<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * Rating field for Formie - Provides star rating, emoji rating, and numeric rating field types
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;
use craft\utilities\ClearCaches;
use craft\web\UrlManager;
use craft\web\View;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\integrations\feedme\fields\Rating as FeedMeRatingField;
use lindemannrock\formieratingfield\models\Settings;
use lindemannrock\formieratingfield\services\StatisticsService;
use verbb\formie\elements\Submission;
use verbb\formie\events\RegisterFieldsEvent;
use verbb\formie\services\Fields;
use yii\base\Event;

/**
 * Formie Rating Field Plugin
 *
 * @author    LindemannRock
 * @package   FormieRatingField
 * @since     1.0.0
 *
 * @property-read Settings $settings
 * @property-read StatisticsService $statistics
 * @method Settings getSettings()
 */
class FormieRatingField extends Plugin
{
    /**
     * @var FormieRatingField|null Singleton plugin instance
     */
    public static ?FormieRatingField $plugin = null;

    /**
     * @var string Plugin schema version for migrations
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Whether the plugin exposes a control panel settings page
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Whether the plugin has its own CP section
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Set the alias for this module
        Craft::setAlias('@lindemannrock/formieratingfield', __DIR__);

        // Register services
        $this->setComponents([
            'statistics' => StatisticsService::class,
        ]);

        // Register console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'lindemannrock\\formieratingfield\\console\\controllers';
        }

        // Class alias removed - using direct namespace
        
        // Register view paths for Formie
        if (Craft::$app->request->getIsSiteRequest()) {
            Event::on(
                View::class,
                View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
                function(RegisterTemplateRootsEvent $event) {
                    $event->roots['formie-rating-field'] = __DIR__ . '/templates';
                }
            );
        }

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['formie-rating-field'] = __DIR__ . '/templates';
            }
        );

        // Register Twig extension for ratingHelper
        Craft::$app->view->registerTwigExtension(new \lindemannrock\formieratingfield\twigextensions\PluginNameExtension());

        // Register cache clearing option in utilities
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                $settings = $this->getSettings();
                $event->options[] = [
                    'key' => 'formie-rating-cache',
                    'label' => Craft::t('formie-rating-field', '{pluginName} Cache', [
                        'pluginName' => $settings->getDisplayName(),
                    ]),
                    'action' => function() {
                        $this->get('statistics')->clearAllCache();
                    },
                ];
            }
        );

        // Register our field
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELDS,
            function(RegisterFieldsEvent $event) {
                $event->fields[] = Rating::class;
            }
        );

        // Register Feed Me integration (only if Feed Me is installed)
        if (class_exists(FeedMeFields::class)) {
            Event::on(
                FeedMeFields::class,
                FeedMeFields::EVENT_REGISTER_FEED_ME_FIELDS,
                function(RegisterFeedMeFieldsEvent $event) {
                    $event->fields[] = FeedMeRatingField::class;
                }
            );
        }

        // Register CP URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['formie-rating-field'] = 'formie-rating-field/statistics/index';
                $event->rules['formie-rating-field/statistics'] = 'formie-rating-field/statistics/index';
                $event->rules['formie-rating-field/statistics/form/<formId:\d+>'] = 'formie-rating-field/statistics/form';
                $event->rules['formie-rating-field/statistics/form/<formId:\d+>/group/<groupValue>'] = 'formie-rating-field/statistics/group-detail';
                $event->rules['formie-rating-field/settings'] = 'formie-rating-field/settings/index';
                $event->rules['formie-rating-field/settings/general'] = 'formie-rating-field/settings/general';
                $event->rules['formie-rating-field/settings/interface'] = 'formie-rating-field/settings/interface';
            }
        );

        // Invalidate statistics cache when submissions are saved
        Event::on(
            Submission::class,
            Submission::EVENT_AFTER_SAVE,
            function(Event $event) {
                /** @var Submission $submission */
                $submission = $event->sender;

                // Clear cache for this form's statistics
                if ($submission->formId) {
                    $this->get('statistics')->clearCacheForForm($submission->formId);
                }
            }
        );

        // Invalidate statistics cache when submissions are deleted
        Event::on(
            Submission::class,
            Submission::EVENT_AFTER_DELETE,
            function(Event $event) {
                /** @var Submission $submission */
                $submission = $event->sender;

                // Clear cache for this form's statistics
                if ($submission->formId) {
                    $this->get('statistics')->clearCacheForForm($submission->formId);
                }
            }
        );

        // Set the plugin name from settings
        $settings = $this->getSettings();
        if (!empty($settings->pluginName)) {
            $this->name = $settings->pluginName;
        }

        Craft::info(
            'Formie Rating Field plugin loaded',
            __METHOD__
        );
    }
    
    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
    
    
    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect('formie-rating-field/settings');
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();

        if ($item) {
            $item['label'] = $this->getSettings()->getFullName();
            $item['icon'] = '@appicons/star.svg';

            $item['subnav'] = [
                'statistics' => [
                    'label' => Craft::t('formie-rating-field', 'Statistics'),
                    'url' => 'formie-rating-field/statistics',
                ],
                'settings' => [
                    'label' => Craft::t('formie-rating-field', 'Settings'),
                    'url' => 'formie-rating-field/settings',
                ],
            ];
        }

        return $item;
    }
}
