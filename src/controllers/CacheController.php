<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use yii\web\Response;

/**
 * Cache Controller
 *
 * @author LindemannRock
 * @since 3.3.0
 */
class CacheController extends Controller
{
    /**
     * Generate cache for all forms (trigger queue job)
     */
    public function actionGenerateAll(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();

        try {
            // Push job to queue
            Craft::$app->getQueue()->push(new GenerateCacheJob([
                'reschedule' => false, // Manual trigger, don't reschedule
            ]));

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('formie-rating-field', 'Cache generation started'),
            ]);
        } catch (\Exception $e) {
            Craft::error('Failed to start cache generation: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all statistics cache
     */
    public function actionClearAll(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();

        try {
            $statisticsService = FormieRatingField::$plugin->get('statistics');
            $statisticsService->clearAllCache();

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('formie-rating-field', 'Cache cleared successfully'),
            ]);
        } catch (\Exception $e) {
            Craft::error('Failed to clear cache: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
