<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\formieratingfield\web\assets\field;

use craft\web\AssetBundle;

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
        // Define the path to the built assets folder through the Composer alias
        $this->sourcePath = '@lindemannrock/formieratingfield/web/assets/field/dist';

        // Define which files to include
        $this->css = [
            'css/rating.css',
        ];

        $this->js = [
            'js/rating.js',
        ];

        parent::init();
    }
}
