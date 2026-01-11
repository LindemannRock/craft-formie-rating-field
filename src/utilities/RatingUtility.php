<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\utilities;

use Craft;
use craft\base\Utility;
use lindemannrock\formieratingfield\FormieRatingField;

/**
 * Rating Utility
 *
 * @author LindemannRock
 * @since 3.3.0
 */
class RatingUtility extends Utility
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return FormieRatingField::$plugin->getSettings()->getFullName();
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'formie-rating';
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'star';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $statisticsService = FormieRatingField::$plugin->get('statistics');
        $settings = FormieRatingField::$plugin->getSettings();
        $cacheCount = $statisticsService->getCacheFileCount();

        Craft::info("Utilities: Cache count = {$cacheCount}, Storage method = {$settings->cacheStorageMethod}", __METHOD__);

        return Craft::$app->getView()->renderTemplate('formie-rating-field/utilities/index', [
            'cacheCount' => $cacheCount,
        ]);
    }
}
