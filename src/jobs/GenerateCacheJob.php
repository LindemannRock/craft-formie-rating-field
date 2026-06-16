<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\jobs;

use Craft;
use craft\queue\BaseJob;
use DateTime;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\traits\QueueTtrTrait;
use lindemannrock\formieratingfield\FormieRatingField;
use yii\queue\RetryableJobInterface;

/**
 * Generate Cache Job
 *
 * Automatically generates statistics cache for all forms with rating fields.
 *
 * Site dimension: only the cross-site aggregate (siteId='all') is pre-warmed.
 * Per-site queries compute live on first load (no cache hit) and are then stored
 * on save, but are not iterated here. Pre-warming every (form × field × dateRange × site)
 * combination would multiply queue size by the number of editable sites with no
 * meaningful benefit for the typical single-site or cross-site view.
 *
 * @author LindemannRock
 * @since 3.10.0
 */
class GenerateCacheJob extends BaseJob implements RetryableJobInterface
{
    use QueueTtrTrait;

    /**
     * @var bool Whether to reschedule after completion
     */
    public bool $reschedule = false;

    /**
     * @var bool Whether this row is the recurring scheduler master job
     *
     * @since 3.20.0
     */
    public bool $scheduledMaster = false;

    /**
     * @var string|null Next run time display string
     */
    public ?string $nextRunTime = null;

    /**
     * @var int|null Specific form ID to generate (null = all forms)
     */
    public ?int $formId = null;

    /**
     * @var string|null Specific field handle to process
     */
    public ?string $fieldHandle = null;

    /**
     * @var string|null Specific date range to process
     */
    public ?string $dateRange = null;

    /**
     * @var string|null Specific groupBy field to process
     */
    public ?string $groupBy = null;

    /**
     * @var int Current batch number
     */
    public int $currentBatch = 1;

    /**
     * @var int Total batches
     */
    public int $totalBatches = 1;

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        if ($this->reschedule && !$this->nextRunTime) {
            $this->nextRunTime = $this->formatNextRunTime($this->calculateNextRun());
        }
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $statisticsService = FormieRatingField::$plugin->statistics;

        // If this is the first batch, calculate total batches and clear cache
        if ($this->currentBatch === 1 && !$this->formId) {
            // Clear all cache first
            $statisticsService->clearAllCache();
            Craft::info('Cleared all statistics cache before regeneration', __METHOD__);

            // Calculate and queue all batches
            $this->queueAllBatches($statisticsService);

            if ($this->reschedule) {
                $this->scheduleNext();
            }

            return;
        }

        // Process this specific batch
        $this->processBatch($statisticsService, $queue);

        // Reschedule master job if needed
        if ($this->reschedule && $this->currentBatch === 1) {
            $this->scheduleNext();
        }
    }

    /**
     * Calculate and queue all batches
     */
    private function queueAllBatches($statisticsService): void
    {
        $formsWithRatings = $statisticsService->getFormsWithRatingFields();
        $dateRanges = ['last7days', 'last30days', 'last90days', 'all'];

        $batches = [];

        foreach ($formsWithRatings as $item) {
            $form = $item['form'];
            $ratingFields = $statisticsService->getRatingFieldsForForm($form);
            $groupableFields = $statisticsService->getGroupableFieldsForForm($form);

            foreach ($ratingFields as $field) {
                foreach ($dateRanges as $range) {
                    // Batch 1: Ungrouped stats for this field + range
                    $batches[] = [
                        'formId' => $form->id,
                        'fieldHandle' => $field->handle,
                        'dateRange' => $range,
                        'groupBy' => null,
                    ];

                    // Batches 2-N: Each grouping
                    foreach ($groupableFields as $groupField) {
                        $batches[] = [
                            'formId' => $form->id,
                            'fieldHandle' => $field->handle,
                            'dateRange' => $range,
                            'groupBy' => $groupField['handle'],
                        ];
                    }
                }
            }
        }

        $totalBatches = count($batches);

        // Queue each batch
        foreach ($batches as $index => $batchConfig) {
            Craft::$app->getQueue()->push(new self([
                'formId' => $batchConfig['formId'],
                'fieldHandle' => $batchConfig['fieldHandle'],
                'dateRange' => $batchConfig['dateRange'],
                'groupBy' => $batchConfig['groupBy'],
                'currentBatch' => $index + 1,
                'totalBatches' => $totalBatches,
                'reschedule' => false,
                'scheduledMaster' => false,
            ]));
        }

        Craft::info("Queued {$totalBatches} cache generation batches", __METHOD__);
    }

    /**
     * Process a single batch
     */
    private function processBatch($statisticsService, $queue): void
    {
        // Get the specific form and field
        $form = \verbb\formie\elements\Form::find()->id($this->formId)->one();

        if (!$form) {
            return;
        }

        $ratingFields = $statisticsService->getRatingFieldsForForm($form);
        $field = null;

        foreach ($ratingFields as $ratingField) {
            if ($ratingField->handle === $this->fieldHandle) {
                $field = $ratingField;
                break;
            }
        }

        if (!$field) {
            return;
        }

        // Update progress
        $this->setProgress($queue, $this->currentBatch / $this->totalBatches,
            Craft::t('formie-rating-field', 'Batch {current} of {total}: {form} - {field}', [
                'current' => $this->currentBatch,
                'total' => $this->totalBatches,
                'form' => $form->title,
                'field' => $field->label,
            ])
        );

        // Clear this specific cache first to force regeneration (always siteId='all' for pre-warming)
        $cacheFilename = $statisticsService->getCacheFilename($form->id, $field->handle, $this->dateRange, $this->groupBy, 'all');
        Craft::info("Generating cache for: {$cacheFilename}", __METHOD__);

        // Generate cache for this specific combination (will save it).
        // siteId='all' is explicitly passed — the job only pre-warms cross-site aggregates.
        $statisticsService->getFieldStatistics($form, $field, $this->dateRange, $this->groupBy, 'all');

        Craft::info("Completed batch {$this->currentBatch}/{$this->totalBatches}", __METHOD__);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $settings = FormieRatingField::$plugin->getSettings();

        // Show batch info if this is a specific batch
        if ($this->totalBatches > 1) {
            return Craft::t('formie-rating-field', '{pluginName}: Generating cache (batch {current} of {total})', [
                'pluginName' => $settings->getDisplayName(),
                'current' => $this->currentBatch,
                'total' => $this->totalBatches,
            ]);
        }

        $description = Craft::t('formie-rating-field', '{pluginName}: Generating statistics cache', [
            'pluginName' => $settings->getDisplayName(),
        ]);

        if ($this->nextRunTime) {
            $description .= " ({$this->nextRunTime})";
        }

        return $description;
    }

    /**
     * Calculate delay until next run based on schedule
     */
    public function calculateNextRunDelay(): int
    {
        $settings = FormieRatingField::$plugin->getSettings();
        $schedule = $settings->getEffectiveCacheGenerationSchedule();

        return ScheduleHelper::calculateDelaySeconds($schedule);
    }

    /**
     * Calculate the next scheduled run.
     */
    private function calculateNextRun(): ?DateTime
    {
        $settings = FormieRatingField::$plugin->getSettings();

        return ScheduleHelper::calculateNext($settings->getEffectiveCacheGenerationSchedule());
    }

    /**
     * Format the next run for the serialized queue description.
     */
    private function formatNextRunTime(?DateTime $nextRun): ?string
    {
        if ($nextRun === null) {
            return null;
        }

        return DateFormatHelper::formatCompactDatetimeFromSettings(
            $nextRun,
            FormieRatingField::$plugin->getSettings(),
            null,
            false,
            pluginHandle: 'formie-rating-field',
        );
    }

    /**
     * Schedule next run
     */
    private function scheduleNext(): void
    {
        // Mutex prevents parallel master jobs from pushing the same next row.
        // Non-blocking acquire: if another job is currently scheduling, skip.
        $mutex = Craft::$app->getMutex();
        $lockName = 'formie-rating-field:schedule-cache-job';

        if (!$mutex->acquire($lockName)) {
            return;
        }

        try {
            $nextRun = $this->calculateNextRun();
            $delay = $this->calculateNextRunDelay();

            if ($nextRun !== null && $delay > 0) {
                Craft::$app->getQueue()->delay($delay)->push(new self([
                    'reschedule' => true,
                    'scheduledMaster' => true,
                    'nextRunTime' => $this->formatNextRunTime($nextRun),
                ]));

                Craft::info('Scheduled next cache generation', __METHOD__);
            }
        } finally {
            $mutex->release($lockName);
        }
    }
}
