<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\web\assets\field;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Rating Field Asset Bundle
 *
 * This asset bundle provides the CSS and JavaScript needed for the rating field
 * to function properly on the front-end of the site.
 *
 * @author LindemannRock
 * @since 1.0.0
 */
class RatingFieldAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        // Define the path to the assets folder
        $this->sourcePath = __DIR__;

        // Define which files to include
        $this->css = [
            'rating.css',
        ];

        // Use minified JS in production
        $this->js = [
            Craft::$app->getConfig()->getGeneral()->devMode ? 'rating.js' : 'rating.min.js',
        ];

        parent::init();
    }
}