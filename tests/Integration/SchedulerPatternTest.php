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
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;
use verbb\formie\elements\Form;

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
}
