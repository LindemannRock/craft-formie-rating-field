<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;

/**
 * Formie Rating Field Settings Model
 *
 * @author    LindemannRock
 * @package   FormieRatingField
 * @since     1.0.0
 */
class Settings extends Model
{
    /**
     * @var string|null The public-facing name of the plugin
     */
    public ?string $pluginName = 'Formie Rating Field';
    
    /**
     * @var string Default rating type (star, emoji, nps)
     */
    public string $defaultRatingType = 'star';
    
    /**
     * @var string Default rating size (small, medium, large, xlarge)
     */
    public string $defaultRatingSize = 'medium';
    
    /**
     * @var int Default minimum rating value
     */
    public $defaultMinRating = 1;
    
    /**
     * @var int Default maximum rating value
     */
    public $defaultMaxRating = 5;
    
    /**
     * @var bool Allow half ratings by default (star type only)
     */
    public bool $defaultAllowHalfRatings = false;
    
    /**
     * @var bool Show selected value label by default
     */
    public bool $defaultShowSelectedLabel = false;
    
    /**
     * @var bool Show endpoint labels by default
     */
    public bool $defaultShowEndpointLabels = false;
    
    /**
     * @var string Default start label text
     */
    public string $defaultStartLabel = '';
    
    /**
     * @var string Default end label text
     */
    public string $defaultEndLabel = '';

    /**
     * @var string Emoji render mode (system, noto-color, noto-simple)
     */
    public string $emojiRenderMode = 'system';

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['pluginName', 'defaultRatingType', 'defaultRatingSize', 'defaultStartLabel', 'defaultEndLabel'], 'string'],
            [['defaultMinRating'], 'integer', 'min' => 0, 'max' => 1],
            [['defaultMaxRating'], 'integer', 'min' => 3, 'max' => 10],
            [['defaultAllowHalfRatings', 'defaultShowSelectedLabel', 'defaultShowEndpointLabels'], 'boolean'],
            [['defaultRatingType'], 'in', 'range' => ['star', 'emoji', 'nps']],
            [['defaultRatingSize'], 'in', 'range' => ['small', 'medium', 'large', 'xlarge']],
            [['defaultMinRating'], 'in', 'range' => [0, 1]],
            [['defaultMaxRating'], 'in', 'range' => [3, 4, 5, 6, 7, 8, 9, 10]],
            [['emojiRenderMode'], 'in', 'range' => ['system', 'noto-color', 'noto-simple', 'webfont']], // 'webfont' for backward compatibility
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'pluginName' => Craft::t('formie-rating-field', 'Plugin Name'),
            'defaultRatingType' => Craft::t('formie-rating-field', 'Default Rating Type'),
            'defaultRatingSize' => Craft::t('formie-rating-field', 'Default Rating Size'),
            'defaultMinRating' => Craft::t('formie-rating-field', 'Default Minimum Rating'),
            'defaultMaxRating' => Craft::t('formie-rating-field', 'Default Maximum Rating'),
            'defaultAllowHalfRatings' => Craft::t('formie-rating-field', 'Allow Half Ratings by Default'),
            'defaultShowSelectedLabel' => Craft::t('formie-rating-field', 'Show Selected Label by Default'),
            'defaultShowEndpointLabels' => Craft::t('formie-rating-field', 'Show Endpoint Labels by Default'),
            'defaultStartLabel' => Craft::t('formie-rating-field', 'Default Start Label'),
            'defaultEndLabel' => Craft::t('formie-rating-field', 'Default End Label'),
            'emojiRenderMode' => Craft::t('formie-rating-field', 'Emoji Render Mode'),
        ];
    }

    /**
     * Check if a setting is overridden in config file
     *
     * @param string $setting
     * @return bool
     */
    public function isOverridden(string $setting): bool
    {
        $configFileSettings = Craft::$app->getConfig()->getConfigFromFile('formie-rating-field');
        return isset($configFileSettings[$setting]);
    }
}