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
use craft\helpers\DateTimeHelper;
use craft\queue\Queue;
use DateTime;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\RecurringQueueHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\queue\DeferredQueueJob;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use lindemannrock\formieratingfield\models\Settings;
use lindemannrock\formieratingfield\services\StatisticsCacheScheduler;
use lindemannrock\formieratingfield\tests\Support\InstalledBasePackage;
use lindemannrock\formieratingfield\tests\Support\IsolatedQueue;
use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use verbb\formie\elements\Form;
use yii\queue\sqs\Queue as SqsQueue;

/**
 * Verifies the recurring cache-generation scheduler pattern.
 *
 * @since 3.20.0
 */
final class SchedulerPatternTest extends TestCase
{
    private const START_TIMESTAMP = 1_800_000_000;

    private ?string $originalSchedule = null;
    private ?Queue $originalQueue = null;
    private ?SchedulerRecordingSqsQueue $proxyQueue = null;
    private ?IsolatedQueue $isolatedQueue = null;
    private bool $timePaused = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSchedule = FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule;
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(IsolatedQueue::class, $queue);
        $this->isolatedQueue = $queue;
        $this->isolatedQueue->clearShadowRows();
    }

    protected function cleanupExternalState(): void
    {
        try {
            $this->isolatedQueue?->clearShadowRows();
        } finally {
            $this->isolatedQueue = null;

            if ($this->timePaused) {
                DateTimeHelper::resume();
                $this->timePaused = false;
            }

            if ($this->originalQueue !== null) {
                Craft::$app->set('queue', $this->originalQueue);
                $this->originalQueue = null;
            }

            $this->proxyQueue = null;

            if ($this->originalSchedule !== null) {
                FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = $this->originalSchedule;
            }

            parent::cleanupExternalState();
        }
    }

    public function testApprovedBasePortableQueueApiLoadsFromTheInstalledPackage(): void
    {
        self::assertSame('lindemannrock/craft-plugin-base', InstalledBasePackage::name());

        foreach ([
            RecurringQueueHelper::class => 'helpers/RecurringQueueHelper.php',
            DeferredQueueJob::class => 'queue/DeferredQueueJob.php',
        ] as $class => $relativePath) {
            $expectedSource = InstalledBasePackage::sourceFile($relativePath);
            self::assertFileExists($expectedSource);
            self::assertSame(
                $expectedSource,
                InstalledBasePackage::reflectedClassFile($class),
            );
        }

        self::assertTrue(method_exists(RecurringQueueHelper::class, 'ensurePending'));
        self::assertTrue(method_exists(RecurringQueueHelper::class, 'deletePending'));
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
        self::assertSame(
            ['Disabled', 'Every 3 Hours', 'Every 6 Hours', 'Every 12 Hours', 'Daily', 'Daily at 2:00 AM', 'Weekly'],
            array_column($settings->getCacheGenerationScheduleOptions(), 'label'),
        );
    }

    #[DataProvider('fixedScheduleProvider')]
    public function testFixedScheduleCalculationsRemainOnExistingBoundaries(string $schedule, string $expected): void
    {
        $from = new DateTime('2026-08-17 10:47:03', new \DateTimeZone('Africa/Cairo'));
        $next = ScheduleHelper::calculateNext($schedule, $from);

        self::assertInstanceOf(DateTime::class, $next);
        self::assertSame($expected, $next->format('Y-m-d H:i:s'));
        self::assertSame($next->getTimestamp() - $from->getTimestamp(), ScheduleHelper::calculateDelaySeconds($schedule, $from));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fixedScheduleProvider(): iterable
    {
        yield 'every three hours' => ['every3hours', '2026-08-17 12:00:00'];
        yield 'every six hours' => ['every6hours', '2026-08-17 12:00:00'];
        yield 'every twelve hours' => ['every12hours', '2026-08-17 12:00:00'];
        yield 'daily' => ['daily', '2026-08-18 00:00:00'];
        yield 'daily at two' => ['daily2am', '2026-08-18 02:00:00'];
    }

    public function testWeeklyCalculationRetainsConfiguredWallClockBoundary(): void
    {
        $from = new DateTime('2026-08-17 10:47:03', new \DateTimeZone('Africa/Cairo'));
        $next = ScheduleHelper::calculateNext('weekly', $from);

        self::assertInstanceOf(DateTime::class, $next);
        self::assertSame('00:00:00', $next->format('H:i:s'));
        self::assertGreaterThan($from, $next);
        self::assertSame($next->getTimestamp() - $from->getTimestamp(), ScheduleHelper::calculateDelaySeconds('weekly', $from));
    }

    public function testRunningScheduledMasterCreatesOnePortableSuccessor(): void
    {
        FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = 'daily';

        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);
        $currentId = $queue->push(new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => true,
            'recurringOwner' => StatisticsCacheScheduler::RECURRING_OWNER,
        ]));
        self::assertNotNull($currentId);
        $this->markExecuting($queue, $currentId);

        $job = new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => true,
            'recurringOwner' => StatisticsCacheScheduler::RECURRING_OWNER,
        ]);

        try {
            $this->invokeScheduleNext($job);
            $this->invokeScheduleNext($job);

            self::assertSame(2, $this->countScheduledMasterJobs());
            self::assertSame(1, $this->countPendingPortableScheduledMasterJobs());
        } finally {
            $this->markExecuting($queue, null);
        }
    }

    public function testSqsLongDelayUsesBoundedHandoffWithoutDispatchingTheConsumerEarly(): void
    {
        $queue = $this->installTestQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $settings = $this->settingsWithSchedule('every6hours');
        $nextRun = $this->dateAt(self::START_TIMESTAMP + 1_278);
        $scheduler = new FixedStatisticsCacheScheduler($nextRun, 1_278);

        $result = $scheduler->ensurePending($settings);

        self::assertTrue($result->wasCreated());
        self::assertNotNull($result->jobId);
        $firstRow = $this->fetchOnlyPortableScheduledRow();
        $firstHandoff = $this->unserializeJob($firstRow);
        self::assertInstanceOf(DeferredQueueJob::class, $firstHandoff);
        self::assertSame(self::START_TIMESTAMP + 1_278, $firstHandoff->targetTimestamp);
        self::assertInstanceOf(GenerateCacheJob::class, $firstHandoff->job);
        self::assertSame(StatisticsCacheScheduler::RECURRING_OWNER, $firstHandoff->job->recurringOwner);
        self::assertSame(
            DateFormatHelper::formatCompactDatetimeFromSettings(
                $nextRun,
                $settings,
                null,
                false,
                pluginHandle: 'formie-rating-field',
            ),
            $firstHandoff->job->nextRunTime,
        );
        self::assertSame(900, (int) $firstRow['delay']);

        $this->pauseAt(self::START_TIMESTAMP + 900);
        self::assertTrue($queue->executeJob($result->jobId));

        $consumerRow = $this->fetchOnlyPortableScheduledRow();
        $consumer = $this->unserializeJob($consumerRow);
        self::assertInstanceOf(GenerateCacheJob::class, $consumer);
        self::assertSame(378, (int) $consumerRow['delay']);
        self::assertSame([900, 378], $this->proxyDelays());
        self::assertLessThanOrEqual(900, max($this->proxyDelays()));
    }

    #[DataProvider('portableBoundaryProvider')]
    public function testPortableSchedulesKeepTheirAbsoluteTarget(int $delay): void
    {
        $this->installTestQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $settings = $this->settingsWithSchedule('every6hours');
        $scheduler = new FixedStatisticsCacheScheduler($this->dateAt(self::START_TIMESTAMP + $delay), $delay);

        $scheduler->ensurePending($settings);

        $handoff = $this->unserializeJob($this->fetchOnlyPortableScheduledRow());
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        self::assertSame(self::START_TIMESTAMP + $delay, $handoff->targetTimestamp);
        self::assertSame([900], $this->proxyDelays());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function portableBoundaryProvider(): iterable
    {
        yield 'six hours' => [21_600];
        yield 'daily' => [86_400];
        yield 'weekly' => [604_800];
    }

    public function testLocalQueueRetainsTheCompleteDelay(): void
    {
        $this->installTestQueue(false);
        $this->pauseAt(self::START_TIMESTAMP);
        $settings = $this->settingsWithSchedule('every6hours');
        $scheduler = new FixedStatisticsCacheScheduler($this->dateAt(self::START_TIMESTAMP + 1_278), 1_278);

        $scheduler->ensurePending($settings);

        $row = $this->fetchOnlyPortableScheduledRow();
        self::assertInstanceOf(GenerateCacheJob::class, $this->unserializeJob($row));
        self::assertSame(1_278, (int) $row['delay']);
        self::assertSame([], $this->proxyDelays());
    }

    public function testRepeatedPortableSchedulingKeepsOneChain(): void
    {
        $this->installTestQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $settings = $this->settingsWithSchedule('every6hours');
        $scheduler = new FixedStatisticsCacheScheduler($this->dateAt(self::START_TIMESTAMP + 1_278), 1_278);

        $first = $scheduler->ensurePending($settings);
        $second = $scheduler->ensurePending($settings);

        self::assertTrue($first->wasCreated());
        self::assertTrue($second->hasPending());
        self::assertFalse($second->wasCreated());
        self::assertSame($first->jobId, $second->jobId);
        self::assertSame(1, $this->countPortableScheduledMasterJobs());
        self::assertSame([900], $this->proxyDelays());
    }

    public function testDisableCancelsPortableAndLegacyChainsWithoutDeletingManualOrConcreteJobs(): void
    {
        $queue = $this->installTestQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $settings = $this->settingsWithSchedule('every6hours');
        $scheduler = new FixedStatisticsCacheScheduler($this->dateAt(self::START_TIMESTAMP + 1_278), 1_278);
        $scheduler->ensurePending($settings);
        $this->pushLegacyScheduledMasterJob();

        $manualId = $queue->push(new GenerateCacheJob([
            'formId' => self::TEST_FORM_ID,
            'reschedule' => false,
            'scheduledMaster' => false,
        ]));
        $batchId = $queue->push(new GenerateCacheJob([
            'formId' => self::TEST_FORM_ID,
            'fieldHandle' => 'rating',
            'dateRange' => 'last30days',
            'reschedule' => false,
            'scheduledMaster' => false,
        ]));

        $settings->cacheGenerationSchedule = 'disabled';
        $result = $scheduler->replace($settings);

        self::assertTrue($result->wasSkipped());
        self::assertSame(0, $this->countScheduledMasterJobs());
        self::assertTrue($this->queueRowExists((string) $manualId));
        self::assertTrue($this->queueRowExists((string) $batchId));
    }

    public function testScheduleReplacementKeepsManualJobsAndUsesTheNewBoundary(): void
    {
        $queue = $this->installTestQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $settings = $this->settingsWithSchedule('every6hours');
        (new FixedStatisticsCacheScheduler($this->dateAt(self::START_TIMESTAMP + 1_278), 1_278))->ensurePending($settings);
        $manualId = $queue->push(new GenerateCacheJob([
            'formId' => self::TEST_FORM_ID,
            'reschedule' => false,
            'scheduledMaster' => false,
        ]));

        $settings->cacheGenerationSchedule = 'weekly';
        $replacement = new FixedStatisticsCacheScheduler($this->dateAt(self::START_TIMESTAMP + 604_800), 604_800);
        $result = $replacement->replace($settings);

        self::assertTrue($result->wasCreated());
        self::assertSame(1, $this->countPortableScheduledMasterJobs());
        self::assertTrue($this->queueRowExists((string) $manualId));
        $handoff = $this->unserializeJob($this->fetchOnlyPortableScheduledRow());
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        self::assertSame(self::START_TIMESTAMP + 604_800, $handoff->targetTimestamp);
    }

    public function testProxyFailureRemainsObservable(): void
    {
        $this->installTestQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        self::assertNotNull($this->proxyQueue);
        $this->proxyQueue->failPushes = true;
        $settings = $this->settingsWithSchedule('every6hours');
        $scheduler = new FixedStatisticsCacheScheduler($this->dateAt(self::START_TIMESTAMP + 1_278), 1_278);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rating scheduler proxy failure.');

        $scheduler->ensurePending($settings);
    }

    public function testBootstrapIgnoresFailedScheduledMasterRow(): void
    {
        FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = 'daily';

        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => true,
        ]));
        self::assertSame(1, $this->countScheduledMasterJobs());

        Craft::$app->getDb()->createCommand()
            ->update('{{%queue}}', ['fail' => true], [
                'and',
                ['like', 'job', 'formieratingfield'],
                ['like', 'job', 'GenerateCacheJob'],
                [
                    'or',
                    ['like', 'job', '"scheduledMaster";b:1'],
                    ['like', 'job', '"scheduledMaster":true'],
                ],
            ])
            ->execute();

        $scheduleInitial = new ReflectionMethod(FormieRatingField::$plugin, 'scheduleInitialCacheGeneration');
        $scheduleInitial->setAccessible(true);
        $scheduleInitial->invoke(FormieRatingField::$plugin);

        self::assertSame(2, $this->countScheduledMasterJobs());
    }

    public function testBootstrapCreatesThePortableRecurringIdentity(): void
    {
        FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = 'daily';

        $this->invokeInitialSchedule();

        self::assertSame(1, $this->countPortableScheduledMasterJobs());
        $job = $this->unserializeJob($this->fetchOnlyPortableScheduledRow());
        self::assertInstanceOf(GenerateCacheJob::class, $job);
        self::assertTrue($job->scheduledMaster);
        self::assertTrue($job->reschedule);
        self::assertSame(StatisticsCacheScheduler::RECURRING_OWNER, $job->recurringOwner);
    }

    public function testBootstrapRecognizesLegacyScheduledMasterMarker(): void
    {
        FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = 'daily';

        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => true,
        ]));

        $this->invokeInitialSchedule();

        self::assertSame(1, $this->countCacheGenerationJobs());
        self::assertSame(0, $this->countPortableScheduledMasterJobs());
    }

    public function testBootstrapRecognizesLegacyScheduledMasterRowWithoutMarker(): void
    {
        FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = 'daily';

        $this->pushLegacyScheduledMasterJob();
        self::assertSame(1, $this->countCacheGenerationJobs());

        $scheduleInitial = new ReflectionMethod(FormieRatingField::$plugin, 'scheduleInitialCacheGeneration');
        $scheduleInitial->setAccessible(true);
        $scheduleInitial->invoke(FormieRatingField::$plugin);

        self::assertSame(1, $this->countCacheGenerationJobs());
    }

    public function testBootstrapCollapsesDuplicateLegacyScheduledMasterRows(): void
    {
        FormieRatingField::$plugin->getSettings()->cacheGenerationSchedule = 'daily';

        $this->pushLegacyScheduledMasterJob();
        $this->pushLegacyScheduledMasterJob();
        self::assertSame(2, $this->countCacheGenerationJobs());

        $scheduleInitial = new ReflectionMethod(FormieRatingField::$plugin, 'scheduleInitialCacheGeneration');
        $scheduleInitial->setAccessible(true);
        $scheduleInitial->invoke(FormieRatingField::$plugin);

        self::assertSame(1, $this->countCacheGenerationJobs());
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

    public function testScheduleChangeReplacesLegacyScheduledMasterOnly(): void
    {
        $settings = FormieRatingField::$plugin->getSettings();
        $settings->cacheGenerationSchedule = 'daily';

        $this->pushLegacyScheduledMasterJob();
        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'reschedule' => false,
            'scheduledMaster' => false,
            'formId' => self::TEST_FORM_ID,
        ]));

        $settings->cacheGenerationSchedule = 'weekly';
        FormieRatingField::$plugin->handleCacheGenerationScheduleChange($settings, 'daily');

        self::assertSame(1, $this->countScheduledMasterJobs());
        self::assertSame(1, $this->countManualCacheGenerationJobs());
        self::assertSame(2, $this->countCacheGenerationJobs());
    }

    public function testManualFormScopedMasterQueuesConcreteBatches(): void
    {
        $form = $this->findFirstFormWithRatingFields();

        if (!$form instanceof Form) {
            self::markTestSkipped('No Formie form with rating fields is available in the test database.');
        }

        $ratingFields = $this->statistics->getRatingFieldsForForm($form);
        $groupableFields = $this->statistics->getGroupableFieldsForForm($form);
        $expectedBatches = count($ratingFields) * 4 * (count($groupableFields) + 1);

        self::assertGreaterThan(0, $expectedBatches);

        $job = new GenerateCacheJob([
            'formId' => (int)$form->id,
            'reschedule' => false,
            'scheduledMaster' => false,
        ]);

        $job->execute(Craft::$app->getQueue());

        self::assertSame($expectedBatches, $this->countManualCacheGenerationJobs());

        foreach ($this->cacheGenerationQueueQuery()->all() as $row) {
            self::assertIsArray($row);
            self::assertStringContainsString('fieldHandle', (string)$row['job']);
            self::assertStringContainsString('dateRange', (string)$row['job']);
        }
    }

    public function testManualFormScopedMasterForMissingFormQueuesNoBatches(): void
    {
        $job = new GenerateCacheJob([
            'formId' => self::TEST_FORM_ID,
            'reschedule' => false,
            'scheduledMaster' => false,
        ]);

        $job->execute(Craft::$app->getQueue());

        self::assertSame(0, $this->countCacheGenerationJobs());
    }

    public function testGroupedBatchSkipsStaleGroupByHandle(): void
    {
        $form = $this->findFirstFormWithRatingFields();

        if (!$form instanceof Form) {
            self::markTestSkipped('No Formie form with rating fields is available in the test database.');
        }

        if ($this->statistics->getGroupableFieldsForForm($form) === []) {
            self::markTestSkipped('No Formie form with groupable fields is available in the test database.');
        }

        $ratingFields = array_values($this->statistics->getRatingFieldsForForm($form));
        $field = $ratingFields[0] ?? null;

        if (!$field instanceof Rating) {
            self::markTestSkipped('No rating field is available for the selected Formie form.');
        }

        $staleGroupBy = 'removedGroupedFieldForTest';
        $cacheFile = $this->statisticsCachePath() . $this->statistics->getCacheFilename(
            (int)$form->id,
            $field->handle,
            'last7days',
            $staleGroupBy,
            'all'
        );
        @unlink($cacheFile);

        $job = new GenerateCacheJob([
            'formId' => (int)$form->id,
            'fieldHandle' => $field->handle,
            'dateRange' => 'last7days',
            'groupBy' => $staleGroupBy,
            'currentBatch' => 1,
            'totalBatches' => 1,
            'reschedule' => false,
            'scheduledMaster' => false,
        ]);

        $processBatch = new ReflectionMethod($job, 'processBatch');
        $processBatch->setAccessible(true);
        $processBatch->invoke($job, $this->statistics, Craft::$app->getQueue());

        self::assertFileDoesNotExist($cacheFile);
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

    private function countPortableScheduledMasterJobs(): int
    {
        return (int) $this->portableScheduledQueueQuery()->count();
    }

    private function countPendingPortableScheduledMasterJobs(): int
    {
        return (int) $this->portableScheduledQueueQuery()
            ->andWhere(['fail' => false])
            ->andWhere(['timeUpdated' => null])
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

    private function countCacheGenerationJobs(): int
    {
        return (int) $this->cacheGenerationQueueQuery()->count();
    }

    private function pushLegacyScheduledMasterJob(): void
    {
        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'reschedule' => true,
            'scheduledMaster' => false,
        ]));

        $row = $this->cacheGenerationQueueQuery()
            ->orderBy(['id' => SORT_DESC])
            ->one();

        self::assertIsArray($row);

        $job = (string) $row['job'];
        $job = str_replace('s:15:"scheduledMaster";b:0;', '', $job);
        $job = str_replace('"scheduledMaster":false,', '', $job);
        $job = str_replace(',"scheduledMaster":false', '', $job);

        Craft::$app->getDb()->createCommand()
            ->update('{{%queue}}', ['job' => $job], ['id' => (int) $row['id']])
            ->execute();
    }

    private function findFirstFormWithRatingFields(): ?Form
    {
        foreach ($this->statistics->getFormsWithRatingFields() as $item) {
            $form = $item['form'] ?? null;

            if ($form instanceof Form) {
                return $form;
            }
        }

        return null;
    }

    private function cacheGenerationQueueQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'formieratingfield'])
            ->andWhere(['like', 'job', 'GenerateCacheJob']);
    }

    private function portableScheduledQueueQuery(): Query
    {
        return $this->cacheGenerationQueueQuery()
            ->andWhere(['like', 'job', StatisticsCacheScheduler::RECURRING_OWNER]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchOnlyPortableScheduledRow(): array
    {
        $rows = $this->portableScheduledQueueQuery()->orderBy(['id' => SORT_ASC])->all();
        self::assertCount(1, $rows);

        return $rows[0];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function unserializeJob(array $row): object
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);
        $job = $queue->serializer->unserialize((string) $row['job']);
        self::assertIsObject($job);

        return $job;
    }

    private function installTestQueue(bool $bounded): Queue
    {
        if ($this->originalQueue === null) {
            $original = Craft::$app->getQueue();
            self::assertInstanceOf(Queue::class, $original);
            $this->originalQueue = $original;
        }

        $this->proxyQueue = $bounded ? new SchedulerRecordingSqsQueue() : null;
        $queue = new Queue([
            'db' => Craft::$app->getDb(),
            'mutex' => Craft::$app->getMutex(),
            'proxyQueue' => $this->proxyQueue,
        ]);
        Craft::$app->set('queue', $queue);

        return $queue;
    }

    private function pauseAt(int $timestamp): void
    {
        if ($this->timePaused) {
            DateTimeHelper::resume();
        }

        DateTimeHelper::pause(new DateTime("@$timestamp"));
        $this->timePaused = true;
    }

    private function dateAt(int $timestamp): DateTime
    {
        return new DateTime("@$timestamp");
    }

    private function settingsWithSchedule(string $schedule): Settings
    {
        $settings = FormieRatingField::$plugin->getSettings();
        $settings->cacheGenerationSchedule = $schedule;

        return $settings;
    }

    /**
     * @return list<int>
     */
    private function proxyDelays(): array
    {
        return $this->proxyQueue === null ? [] : array_column($this->proxyQueue->pushes, 'delay');
    }

    private function queueRowExists(string $id): bool
    {
        return (new Query())->from('{{%queue}}')->where(['id' => $id])->exists();
    }

    private function markExecuting(Queue $queue, ?string $jobId): void
    {
        if ($jobId !== null) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%queue}}', ['timeUpdated' => time()], ['id' => $jobId])
                ->execute();
        }

        $property = new ReflectionProperty(Queue::class, '_executingJobId');
        $property->setValue($queue, $jobId);
    }

    private function invokeScheduleNext(GenerateCacheJob $job): void
    {
        $scheduleNext = new ReflectionMethod($job, 'scheduleNext');
        $scheduleNext->setAccessible(true);
        $scheduleNext->invoke($job);
    }

    private function invokeInitialSchedule(): void
    {
        $scheduleInitial = new ReflectionMethod(FormieRatingField::$plugin, 'scheduleInitialCacheGeneration');
        $scheduleInitial->setAccessible(true);
        $scheduleInitial->invoke(FormieRatingField::$plugin);
    }
}

/**
 * Provides deterministic next-run boundaries for portable queue behavior.
 *
 * @since 3.22.0
 */
final class FixedStatisticsCacheScheduler extends StatisticsCacheScheduler
{
    public function __construct(
        private readonly DateTime $nextRun,
        private readonly int $delay,
    ) {
    }

    protected function calculateNextRun(string $schedule): ?DateTime
    {
        return clone $this->nextRun;
    }

    protected function calculateDelay(string $schedule): int
    {
        return $this->delay;
    }
}

/**
 * Records proxy delays without contacting SQS.
 *
 * @since 3.22.0
 */
final class SchedulerRecordingSqsQueue extends SqsQueue
{
    /**
     * @var list<array{delay: int, priority: mixed, ttr: int}>
     */
    public array $pushes = [];

    public bool $failPushes = false;

    protected function pushMessage($message, $ttr, $delay, $priority): string
    {
        if ($this->failPushes) {
            throw new \RuntimeException('Rating scheduler proxy failure.');
        }

        $this->pushes[] = [
            'delay' => (int) $delay,
            'priority' => $priority,
            'ttr' => (int) $ttr,
        ];

        return 'rating-scheduler-proxy-' . count($this->pushes);
    }
}
