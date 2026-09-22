<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\services;

use Craft;
use craft\db\Query;
use DateTime;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\RecurringQueueHelper;
use lindemannrock\base\helpers\RecurringQueueResult;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use lindemannrock\formieratingfield\models\Settings;
use yii\db\Expression;

/**
 * Owns the recurring statistics-cache generation schedule.
 *
 * @since 3.22.0
 */
class StatisticsCacheScheduler
{
    public const PLUGIN_TOKEN = 'formieratingfield';
    public const RECURRING_OWNER = 'formie-rating-field:statistics-cache:scheduled-master';

    private const SCHEDULE_MUTEX = 'formie-rating-field:schedule-cache-job';
    private const PORTABLE_MUTEX = 'formie-rating-field:schedule-cache-job:portable';

    /**
     * Ensure one recurring scheduled master is pending.
     */
    public function ensurePending(Settings $settings): RecurringQueueResult
    {
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::SCHEDULE_MUTEX)) {
            Craft::debug('Skipped recurring cache scheduling because the schedule mutex is already held.', __METHOD__);

            return new RecurringQueueResult(RecurringQueueResult::STATUS_LOCK_MISSED);
        }

        try {
            $result = $this->ensurePendingUnlocked($settings);
            if ($result->missedLock()) {
                throw new \RuntimeException('Unable to acquire the portable recurring cache schedule lock.');
            }

            return $result;
        } finally {
            $mutex->release(self::SCHEDULE_MUTEX);
        }
    }

    /**
     * Cancel the previous recurring chain and create its replacement when enabled.
     */
    public function replace(Settings $settings): RecurringQueueResult
    {
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire(self::SCHEDULE_MUTEX)) {
            throw new \RuntimeException('Unable to acquire the recurring cache schedule lock.');
        }

        try {
            $this->cancelUnlocked();

            if ($settings->getEffectiveCacheGenerationSchedule() === 'disabled') {
                return new RecurringQueueResult(RecurringQueueResult::STATUS_SKIPPED);
            }

            $result = $this->ensurePendingUnlocked($settings);
            if ($result->missedLock()) {
                throw new \RuntimeException('Unable to acquire the portable recurring cache schedule lock.');
            }

            return $result;
        } finally {
            $mutex->release(self::SCHEDULE_MUTEX);
        }
    }

    private function ensurePendingUnlocked(Settings $settings): RecurringQueueResult
    {
        $schedule = $settings->getEffectiveCacheGenerationSchedule();
        $nextRun = $this->calculateNextRun($schedule);
        $delay = $this->calculateDelay($schedule);

        if ($nextRun === null || $delay <= 0) {
            return new RecurringQueueResult(RecurringQueueResult::STATUS_SKIPPED);
        }

        $legacyRows = $this->legacyPendingRows();
        if ($legacyRows !== []) {
            $duplicatesDeleted = $this->deleteRows(array_slice($legacyRows, 1));

            // Prefer the valid legacy chain during the transition. Its next
            // self-reschedule uses the portable owner identity automatically.
            $duplicatesDeleted += RecurringQueueHelper::deletePending(
                pluginToken: self::PLUGIN_TOKEN,
                jobClass: GenerateCacheJob::class,
                extraLikeTokens: [self::RECURRING_OWNER],
                mutexName: self::PORTABLE_MUTEX,
            );

            return new RecurringQueueResult(
                RecurringQueueResult::STATUS_EXISTING,
                (string) $legacyRows[0]['id'],
                $duplicatesDeleted,
            );
        }

        $result = RecurringQueueHelper::ensurePending(
            pluginToken: self::PLUGIN_TOKEN,
            jobClass: GenerateCacheJob::class,
            delay: $delay,
            jobFactory: fn(): GenerateCacheJob => new GenerateCacheJob([
                'reschedule' => true,
                'scheduledMaster' => true,
                'recurringOwner' => self::RECURRING_OWNER,
                'nextRunTime' => $this->formatNextRunTime($nextRun, $settings),
            ]),
            extraLikeTokens: [self::RECURRING_OWNER],
            mutexName: self::PORTABLE_MUTEX,
        );

        if ($result->missedLock()) {
            Craft::warning('Skipped recurring cache scheduling because the portable schedule mutex could not be acquired.', __METHOD__);
        }

        return $result;
    }

    private function cancelUnlocked(): void
    {
        RecurringQueueHelper::deletePending(
            pluginToken: self::PLUGIN_TOKEN,
            jobClass: GenerateCacheJob::class,
            extraLikeTokens: [self::RECURRING_OWNER],
            mutexName: self::PORTABLE_MUTEX,
        );

        // Remove any failed or reserved final consumer row left outside Base's
        // pending/deferred cancellation set, then remove pre-portable masters.
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', $this->portableScheduledMasterCondition())
            ->execute();
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', $this->legacyScheduledMasterCondition())
            ->execute();
    }

    /**
     * @return list<array{id: int|string}>
     */
    private function legacyPendingRows(): array
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = (new Query())
            ->select(['id'])
            ->from('{{%queue}}')
            ->where($this->legacyScheduledMasterCondition())
            ->andWhere(['fail' => false])
            ->andWhere(['timeUpdated' => null])
            ->orderBy(new Expression('[[timePushed]] + [[delay]] ASC'))
            ->addOrderBy(['priority' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $rows;
    }

    /**
     * @return array<int, mixed>
     */
    private function portableScheduledMasterCondition(): array
    {
        return [
            'and',
            ['like', 'job', self::PLUGIN_TOKEN],
            ['like', 'job', 'GenerateCacheJob'],
            ['like', 'job', self::RECURRING_OWNER],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function legacyScheduledMasterCondition(): array
    {
        return [
            'and',
            ['like', 'job', self::PLUGIN_TOKEN],
            ['like', 'job', 'GenerateCacheJob'],
            ['not like', 'job', self::RECURRING_OWNER],
            [
                'or',
                ['like', 'job', '"scheduledMaster";b:1'],
                ['like', 'job', '"scheduledMaster":true'],
                [
                    'and',
                    ['not like', 'job', 'scheduledMaster'],
                    [
                        'or',
                        ['like', 'job', '"reschedule";b:1'],
                        ['like', 'job', '"reschedule":true'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param list<array{id: int|string}> $rows
     */
    private function deleteRows(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $ids = array_map(static fn(array $row): string => (string) $row['id'], $rows);

        return Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', ['id' => $ids])
            ->execute();
    }

    protected function calculateNextRun(string $schedule): ?DateTime
    {
        return ScheduleHelper::calculateNext($schedule);
    }

    protected function calculateDelay(string $schedule): int
    {
        return ScheduleHelper::calculateDelaySeconds($schedule);
    }

    private function formatNextRunTime(DateTime $nextRun, Settings $settings): string
    {
        return DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            $settings,
            null,
            false,
            pluginHandle: 'formie-rating-field',
        );
    }
}
