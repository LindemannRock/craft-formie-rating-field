<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\formieratingfield\widgets;

use Craft;
use craft\base\Widget;
use lindemannrock\formieratingfield\FormieRatingField;

/**
 * Formie Rating Field statistics dashboard widget.
 *
 * @since 3.21.0
 */
class RatingStatisticsWidget extends Widget
{
    use SiteFilterTrait;

    /**
     * @var int Number of forms to show
     */
    public int $limit = 5;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['limit'], 'integer', 'min' => 3, 'max' => 20];
        $rules[] = [['siteId'], 'in', 'range' => array_column($this->siteOptions(), 'value')];
        $rules[] = [['limit'], 'default', 'value' => 5];
        $rules[] = [['siteId'], 'default', 'value' => 'all'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        $pluginName = FormieRatingField::$plugin->getSettings()->getFullName();

        return $pluginName . ' - ' . Craft::t('formie-rating-field', 'Statistics');
    }

    /**
     * @inheritdoc
     */
    public static function isSelectable(): bool
    {
        return parent::isSelectable()
            && Craft::$app->getUser()->checkPermission('formieRatingField:viewStatistics');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return '@lindemannrock/formieratingfield/icon-mask.svg';
    }

    /**
     * @inheritdoc
     */
    public static function maxColspan(): ?int
    {
        return 2;
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): ?string
    {
        return self::displayName();
    }

    /**
     * @inheritdoc
     */
    public function getSubtitle(): ?string
    {
        return Craft::t('formie-rating-field', 'Rating Statistics');
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('formie-rating-field/widgets/rating-statistics/settings', [
            'widget' => $this,
            'siteOptions' => $this->siteOptions(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        if (!Craft::$app->getUser()->checkPermission('formieRatingField:viewStatistics')) {
            return Craft::$app->getView()->renderTemplate('lindemannrock-base/_components/dashboard-widget-empty', [
                'title' => Craft::t('formie-rating-field', 'No forms with rating fields found.'),
            ]);
        }

        $forms = FormieRatingField::$plugin->statistics->getFormsWithRatingFields($this->effectiveSiteId());

        usort($forms, static fn(array $a, array $b): int =>
            ($b['totalSubmissions'] ?? 0) <=> ($a['totalSubmissions'] ?? 0)
        );

        return Craft::$app->getView()->renderTemplate('formie-rating-field/widgets/rating-statistics/body', [
            'forms' => array_slice($forms, 0, $this->limit),
            'siteId' => $this->siteId,
        ]);
    }
}
