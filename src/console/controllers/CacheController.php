<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\console\controllers;

use Craft;
use craft\console\Controller;
use lindemannrock\formieratingfield\FormieRatingField;
use yii\console\ExitCode;

/**
 * Cache management commands
 *
 * @author LindemannRock
 * @since 3.3.0
 */
class CacheController extends Controller
{
    /**
     * Clear all rating field statistics cache
     *
     * Example: php craft formie-rating-field/cache/clear
     */
    public function actionClear(): int
    {
        $this->stdout("Clearing rating field statistics cache...\n");

        $statisticsService = FormieRatingField::$plugin->get('statistics');

        if (!$statisticsService) {
            $this->stderr("Error: Statistics service not available.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $cacheCount = $statisticsService->getCacheFileCount();

        if ($cacheCount === 0) {
            $this->stdout("No cache files to clear.\n");
            return ExitCode::OK;
        }

        $this->stdout("Found {$cacheCount} cache file(s).\n");

        if ($statisticsService->clearAllCache()) {
            $this->stdout("Successfully cleared all statistics cache files.\n");
            return ExitCode::OK;
        }

        $this->stderr("Error: Failed to clear cache files.\n");
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Clear statistics cache for a specific form
     *
     * Example: php craft formie-rating-field/cache/clear-form --formId=34
     */
    public function actionClearForm(int $formId): int
    {
        $this->stdout("Clearing statistics cache for form ID: {$formId}...\n");

        $statisticsService = FormieRatingField::$plugin->get('statistics');

        if (!$statisticsService) {
            $this->stderr("Error: Statistics service not available.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($statisticsService->clearCacheForForm($formId)) {
            $this->stdout("Successfully cleared cache for form {$formId}.\n");
            return ExitCode::OK;
        }

        $this->stderr("Error: Failed to clear cache for form {$formId}.\n");
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Show cache statistics
     *
     * Example: php craft formie-rating-field/cache/info
     */
    public function actionInfo(): int
    {
        $statisticsService = FormieRatingField::$plugin->get('statistics');

        if (!$statisticsService) {
            $this->stderr("Error: Statistics service not available.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $cacheCount = $statisticsService->getCacheFileCount();
        $cachePath = Craft::$app->getPath()->getStoragePath() . '/formie-rating-field/cache/statistics/';
        $settings = FormieRatingField::$plugin->getSettings();

        $this->stdout("Rating Field Statistics Cache Info:\n");
        $this->stdout("-----------------------------------\n");
        $this->stdout("Cache path: {$cachePath}\n");
        $this->stdout("Cache files: {$cacheCount}\n");
        $this->stdout("Generation schedule: {$settings->cacheGenerationSchedule}\n");
        $this->stdout("Manual clear: php craft formie-rating-field/cache/clear\n");
        $this->stdout("Manual generate: php craft formie-rating-field/cache/generate\n");

        return ExitCode::OK;
    }

    /**
     * Generate cache for all forms with rating fields
     *
     * Example: php craft formie-rating-field/cache/generate
     * Example: php craft formie-rating-field/cache/generate --formId=34
     */
    public function actionGenerate(?int $formId = null): int
    {
        $this->stdout("Queuing cache generation job...\n");

        // Push to queue instead of running directly
        Craft::$app->getQueue()->push(new \lindemannrock\formieratingfield\jobs\GenerateCacheJob([
            'formId' => $formId,
            'reschedule' => false, // Manual trigger
        ]));

        $this->stdout("Cache generation job queued successfully.\n");
        $this->stdout("Check progress in the queue manager or wait for completion.\n");

        return ExitCode::OK;
    }
}
