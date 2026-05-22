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
use lindemannrock\base\traits\DateFormatSettingsTrait;
use lindemannrock\base\traits\DateRangeSettingsTrait;
use lindemannrock\base\traits\ExportFormatSettingsTrait;
use lindemannrock\base\traits\ItemsPerPageSettingsTrait;
use lindemannrock\base\traits\PluginNameSettingsTrait;
use lindemannrock\base\traits\SettingsConfigTrait;
use lindemannrock\base\traits\SettingsDisplayNameTrait;

/**
 * Formie Rating Field Settings Model
 *
 * @author    LindemannRock
 * @package   FormieRatingField
 * @since     1.0.0
 */
class Settings extends Model
{
    use DateFormatSettingsTrait;
    use DateRangeSettingsTrait;
    use ExportFormatSettingsTrait;
    use ItemsPerPageSettingsTrait;
    use PluginNameSettingsTrait;
    use SettingsConfigTrait;
    use SettingsDisplayNameTrait;

    /**
     * @var string The name of the plugin as it appears in the Control Panel menu
     */
    public string $pluginName = 'Formie Rating';

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
    public int $defaultMinRating = 1;

    /**
     * @var int Default maximum rating value
     */
    public int $defaultMaxRating = 5;

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
     * @var string Default emoji render mode (system, noto-color, noto-simple)
     */
    public string $defaultEmojiRenderMode = 'system';

    /**
     * @var bool Enable single emoji selection mode by default (emoji type only)
     */
    public bool $defaultSingleEmojiSelection = false;

    /**
     * @var int Maximum rows included in raw-responses exports. 0 = unlimited.
     *
     * Default 50,000 protects against PHP OOM when an admin exports the full
     * "Raw Responses" sheet on a high-volume form (each row hydrates a Submission
     * element + per-field-value rendering pipeline). Truncation is logged.
     */
    public int $maxExportRows = 50000;

    /**
     * @var string Cache storage method (file or redis)
     */
    public string $cacheStorageMethod = 'file';

    /**
     * @var string Schedule for automatic cache generation
     */
    public string $cacheGenerationSchedule = 'manual';

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return array_merge([
            [['defaultRatingType', 'defaultRatingSize', 'defaultStartLabel', 'defaultEndLabel'], 'string'],
            [['defaultMinRating'], 'integer', 'min' => 0, 'max' => 1],
            [['defaultMaxRating'], 'integer', 'min' => 3, 'max' => 10],
            [['maxExportRows'], 'integer', 'min' => 0, 'max' => 1000000],
            [['defaultAllowHalfRatings', 'defaultShowSelectedLabel', 'defaultShowEndpointLabels', 'defaultSingleEmojiSelection'], 'boolean'],
            [['defaultRatingType'], 'in', 'range' => ['star', 'emoji', 'nps']],
            [['defaultRatingSize'], 'in', 'range' => ['small', 'medium', 'large', 'xlarge']],
            [['defaultMinRating'], 'in', 'range' => [0, 1]],
            [['defaultMaxRating'], 'in', 'range' => [3, 4, 5, 6, 7, 8, 9, 10]],
            [['defaultEmojiRenderMode'], 'in', 'range' => ['system', 'noto-color', 'noto-simple', 'webfont']], // 'webfont' for backward compatibility
            [['cacheStorageMethod'], 'in', 'range' => ['file', 'redis']],
            [['cacheGenerationSchedule'], 'in', 'range' => ['manual', 'every3hours', 'every6hours', 'every12hours', 'daily', 'daily2am', 'twicedaily', 'weekly']],
        ], $this->pluginNameSettingsRules(), $this->dateFormatSettingsRules(), $this->dateRangeSettingsRules(), $this->exportFormatSettingsRules(), $this->itemsPerPageSettingsRules());
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge([
            'defaultRatingType' => Craft::t('formie-rating-field', 'Default Rating Type'),
            'defaultRatingSize' => Craft::t('formie-rating-field', 'Default Rating Size'),
            'defaultMinRating' => Craft::t('formie-rating-field', 'Default Minimum Rating'),
            'defaultMaxRating' => Craft::t('formie-rating-field', 'Default Maximum Rating'),
            'defaultAllowHalfRatings' => Craft::t('formie-rating-field', 'Allow Half Ratings by Default'),
            'defaultShowSelectedLabel' => Craft::t('formie-rating-field', 'Show Selected Label by Default'),
            'defaultShowEndpointLabels' => Craft::t('formie-rating-field', 'Show Endpoint Labels by Default'),
            'defaultStartLabel' => Craft::t('formie-rating-field', 'Default Start Label'),
            'defaultEndLabel' => Craft::t('formie-rating-field', 'Default End Label'),
            'defaultEmojiRenderMode' => Craft::t('formie-rating-field', 'Default Emoji Render Mode'),
            'defaultSingleEmojiSelection' => Craft::t('formie-rating-field', 'Single Emoji Selection by Default'),
            'maxExportRows' => Craft::t('formie-rating-field', 'Max Export Rows'),
            'cacheStorageMethod' => Craft::t('formie-rating-field', 'Cache Storage Method'),
            'cacheGenerationSchedule' => Craft::t('formie-rating-field', 'Cache Generation Schedule'),
        ], $this->pluginNameSettingsLabel(), $this->dateFormatSettingsLabels(), $this->dateRangeSettingsLabel(), $this->exportFormatSettingsLabels(), $this->itemsPerPageSettingsLabel());
    }

    /**
     * Plugin handle for config file resolution
     */
    protected static function pluginHandle(): string
    {
        return 'formie-rating-field';
    }
}
