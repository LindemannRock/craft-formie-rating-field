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
use lindemannrock\formieratingfield\FormieRatingField;

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

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        // Load Noto Color Emoji web font if configured
        $plugin = FormieRatingField::$plugin;
        if ($plugin !== null) {
            $settings = $plugin->getSettings();
            if ($settings->defaultEmojiRenderMode === 'webfont') {
                // Register Google Fonts Noto Color Emoji
                $view->registerCssFile(
                    'https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap',
                    [
                        'position' => \yii\web\View::POS_HEAD,
                    ]
                );

                // Add a class to body to indicate webfont mode
                $view->registerJs(
                    "document.documentElement.classList.add('fui-rating-emoji-webfont');",
                    \yii\web\View::POS_READY
                );
            }
        }
    }
}
