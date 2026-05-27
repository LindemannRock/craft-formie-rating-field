<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use Craft;
use craft\db\Query;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;

/**
 * Verifies the recurring cache-generation scheduler pattern.
 *
 * @since 3.20.0
 */
final class SchedulerPatternTest extends TestCase
{
    private ?string $originalSchedule = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSchedule = FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule;
        $this->deleteCacheGenerationQueueRows();
    }

    protected function cleanupExternalState(): void
    {
        $this->deleteCacheGenerationQueueRows();

        if ($this->originalSchedule !== null) {
            FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = $this->originalSchedule;
        }

        parent::cleanupExternalState();
    }

    public function testScheduleOptionsNormalizeLegacyValues(): void
    {
        $settings = FormieRatingField::$plugin->getSettings();

        $settings->cacheGenerationSchedule = 'manual';
        self::assertSame('disabled', $settings->getEffectiveCacheGenerationSchedule());

        $settings->cacheGenerationSchedule = 'twicedaily';
        self::assertSame('every12hours', $settings->getEffectiveCacheGenerationSchedule());

        self::assertSame(
            ['disabled', 'every3hours', 'every6hours', 'every12hours', 'daily', 'daily2am', 'weekly'],
            array_column($settings->getCacheGenerationScheduleOptions(), 'value'),
        );
    }

    public function testScheduledMasterReschedulesEvenWhenCurrentRowExists(): void
    {
        FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = 'daily';

        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => true,
        ]));

        $job = new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => true,
        ]);

        $scheduleNext = new ReflectionMethod($job, 'scheduleNext');
        $scheduleNext->setAccessible(true);
        $scheduleNext->invoke($job);

        self::assertSame(2, $this->countScheduledMasterJobs());
    }

    public function testScheduleChangeReplacesScheduledMasterOnly(): void
    {
        $settings = FormieRatingField::$plugin->getSettings();
        $settings->cacheGenerationSchedule = 'daily';

        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => true,
        ]));
        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'reschedule' => false,
            'scheduledMaster' => false,
            'formId' => self::TEST_FORM_ID,
        ]));

        $settings->cacheGenerationSchedule = 'weekly';
        FormieRatingField::$plugin->handleCacheGenerationScheduleChange($settings, 'daily');

        self::assertSame(1, $this->countScheduledMasterJobs());
        self::assertSame(1, $this->countManualCacheGenerationJobs());
    }

    private function countScheduledMasterJobs(): int
    {
        return (int) $this->cacheGenerationQueueQuery()
            ->andWhere([
                'or',
                ['like', 'job', '"scheduledMaster";b:1'],
                ['like', 'job', '"scheduledMaster":true'],
            ])
            ->count();
    }

    private function countManualCacheGenerationJobs(): int
    {
        return (int) $this->cacheGenerationQueueQuery()
            ->andWhere([
                'or',
                ['like', 'job', '"scheduledMaster";b:0'],
                ['like', 'job', '"scheduledMaster":false'],
            ])
            ->count();
    }

    private function deleteCacheGenerationQueueRows(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', [
                'and',
                ['like', 'job', 'formieratingfield'],
                ['like', 'job', 'GenerateCacheJob'],
            ])
            ->execute();
    }

    private function cacheGenerationQueueQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'formieratingfield'])
            ->andWhere(['like', 'job', 'GenerateCacheJob']);
    }
}
