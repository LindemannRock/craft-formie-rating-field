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

        // Clear all cache first to ensure fresh calculations
        $statisticsService->clearAllCache();
        Craft::info('Cleared all statistics cache before regeneration', __METHOD__);

        // Get forms to process
        $formsWithRatings = $statisticsService->getFormsWithRatingFields();

        if ($this->formId) {
            // Filter to specific form
            $formsWithRatings = array_filter($formsWithRatings, fn($item) => $item['form']->id == $this->formId);
        }

        $totalForms = count($formsWithRatings);
        $processed = 0;

        // Date ranges to pre-generate
        $dateRanges = ['last7days', 'last30days', 'last90days', 'all'];

        foreach ($formsWithRatings as $item) {
            $form = $item['form'];
            $ratingFields = $statisticsService->getRatingFieldsForForm($form);

            // Update progress label
            $this->setProgress($queue, $processed / $totalForms, Craft::t('formie-rating-field', 'Processing {form}...', [
                'form' => $form->title,
            ]));

            foreach ($ratingFields as $field) {
                foreach ($dateRanges as $dateRange) {
                    // Generate cache (this will save it)
                    $statisticsService->getFieldStatistics($form, $field, $dateRange);

                    // Also generate grouped stats if groupable fields exist
                    $groupableFields = $statisticsService->getGroupableFieldsForForm($form);
                    foreach ($groupableFields as $groupField) {
                        $statisticsService->getFieldStatistics($form, $field, $dateRange, $groupField['handle']);
                    }
                }
            }

            $processed++;
            // Final progress update for this form
            $this->setProgress($queue, $processed / $totalForms);
        }

        Craft::info("Generated cache for {$processed} form(s)", __METHOD__);

        // Reschedule if needed
        if ($this->reschedule) {
            $this->scheduleNext();
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $settings = FormieRatingField::$plugin->getSettings();
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
