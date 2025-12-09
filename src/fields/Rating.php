<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\fields;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use GraphQL\Type\Definition\Type;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\web\assets\field\RatingFieldAsset;
use verbb\formie\base\Field;
use verbb\formie\base\FieldInterface;
use verbb\formie\helpers\SchemaHelper;
use yii\db\Schema;

/**
 * Rating field
 *
 * @author LindemannRock
 * @since 1.0.0
 */
class Rating extends Field implements FieldInterface
{
    // Constants
    // =========================================================================

    public const RATING_TYPE_STAR = 'star';
    public const RATING_TYPE_EMOJI = 'emoji';
    public const RATING_TYPE_NPS = 'nps';

    // Properties
    // =========================================================================

    /**
     * @var string The rating type (star, emoji, nps)
     */
    public $ratingType;

    /**
     * @var string The rating size (small, medium, large, xlarge)
     */
    public $ratingSize;

    /**
     * @var int The minimum rating value
     */
    public $minValue;

    /**
     * @var int The maximum rating value
     */
    public $maxValue;

    /**
     * @var bool Whether to allow half ratings (star type only)
     */
    public $allowHalfRatings;

    /**
     * @var string Emoji render mode (system, noto-color, noto-simple)
     */
    public $emojiRenderMode;

    /**
     * @var bool Whether to show the selected value label
     */
    public $showSelectedLabel;

    /**
     * @var bool Whether to show endpoint labels
     */
    public $showEndpointLabels;

    /**
     * @var array Custom labels for each rating value
     */
    public $customLabels = [];

    /**
     * @var string The start label (for endpoint labels)
     */
    public $startLabel;

    /**
     * @var string The end label (for endpoint labels)
     */
    public $endLabel;

    /**
     * @var bool Whether to use single emoji selection mode (emoji type only)
     */
    public $singleEmojiSelection;

    /**
     * @var bool Enable Google Review prompt for high ratings
     */
    public $enableGoogleReview;

    /**
     * @var int|null Minimum rating threshold to trigger Google Review prompt
     */
    public $googleReviewThreshold;

    /**
     * @var string Field handle containing the Google Place ID
     */
    public $googlePlaceIdField;

    /**
     * @var string Custom message for high ratings
     */
    public $googleReviewMessageHigh;

    /**
     * @var string Custom message for medium ratings
     */
    public $googleReviewMessageMedium;

    /**
     * @var string Custom message for low ratings
     */
    public $googleReviewMessageLow;

    /**
     * @var string Google Review button label
     */
    public $googleReviewButtonLabel;

    /**
     * @var string Google Review button CSS classes
     */
    public $googleReviewButtonClass;

    /**
     * @var string Google Review URL template
     */
    public $googleReviewUrl;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Get plugin settings and apply defaults if properties are not set
        $plugin = FormieRatingField::$plugin;
        if ($plugin !== null) {
            $settings = $plugin->getSettings();
            // Apply defaults from plugin settings only if not already set
            if ($this->ratingType === null) {
                $this->ratingType = $settings->defaultRatingType;
            }
            if ($this->ratingSize === null) {
                $this->ratingSize = $settings->defaultRatingSize;
            }
            if ($this->minValue === null) {
                $this->minValue = $settings->defaultMinRating;
            }
            if ($this->maxValue === null) {
                $this->maxValue = $settings->defaultMaxRating;
            }
            if ($this->allowHalfRatings === null) {
                $this->allowHalfRatings = $settings->defaultAllowHalfRatings;
            }
            if ($this->emojiRenderMode === null) {
                $this->emojiRenderMode = $settings->defaultEmojiRenderMode;
            }
            if ($this->showSelectedLabel === null) {
                $this->showSelectedLabel = $settings->defaultShowSelectedLabel;
            }
            if ($this->showEndpointLabels === null) {
                $this->showEndpointLabels = $settings->defaultShowEndpointLabels;
            }
            if ($this->startLabel === null) {
                $this->startLabel = $settings->defaultStartLabel;
            }
            if ($this->endLabel === null) {
                $this->endLabel = $settings->defaultEndLabel;
            }
            if ($this->singleEmojiSelection === null) {
                $this->singleEmojiSelection = $settings->defaultSingleEmojiSelection;
            }
        }

        // Final fallbacks if plugin is not available
        if ($this->ratingType === null) {
            $this->ratingType = self::RATING_TYPE_STAR;
        }
        if ($this->ratingSize === null) {
            $this->ratingSize = 'medium';
        }
        if ($this->enableGoogleReview === null) {
            $this->enableGoogleReview = false;
        }
        if ($this->googleReviewThreshold === null) {
            $this->googleReviewThreshold = 9;
        }
        if ($this->googlePlaceIdField === null) {
            $this->googlePlaceIdField = '';
        }
        if ($this->googleReviewMessageHigh === null) {
            $this->googleReviewMessageHigh = '';
        }
        if ($this->googleReviewMessageMedium === null) {
            $this->googleReviewMessageMedium = '';
        }
        if ($this->googleReviewMessageLow === null) {
            $this->googleReviewMessageLow = '';
        }
        if ($this->googleReviewButtonLabel === null) {
            $this->googleReviewButtonLabel = '';
        }
        if ($this->googleReviewButtonClass === null) {
            $this->googleReviewButtonClass = '';
        }
        if ($this->googleReviewUrl === null) {
            $this->googleReviewUrl = '';
        }

        // Enforce NPS scale (must always be 0-10)
        if ($this->ratingType === self::RATING_TYPE_NPS) {
            $this->minValue = 0;
            $this->maxValue = 10;
        } else {
            // For non-NPS types, use defaults
            if ($this->minValue === null) {
                $this->minValue = 1;
            }
            if ($this->maxValue === null) {
                $this->maxValue = 5;
            }
        }
        if ($this->allowHalfRatings === null) {
            $this->allowHalfRatings = false;
        }
        if ($this->emojiRenderMode === null) {
            $this->emojiRenderMode = 'system';
        }
        if ($this->showSelectedLabel === null) {
            $this->showSelectedLabel = false;
        }
        if ($this->showEndpointLabels === null) {
            $this->showEndpointLabels = false;
        }
        if ($this->startLabel === null) {
            $this->startLabel = '';
        }
        if ($this->endLabel === null) {
            $this->endLabel = '';
        }
        if ($this->singleEmojiSelection === null) {
            $this->singleEmojiSelection = false;
        }
    }

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Rating');
    }

    /**
     * @inheritdoc
     */
    public static function getSvgIcon(): string
    {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>';
    }

    /**
     * @inheritdoc
     */
    public static function getSvgIconPath(): string
    {
        return '@formie-rating-templates/icon.svg';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return Schema::TYPE_DECIMAL . '(3,1)';
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float)$value;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultValue(): mixed
    {
        // Always return null to ensure no value is pre-selected
        return null;
    }

    /**
     * @inheritdoc
     */
    protected function cpInputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        // Get options for the field
        $options = [];

        foreach ($this->getRatingOptions() as $option) {
            $options[] = [
                'label' => $option['label'],
                'value' => (string)$option['value'], // Ensure value is string for comparison
            ];
        }

        // Use Craft's built-in select template
        return Craft::$app->getView()->renderTemplate('_includes/forms/select', [
            'name' => $this->handle,
            'value' => (string)$value, // Ensure value is string for comparison
            'options' => $options,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getValueAsString($value, ElementInterface $element = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Format the value based on rating type
        switch ($this->ratingType) {
            case self::RATING_TYPE_STAR:
                $stars = '';
                $fullStars = floor($value);
                $hasHalf = ($value - $fullStars) >= 0.5;

                // Add full stars
                for ($i = 0; $i < $fullStars; $i++) {
                    $stars .= '★';
                }

                // Add half star if needed
                if ($hasHalf) {
                    $stars .= '½';
                }

                // Add empty stars to show the scale
                $emptyStars = $this->maxValue - ceil($value);
                for ($i = 0; $i < $emptyStars; $i++) {
                    $stars .= '☆';
                }

                return $stars . ' (' . $value . '/' . $this->maxValue . ')';

            case self::RATING_TYPE_EMOJI:
                // Smart emoji selection based on range size
                $count = $this->maxValue - $this->minValue + 1;
                $emojis5 = ['😢', '😕', '😐', '😊', '😍'];
                $emojis8 = ['😢', '😕', '😐', '😊', '😍', '🤩', '🥰', '😎'];
                $emojis11 = ['😭', '😢', '😕', '😐', '😊', '😍', '🤩', '🥰', '😎', '🤗', '🥳'];
                $emojis = $count <= 5 ? $emojis5 : ($count <= 8 ? $emojis8 : $emojis11);

                $index = round(($value - $this->minValue) / ($this->maxValue - $this->minValue) * (count($emojis) - 1));
                $emoji = $emojis[$index] ?? '😐';
                return $emoji . ' (' . $value . '/' . $this->maxValue . ')';

            case self::RATING_TYPE_NPS:
                return $value . '/' . $this->maxValue;

            default:
                return (string)$value;
        }
    }

    /**
     * @inheritdoc
     */
    public function getValueAsHtml($value, ElementInterface $element = null): string
    {
        if ($value === null || $value === '') {
            return '<span style="color: #9ca3af;">—</span>';
        }

        return Craft::$app->getView()->renderTemplate('formie-rating-field/fields/rating/value', [
            'field' => $this,
            'value' => $value,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function defineValueForSummary($value, ElementInterface $element = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Return HTML string for rich display in submissions
        return $this->getValueAsHtml($value, $element);
    }

    /**
     * @inheritdoc
     */
    public static function getFrontEndInputTemplatePath(): string
    {
        return 'formie-rating-field/fields/rating/input';
    }

    /**
     * @inheritdoc
     */
    public function getPreviewInputHtml(): string
    {
        return '<div class="fui-rating-preview">
            <div v-if="field.settings.ratingType === \'star\'" style="display: flex; gap: 4px;">
                <template v-for="i in (parseInt(field.settings.maxValue) || 5) - (field.settings.minValue !== undefined && field.settings.minValue !== \'\' ? parseInt(field.settings.minValue) : 1) + 1">
                    <span :key="i" style="color: #f59e0b; font-size: 20px;">★</span>
                </template>
            </div>
            <div v-else-if="field.settings.ratingType === \'emoji\'" style="display: flex; flex-direction: column; align-items: start;">
                <div style="display: flex; gap: 4px;">
                    <template v-for="(emoji, index) in (((parseInt(field.settings.maxValue) || 5) - (field.settings.minValue !== undefined && field.settings.minValue !== \'\' ? parseInt(field.settings.minValue) : 0) + 1) <= 5 ? [\'😢\', \'😕\', \'😐\', \'😊\', \'😍\'] : (((parseInt(field.settings.maxValue) || 5) - (field.settings.minValue !== undefined && field.settings.minValue !== \'\' ? parseInt(field.settings.minValue) : 0) + 1) <= 8 ? [\'😢\', \'😕\', \'😐\', \'😊\', \'😍\', \'🤩\', \'🥰\', \'😎\'] : [\'😭\', \'😢\', \'😕\', \'😐\', \'😊\', \'😍\', \'🤩\', \'🥰\', \'😎\', \'🤗\', \'🥳\'])).slice(0, (parseInt(field.settings.maxValue) || 5) - (field.settings.minValue !== undefined && field.settings.minValue !== \'\' ? parseInt(field.settings.minValue) : 0) + 1)">
                        <span :key="index" style="font-size: 20px;">${ emoji }</span>
                    </template>
                </div>
                <div v-if="field.settings.singleEmojiSelection" style="margin-top: 6px; font-size: 11px; color: #6b7280; font-style: italic;">
                    Single selection mode
                </div>
            </div>
            <div v-else-if="field.settings.ratingType === \'nps\'" style="display: flex; gap: 4px;">
                <template v-for="n in (parseInt(field.settings.maxValue) || 10) - (field.settings.minValue !== undefined && field.settings.minValue !== \'\' ? parseInt(field.settings.minValue) : 1) + 1">
                    <span :key="n"
                          style="display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border: 2px solid #e5e7eb; background: white; color: #6b7280; border-radius: 4px; font-size: 12px;">
                        ${ (field.settings.minValue !== undefined && field.settings.minValue !== \'\' ? parseInt(field.settings.minValue) : 1) + n - 1 }
                    </span>
                </template>
            </div>
        </div>';
    }

    /**
     * @inheritdoc
     */
    public function getPreviewInputHtml_OLD(): string
    {
        try {
            // Simple debug
            Craft::info('Rating preview - Type: ' . $this->ratingType . ', Max: ' . $this->maxValue . ', Size: ' . $this->ratingSize, __METHOD__);

            $html = '<div class="fui-rating-preview" style="display: inline-block;">';

            // Use properties directly - Formie populates these when creating field instances
            $ratingType = $this->ratingType;
            $ratingSize = $this->ratingSize;
            $minValue = (int)$this->minValue;
            $maxValue = (int)$this->maxValue;

            // Show debug info
            $html .= '<small style="display: block; margin-bottom: 5px; color: #999;">Type: ' . $ratingType . ' | Max: ' . $maxValue . '</small>';

            // Size mapping for display
            $sizeStyles = [
                'small' => '16px',
                'medium' => '20px',
                'large' => '24px',
                'xlarge' => '32px',
            ];
            $fontSize = $sizeStyles[$ratingSize] ?? '20px';

            if ($ratingType == self::RATING_TYPE_STAR || $ratingType == 'star') {
                $html .= '<div style="display: flex; gap: 4px; align-items: center;">';
                $max = min($maxValue, 10); // Limit to 10 for preview
                $mid = ceil(($maxValue + $minValue) / 2);
                for ($i = $minValue; $i <= $max; $i++) {
                    $html .= '<span style="color: #f59e0b; font-size: ' . $fontSize . ';">' . ($i <= $mid ? '★' : '☆') . '</span>';
                }
                $html .= '</div>';
            } elseif ($ratingType == self::RATING_TYPE_EMOJI || $ratingType == 'emoji') {
                $html .= '<div style="display: flex; gap: 4px; align-items: center;">';
                // Smart emoji selection based on range size
                $count = $maxValue - $minValue + 1;
                $emojis5 = ['😢', '😕', '😐', '😊', '😍'];
                $emojis8 = ['😢', '😕', '😐', '😊', '😍', '🤩', '🥰', '😎'];
                $emojis11 = ['😭', '😢', '😕', '😐', '😊', '😍', '🤩', '🥰', '😎', '🤗', '🥳'];
                $emojis = $count <= 5 ? $emojis5 : ($count <= 8 ? $emojis8 : $emojis11);

                $mid = (int)floor($count / 2);
                for ($i = 0; $i < $count; $i++) {
                    $opacity = $i === $mid ? '1' : '0.5';
                    $emoji = $emojis[$i] ?? '😐';
                    $html .= '<span style="font-size: ' . $fontSize . '; opacity: ' . $opacity . ';">' . $emoji . '</span>';
                }
                $html .= '</div>';
            } elseif ($ratingType == self::RATING_TYPE_NPS || $ratingType == 'nps') {
                $html .= '<div style="display: flex; gap: 4px; align-items: center;">';
                $max = min($maxValue, 11); // Limit to 11 for preview
                $mid = (int)ceil(($maxValue + $minValue) / 2);
                // Scale box size based on rating size
                $boxSize = $ratingSize == 'small' ? '18px' : ($ratingSize == 'large' ? '26px' : ($ratingSize == 'xlarge' ? '32px' : '22px'));
                $textSize = $ratingSize == 'small' ? '11px' : ($ratingSize == 'large' ? '14px' : ($ratingSize == 'xlarge' ? '16px' : '12px'));
                for ($i = $minValue; $i <= $max; $i++) {
                    $selected = $i === $mid;
                    $borderColor = $selected ? '#2d5016' : '#e5e7eb';
                    $bgColor = $selected ? '#2d5016' : 'white';
                    $textColor = $selected ? 'white' : '#6b7280';
                    $html .= '<span style="display: inline-flex; align-items: center; justify-content: center; width: ' . $boxSize . '; height: ' . $boxSize . '; border: 2px solid ' . $borderColor . '; background: ' . $bgColor . '; color: ' . $textColor . '; border-radius: 4px; font-size: ' . $textSize . '; font-weight: 500;">' . $i . '</span>';
                }
                $html .= '</div>';
            } else {
                // Fallback if type is not recognized
                $html .= '<div style="color: #6b7280;">Rating field</div>';
            }

            // Show endpoint labels if enabled
            if ($this->showEndpointLabels && ($this->startLabel || $this->endLabel)) {
                $html .= '<div style="display: flex; justify-content: space-between; margin-top: 4px; font-size: 12px; color: #6b7280;">';
                $html .= '<span>' . htmlspecialchars($this->startLabel ?: '') . '</span>';
                $html .= '<span>' . htmlspecialchars($this->endLabel ?: '') . '</span>';
                $html .= '</div>';
            }

            $html .= '</div>';

            return $html;
        } catch (\Exception $e) {
            // Return a simple fallback if anything goes wrong
            return '<div class="fui-rating-preview">Rating field</div>';
        }
    }

    /**
     * @inheritdoc
     */
    public function getInputTypeName(): string
    {
        return 'rating';
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('formie-rating-field/fields/rating/settings', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getFieldDefaults(): array
    {
        // Get defaults from plugin settings
        $defaults = [
            'ratingType' => self::RATING_TYPE_STAR,
            'ratingSize' => 'medium',
            'minValue' => 1,
            'maxValue' => 5,
            'allowHalfRatings' => false,
            'emojiRenderMode' => 'system',
            'showSelectedLabel' => false,
            'showEndpointLabels' => false,
            'customLabels' => [],
            'startLabel' => '',
            'endLabel' => '',
            'singleEmojiSelection' => false,
        ];

        $plugin = FormieRatingField::$plugin;
        if ($plugin !== null) {
            $settings = $plugin->getSettings();
            $defaults = [
                'ratingType' => $settings->defaultRatingType,
                'ratingSize' => $settings->defaultRatingSize,
                'minValue' => $settings->defaultMinRating,
                'maxValue' => $settings->defaultMaxRating,
                'allowHalfRatings' => $settings->defaultAllowHalfRatings,
                'emojiRenderMode' => $settings->defaultEmojiRenderMode,
                'showSelectedLabel' => $settings->defaultShowSelectedLabel,
                'showEndpointLabels' => $settings->defaultShowEndpointLabels,
                'customLabels' => [],
                'startLabel' => $settings->defaultStartLabel,
                'endLabel' => $settings->defaultEndLabel,
                'singleEmojiSelection' => $settings->defaultSingleEmojiSelection,
            ];
        }

        return $defaults;
    }

    /**
     * @inheritdoc
     */
    public function getConfigJson(): string
    {
        return Json::encode([
            'ratingType' => $this->ratingType,
            'ratingSize' => $this->ratingSize,
            'minValue' => $this->minValue,
            'maxValue' => $this->maxValue,
            'allowHalfRatings' => $this->allowHalfRatings,
            'showSelectedLabel' => $this->showSelectedLabel,
            'showEndpointLabels' => $this->showEndpointLabels,
            'singleEmojiSelection' => $this->singleEmojiSelection,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['minValue', 'maxValue'], 'required'];
        $rules[] = [['minValue', 'maxValue'], 'integer'];
        $rules[] = [['minValue'], 'compare', 'compareAttribute' => 'maxValue', 'operator' => '<'];
        $rules[] = [['ratingType'], 'in', 'range' => [self::RATING_TYPE_STAR, self::RATING_TYPE_EMOJI, self::RATING_TYPE_NPS]];
        $rules[] = [['ratingSize'], 'in', 'range' => ['small', 'medium', 'large', 'xlarge']];

        return $rules;
    }


    /**
     * Get the rating options based on min/max values and custom labels
     */
    public function getRatingOptions(): array
    {
        $options = [];

        // Normalize custom labels to associative array format
        $normalizedLabels = $this->getNormalizedCustomLabels();

        // Only allow half ratings for star type, not for emoji or NPS
        $step = ($this->allowHalfRatings && $this->ratingType === self::RATING_TYPE_STAR) ? 0.5 : 1;

        for ($i = $this->minValue; $i <= $this->maxValue; $i += $step) {
            // For NPS, always use the numeric value as the label
            if ($this->ratingType === self::RATING_TYPE_NPS) {
                $label = (string)$i;
            } else {
                $label = $normalizedLabels[$i] ?? (string)$i;
            }

            $options[] = [
                'value' => $i,
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * Normalize custom labels to associative array format
     * Handles both table format from Formie and associative format
     */
    private function getNormalizedCustomLabels(): array
    {
        if (!is_array($this->customLabels) || empty($this->customLabels)) {
            return [];
        }

        // Check if it's table format (array of rows with 'value' and 'label' keys)
        if (isset($this->customLabels[0]) && is_array($this->customLabels[0])) {
            $normalized = [];
            foreach ($this->customLabels as $row) {
                $value = $row['value'] ?? null;
                $label = $row['label'] ?? null;

                if ($value !== null && $value !== '' && $label !== null && $label !== '') {
                    $normalized[(int)$value] = $label;
                }
            }
            return $normalized;
        }

        // Already in associative format
        return $this->customLabels;
    }

    /**
     * @inheritdoc
     */
    public function getContentGqlType(): Type|array
    {
        return Type::float();
    }

    /**
     * @inheritdoc
     */
    public static function getEmailTemplatePath(): string
    {
        return 'formie-rating-field/fields/rating/email';
    }

    /**
     * @inheritdoc
     */
    public function defineGeneralSchema(): array
    {
        return [
            SchemaHelper::labelField(),
            SchemaHelper::selectField([
                'label' => Craft::t('formie', 'Rating Type'),
                'help' => Craft::t('formie', 'Choose the type of rating display. For NPS, set range to 0-10.'),
                'name' => 'ratingType',
                'options' => [
                    ['label' => Craft::t('formie', 'Star Rating'), 'value' => self::RATING_TYPE_STAR],
                    ['label' => Craft::t('formie', 'Emoji Rating'), 'value' => self::RATING_TYPE_EMOJI],
                    ['label' => Craft::t('formie', 'NPS (Number) Rating'), 'value' => self::RATING_TYPE_NPS],
                ],
            ]),
            SchemaHelper::selectField([
                'label' => Craft::t('formie', 'Emoji Render Mode'),
                'help' => Craft::t('formie', 'Choose how emoji ratings are rendered.'),
                'name' => 'emojiRenderMode',
                'options' => [
                    ['label' => Craft::t('formie', 'System Emojis (Native platform emojis)'), 'value' => 'system'],
                    ['label' => Craft::t('formie', 'Noto Color Emoji (Detailed, colorful style)'), 'value' => 'noto-color'],
                    ['label' => Craft::t('formie', 'Noto Emoji (Simple, clean style)'), 'value' => 'noto-simple'],
                ],
                'if' => '$get(ratingType).value == emoji',
            ]),
            SchemaHelper::variableTextField([
                'label' => Craft::t('formie', 'Placeholder'),
                'help' => Craft::t('formie', 'The text to show as a placeholder.'),
                'name' => 'placeholder',
                'variables' => 'plainTextVariables',
            ]),
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineSettingsSchema(): array
    {
        return [
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Required Field'),
                'help' => Craft::t('formie', 'Whether this field should be required when filling out the form.'),
                'name' => 'required',
            ]),
            SchemaHelper::textField([
                'label' => Craft::t('formie', 'Error Message'),
                'help' => Craft::t('formie', 'When validating the form, show this message if an error occurs. Leave empty to retain the default message.'),
                'name' => 'errorMessage',
                'if' => '$get(required).value',
            ]),
            SchemaHelper::selectField([
                'label' => Craft::t('formie', 'Size'),
                'help' => Craft::t('formie', 'Choose the size of the rating elements.'),
                'name' => 'ratingSize',
                'options' => [
                    ['label' => Craft::t('formie', 'Small'), 'value' => 'small'],
                    ['label' => Craft::t('formie', 'Medium'), 'value' => 'medium'],
                    ['label' => Craft::t('formie', 'Large'), 'value' => 'large'],
                    ['label' => Craft::t('formie', 'Extra Large'), 'value' => 'xlarge'],
                ],
            ]),
            SchemaHelper::selectField([
                'label' => Craft::t('formie', 'Minimum Value'),
                'help' => Craft::t('formie', 'Set the minimum rating value. NPS is always 0-10.'),
                'name' => 'minValue',
                'options' => [
                    ['label' => '0', 'value' => 0],
                    ['label' => '1', 'value' => 1],
                ],
                'if' => '$get(ratingType).value != nps',
            ]),
            SchemaHelper::selectField([
                'label' => Craft::t('formie', 'Maximum Value'),
                'help' => Craft::t('formie', 'Set the maximum rating value. NPS is always 0-10.'),
                'name' => 'maxValue',
                'options' => [
                    ['label' => '3', 'value' => 3],
                    ['label' => '4', 'value' => 4],
                    ['label' => '5', 'value' => 5],
                    ['label' => '6', 'value' => 6],
                    ['label' => '7', 'value' => 7],
                    ['label' => '8', 'value' => 8],
                    ['label' => '9', 'value' => 9],
                    ['label' => '10', 'value' => 10],
                ],
                'if' => '$get(ratingType).value != nps',
            ]),
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Allow Half Ratings'),
                'help' => Craft::t('formie', 'Allow users to select half-star ratings (star type only).'),
                'name' => 'allowHalfRatings',
                'if' => '$get(ratingType).value == star',
            ]),
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Show Selected Label'),
                'help' => Craft::t('formie', 'Display the selected rating value as a text label.'),
                'name' => 'showSelectedLabel',
            ]),
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Show Endpoint Labels'),
                'help' => Craft::t('formie', 'Display labels at the start and end of the rating scale.'),
                'name' => 'showEndpointLabels',
            ]),
            SchemaHelper::variableTextField([
                'label' => Craft::t('formie', 'Start Label'),
                'help' => Craft::t('formie', 'Label for the lowest rating value.'),
                'name' => 'startLabel',
                'if' => '$get(showEndpointLabels).value',
            ]),
            SchemaHelper::variableTextField([
                'label' => Craft::t('formie', 'End Label'),
                'help' => Craft::t('formie', 'Label for the highest rating value.'),
                'name' => 'endLabel',
                'if' => '$get(showEndpointLabels).value',
            ]),
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie', 'Single Emoji Selection'),
                'help' => Craft::t('formie', 'Highlight only the selected emoji instead of cumulative selection. When enabled, a custom label will display beneath the selected emoji.'),
                'name' => 'singleEmojiSelection',
                'if' => '$get(ratingType).value == emoji',
            ]),
            SchemaHelper::tableField([
                'label' => Craft::t('formie', 'Custom Labels'),
                'help' => Craft::t('formie', 'Define custom labels for each rating value (e.g., Value: 1, Label: Terrible). Labels will display beneath selected emoji.'),
                'name' => 'customLabels',
                'validation' => 'optional',
                'generateValue' => false,
                'if' => '$get(singleEmojiSelection).value',
                'newRowDefaults' => [
                    'value' => '',
                    'label' => '',
                ],
                'columns' => [
                    [
                        'type' => 'value',
                        'label' => Craft::t('formie', 'Value'),
                        'class' => 'code singleline-cell textual',
                    ],
                    [
                        'type' => 'label',
                        'label' => Craft::t('formie', 'Label'),
                        'class' => 'singleline-cell textual',
                    ],
                ],
            ]),
            SchemaHelper::lightswitchField([
                'label' => Craft::t('formie-rating-field', 'Enable Google Review Prompt'),
                'help' => Craft::t('formie-rating-field', 'Show a Google Review link when users give high ratings. This will override the form\'s success message. Only one rating field per form should have this enabled.'),
                'name' => 'enableGoogleReview',
            ]),
            SchemaHelper::numberField([
                'label' => Craft::t('formie-rating-field', 'Rating Threshold'),
                'help' => Craft::t('formie-rating-field', 'Minimum rating value to show the Google Review prompt (e.g., 9 for NPS).'),
                'name' => 'googleReviewThreshold',
                'value' => 9,
                'if' => '$get(enableGoogleReview).value',
            ]),
            SchemaHelper::textField([
                'label' => Craft::t('formie-rating-field', 'Google Place ID Field Handle'),
                'help' => Craft::t('formie-rating-field', 'Handle of the field containing the Google Place ID.'),
                'name' => 'googlePlaceIdField',
                'placeholder' => 'googlePlaceId',
                'required' => true,
                'if' => '$get(enableGoogleReview).value',
            ]),
            SchemaHelper::textareaField([
                'label' => Craft::t('formie-rating-field', 'High Rating Message'),
                'help' => Craft::t('formie-rating-field', 'Message shown when rating is above threshold.'),
                'name' => 'googleReviewMessageHigh',
                'placeholder' => 'Thank you for the excellent rating! Would you like to share your experience on Google?',
                'if' => '$get(enableGoogleReview).value',
            ]),
            SchemaHelper::textareaField([
                'label' => Craft::t('formie-rating-field', 'Medium Rating Message'),
                'help' => Craft::t('formie-rating-field', 'Message shown for medium ratings.'),
                'name' => 'googleReviewMessageMedium',
                'placeholder' => 'Thank you for your feedback!',
                'if' => '$get(enableGoogleReview).value',
            ]),
            SchemaHelper::textareaField([
                'label' => Craft::t('formie-rating-field', 'Low Rating Message'),
                'help' => Craft::t('formie-rating-field', 'Message shown for low ratings.'),
                'name' => 'googleReviewMessageLow',
                'placeholder' => 'Thank you for your feedback. We will use it to improve our service.',
                'if' => '$get(enableGoogleReview).value',
            ]),
            SchemaHelper::textField([
                'label' => Craft::t('formie-rating-field', 'Google Review URL Template'),
                'help' => Craft::t('formie-rating-field', 'URL template for Google Reviews. Use {googlePlaceId} as placeholder - it will be replaced with the actual value from that field.'),
                'name' => 'googleReviewUrl',
                'value' => 'https://search.google.com/local/writereview?placeid={googlePlaceId}',
                'placeholder' => 'https://search.google.com/local/writereview?placeid={googlePlaceId}',
                'if' => '$get(enableGoogleReview).value',
            ]),
            SchemaHelper::textField([
                'label' => Craft::t('formie-rating-field', 'Review Button Label'),
                'help' => Craft::t('formie-rating-field', 'Text displayed on the Google Review button.'),
                'name' => 'googleReviewButtonLabel',
                'placeholder' => 'Review on Google',
                'if' => '$get(enableGoogleReview).value',
            ]),
            SchemaHelper::includeInEmailField(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineAppearanceSchema(): array
    {
        return [
            SchemaHelper::visibility(),
            SchemaHelper::labelPosition($this),
            SchemaHelper::instructions(),
            SchemaHelper::instructionsPosition($this),
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineAdvancedSchema(): array
    {
        return [
            SchemaHelper::handleField(),
            SchemaHelper::cssClasses(),
            SchemaHelper::containerAttributesField(),
            SchemaHelper::inputAttributesField(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineConditionsSchema(): array
    {
        return [
            SchemaHelper::enableConditionsField(),
            SchemaHelper::conditionsField(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'ratingType';
        $attributes[] = 'ratingSize';
        $attributes[] = 'minValue';
        $attributes[] = 'maxValue';
        $attributes[] = 'allowHalfRatings';
        $attributes[] = 'showSelectedLabel';
        $attributes[] = 'showEndpointLabels';
        $attributes[] = 'customLabels';
        $attributes[] = 'startLabel';
        $attributes[] = 'endLabel';
        $attributes[] = 'singleEmojiSelection';
        $attributes[] = 'enableGoogleReview';
        $attributes[] = 'googleReviewThreshold';
        $attributes[] = 'googlePlaceIdField';
        $attributes[] = 'googleReviewMessageHigh';
        $attributes[] = 'googleReviewMessageMedium';
        $attributes[] = 'googleReviewMessageLow';
        $attributes[] = 'googleReviewButtonLabel';
        $attributes[] = 'googleReviewButtonClass';
        $attributes[] = 'googleReviewUrl';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getFormBuilderConfig(): array
    {
        $config = parent::getFormBuilderConfig();

        // Ensure our custom properties are included in the form builder config
        $config['ratingType'] = $this->ratingType;
        $config['ratingSize'] = $this->ratingSize;
        $config['minValue'] = $this->minValue;
        $config['maxValue'] = $this->maxValue;
        $config['singleEmojiSelection'] = $this->singleEmojiSelection;
        $config['settings']['ratingType'] = $this->ratingType;
        $config['settings']['ratingSize'] = $this->ratingSize;
        $config['settings']['minValue'] = $this->minValue;
        $config['settings']['maxValue'] = $this->maxValue;
        $config['settings']['singleEmojiSelection'] = $this->singleEmojiSelection;

        return $config;
    }

    /**
     * @inheritdoc
     */
    public function getFrontEndJsModules(): ?array
    {
        // Register the asset bundle to ensure CSS is also loaded
        Craft::$app->getView()->registerAssetBundle(RatingFieldAsset::class);

        // Get the published URL using the asset bundle's source path
        $assetPath = dirname((new \ReflectionClass(RatingFieldAsset::class))->getFileName());
        $publishedUrl = Craft::$app->getAssetManager()->getPublishedUrl($assetPath, true);

        $modules = [
            'src' => $publishedUrl . '/' . (Craft::$app->getConfig()->getGeneral()->devMode ? 'rating.js' : 'rating.min.js'),
            'module' => 'FormieRating',
            'settings' => [
                'ratingType' => $this->ratingType,
                'ratingSize' => $this->ratingSize,
                'minValue' => $this->minValue,
                'maxValue' => $this->maxValue,
                'allowHalfRatings' => $this->allowHalfRatings,
                'showSelectedLabel' => $this->showSelectedLabel,
                'showEndpointLabels' => $this->showEndpointLabels,
                'singleEmojiSelection' => $this->singleEmojiSelection,
            ],
        ];

        // Add Google Review integration if enabled
        if ($this->enableGoogleReview) {
            $this->registerGoogleReviewJs();
        }

        return $modules;
    }

    /**
     * Register Google Review integration JavaScript
     */
    private function registerGoogleReviewJs(): void
    {
        $js = $this->getGoogleReviewJs();

        if ($js) {
            Craft::$app->getView()->registerJs($js, \yii\web\View::POS_END);
        }
    }

    /**
     * Generate Google Review integration JavaScript
     */
    private function getGoogleReviewJs(): string
    {
        if (!$this->enableGoogleReview || !$this->googlePlaceIdField) {
            return '';
        }

        $fieldHandle = $this->handle;
        $threshold = $this->googleReviewThreshold ?? 9;
        $placeIdField = $this->googlePlaceIdField;

        // Get messages and URL with defaults
        $messageHigh = $this->googleReviewMessageHigh ?: Craft::t('formie-rating-field', 'Thank you for the excellent rating! 🎉 We would love if you could share your experience with others.');
        $messageMedium = $this->googleReviewMessageMedium ?: Craft::t('formie-rating-field', 'Thank you for your feedback!');
        $messageLow = $this->googleReviewMessageLow ?: Craft::t('formie-rating-field', 'Thank you for your feedback. We will use it to improve our service.');
        $buttonLabel = $this->googleReviewButtonLabel ?: Craft::t('formie-rating-field', 'Review on Google');
        $reviewUrl = $this->googleReviewUrl ?: 'https://search.google.com/local/writereview?placeid={googlePlaceId}';

        // Translate using Formie's category (like field labels)
        $messageHigh = Craft::t('formie', $messageHigh);
        $messageMedium = Craft::t('formie', $messageMedium);
        $messageLow = Craft::t('formie', $messageLow);
        $buttonLabel = Craft::t('formie', $buttonLabel);

        // Escape for JavaScript
        $messageHigh = addslashes($messageHigh);
        $messageMedium = addslashes($messageMedium);
        $messageLow = addslashes($messageLow);
        $buttonLabel = addslashes($buttonLabel);
        $reviewUrl = addslashes($reviewUrl);

        return <<<JS
(function() {
    document.addEventListener('onFormieInit', function(event) {
        const \$form = event.detail.\$form;

        let capturedRating = 0;
        let capturedPlaceId = '';

        \$form.addEventListener('onBeforeFormieSubmit', function() {
            const ratingSelect = \$form.querySelector('select[name="fields[{$fieldHandle}]"]');
            const placeIdInput = \$form.querySelector('input[name="fields[{$placeIdField}]"]');

            capturedRating = ratingSelect ? parseFloat(ratingSelect.value) : 0;
            capturedPlaceId = placeIdInput ? placeIdInput.value : '';
        });

        \$form.addEventListener('onAfterFormieSubmit', function() {
            setTimeout(function() {
                const successMessage = document.querySelector('[data-fui-alert-success]');

                if (!successMessage) {
                    return;
                }

                if (capturedRating >= {$threshold} && capturedPlaceId) {
                    // Get Formie's submit button classes from the form
                    const submitBtn = \$form.querySelector('[data-submit-action]');
                    const btnClasses = submitBtn ? submitBtn.className : 'fui-btn';

                    // Build review URL by replacing placeholder with actual Place ID
                    const reviewUrl = '{$reviewUrl}'.replace('{googlePlaceId}', capturedPlaceId).replace('{placeId}', capturedPlaceId);

                    successMessage.innerHTML = `
                        <p>{$messageHigh}</p>
                        <p style="margin-top: 16px;">
                            <a href="\${reviewUrl}"
                               target="_blank"
                               class="\${btnClasses}">
                                {$buttonLabel}
                            </a>
                        </p>
                    `;
                } else if (capturedRating >= Math.floor({$threshold} * 0.7)) {
                    successMessage.innerHTML = '<p>{$messageMedium}</p>';
                } else if (capturedRating > 0) {
                    successMessage.innerHTML = '<p>{$messageLow}</p>';
                }
            }, 300);
        });
    });
})();
JS;
    }
}
