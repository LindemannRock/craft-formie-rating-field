<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\formieratingfield\console\controllers;

use Craft;
use craft\console\Controller;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\formieratingfield\cache\StatisticsCacheStoragePresenter;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use verbb\formie\elements\Form;
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
     * @var int|null Optional form ID filter for cache generation.
     * @since 3.20.0
     */
    public ?int $formId = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'generate') {
            $options[] = 'formId';
        }

        return $options;
    }

    /**
     * Clear all rating field statistics cache
     *
     * Example: php craft formie-rating-field/cache/clear
     */
    public function actionClear(): int
    {
        $this->stdout("Clearing rating field statistics cache...\n");

        $statisticsService = FormieRatingField::$plugin->statistics;

        if ($statisticsService->clearAllCache()) {
            $this->stdout("Successfully cleared statistics cache.\n");
            return ExitCode::OK;
        }

        $this->stderr("Error: Failed to clear statistics cache.\n");
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Clear statistics cache for a specific form
     *
     * Example: php craft formie-rating-field/cache/clear-form 34
     */
    public function actionClearForm(int $formId): int
    {
        $this->stdout("Clearing statistics cache for form ID: {$formId}...\n");

        $statisticsService = FormieRatingField::$plugin->statistics;

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
        $statisticsService = FormieRatingField::$plugin->statistics;
        $decision = $statisticsService->getCacheStorageDecision();
        $presentation = (new StatisticsCacheStoragePresenter())->present($decision);
        $settings = FormieRatingField::$plugin->getSettings();

        $this->stdout("Rating Field Statistics Cache Info:\n");
        $this->stdout("-----------------------------------\n");
        $this->stdout("Configured storage: {$settings->cacheStorageMethod}\n");
        $this->stdout("Status: {$presentation['heading']}\n");
        if ($presentation['explanation'] !== null) {
            $this->stdout("Explanation: {$presentation['explanation']}\n");
        }
        if ($decision->usesFileCache()) {
            // Use the same helper StatisticsService::getCachePath() uses.
            $cachePath = PluginHelper::getCachePath(FormieRatingField::$plugin, 'statistics');
            $this->stdout("File cache path: {$cachePath}\n");
            $this->stdout("File cache entries: {$statisticsService->getCacheFileCount()}\n");
        }
        $this->stdout("Generation schedule: {$settings->getEffectiveCacheGenerationSchedule()}\n");
        $this->stdout("Manual clear: php craft formie-rating-field/cache/clear\n");
        $this->stdout("Manual generate: php craft formie-rating-field/cache/generate\n");

        return ExitCode::OK;
    }

    /**
     * Generate cache for all forms with rating fields
     *
     * Example: php craft formie-rating-field/cache/generate
     * Example: php craft formie-rating-field/cache/generate --form-id=34
     */
    public function actionGenerate(): int
    {
        $statisticsService = FormieRatingField::$plugin->statistics;

        if ($this->formId !== null) {
            $form = Form::find()->id($this->formId)->one();

            if (!$form instanceof Form) {
                $this->stderr("Error: Form ID {$this->formId} was not found.\n");
                return ExitCode::DATAERR;
            }

            if ($statisticsService->getRatingFieldsForForm($form) === []) {
                $this->stdout("No rating fields found for form ID {$this->formId}; no cache generation job queued.\n");
                return ExitCode::OK;
            }
        }

        $this->stdout("Queuing cache generation job...\n");

        // Push to queue instead of running directly
        Craft::$app->getQueue()->push(new GenerateCacheJob([
            'formId' => $this->formId,
            'reschedule' => false, // Manual trigger
        ]));

        $this->stdout("Cache generation job queued successfully.\n");
        $this->stdout("Check progress in the queue manager or wait for completion.\n");

        return ExitCode::OK;
    }
}
