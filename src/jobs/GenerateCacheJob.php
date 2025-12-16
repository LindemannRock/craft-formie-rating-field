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
use lindemannrock\formieratingfield\FormieRatingField;

/**
 * Generate Cache Job
 *
 * Automatically generates statistics cache for all forms with rating fields
 *
 * @author LindemannRock
 * @since 3.3.0
 */
class GenerateCacheJob extends BaseJob
{
    /**
     * @var bool Whether to reschedule after completion
     */
    public bool $reschedule = false;

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
    public function init(): void
    {
        parent::init();

        // Calculate next run time if rescheduling
        if ($this->reschedule && !$this->nextRunTime) {
            $delay = $this->calculateNextRunDelay();
            if ($delay > 0) {
                $this->nextRunTime = date('M j, g:ia', time() + $delay);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $settings = FormieRatingField::$plugin->getSettings();
        $statisticsService = FormieRatingField::$plugin->get('statistics');

        // If this is the first batch, calculate total batches and clear cache
        if ($this->currentBatch === 1 && !$this->formId) {
            // Clear all cache first
            $statisticsService->clearAllCache();
            Craft::info('Cleared all statistics cache before regeneration', __METHOD__);

            // Calculate and queue all batches
            $this->queueAllBatches($statisticsService);
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

        // Clear this specific cache first to force regeneration
        $cacheFilename = $statisticsService->getCacheFilename($form->id, $field->handle, $this->dateRange, $this->groupBy);
        Craft::info("Generating cache for: {$cacheFilename}", __METHOD__);

        // Generate cache for this specific combination (will save it)
        $statisticsService->getFieldStatistics($form, $field, $this->dateRange, $this->groupBy);

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
    private function calculateNextRunDelay(): int
    {
        $settings = FormieRatingField::$plugin->getSettings();

        // For daily2am, calculate seconds until next 2am
        if ($settings->cacheGenerationSchedule === 'daily2am') {
            $now = time();
            $next2am = strtotime('tomorrow 2:00am');
            if (date('G') >= 2) {
                // If past 2am today, schedule for tomorrow 2am
                $next2am = strtotime('tomorrow 2:00am');
            } else {
                // Before 2am today, schedule for today 2am
                $next2am = strtotime('today 2:00am');
            }
            return $next2am - $now;
        }

        return match ($settings->cacheGenerationSchedule) {
            'every3hours' => 3 * 3600,
            'every6hours' => 6 * 3600,
            'every12hours' => 12 * 3600,
            'daily' => 24 * 3600,
            'twicedaily' => 12 * 3600,
            'weekly' => 7 * 24 * 3600,
            default => 0,
        };
    }

    /**
     * Schedule next run
     */
    private function scheduleNext(): void
    {
        $delay = $this->calculateNextRunDelay();

        if ($delay > 0) {
            Craft::$app->getQueue()->delay($delay)->push(new self([
                'reschedule' => true,
            ]));
        }
    }
}
