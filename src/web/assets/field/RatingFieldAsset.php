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
 * This asset bundle publishes the rating field assets and registers its CSS.
 * Formie owns loading the JavaScript URL returned by getFrontEndJsModules().
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

        parent::init();
    }
}
