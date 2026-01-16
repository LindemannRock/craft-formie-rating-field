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
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\UrlManager;
use craft\web\View;
use lindemannrock\base\helpers\PluginHelper;
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

        // Bootstrap the base plugin helper (registers ratingHelper Twig global)
        PluginHelper::bootstrap($this, 'ratingHelper');

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

        // Register cache clearing option in utilities
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            function(RegisterCacheOptionsEvent $event) {
                $settings = $this->getSettings();
                $displayName = $settings->getDisplayName();

                $event->options[] = [
                    'key' => 'formie-rating-cache',
                    'label' => Craft::t('formie-rating-field', '{displayName} caches', [
                        'displayName' => $displayName,
                    ]),
                    'action' => function() {
                        $this->get('statistics')->clearAllCache();
                    },
                ];
            }
        );

        // Register utility
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = \lindemannrock\formieratingfield\utilities\RatingUtility::class;
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
                $event->rules['formie-rating-field/statistics/export-group'] = 'formie-rating-field/statistics/export-group';
                $event->rules['formie-rating-field/cache/generate-all'] = 'formie-rating-field/cache/generate-all';
                $event->rules['formie-rating-field/cache/clear-all'] = 'formie-rating-field/cache/clear-all';
                $event->rules['formie-rating-field/settings'] = 'formie-rating-field/settings/index';
                $event->rules['formie-rating-field/settings/general'] = 'formie-rating-field/settings/general';
                $event->rules['formie-rating-field/settings/interface'] = 'formie-rating-field/settings/interface';
                $event->rules['formie-rating-field/settings/cache'] = 'formie-rating-field/settings/cache';
            }
        );

        // Invalidate statistics cache when submissions are saved/deleted
        // Only if NOT using scheduled generation (to avoid conflicts)
        Event::on(
            Submission::class,
            Submission::EVENT_AFTER_SAVE,
            function(Event $event) {
                $settings = $this->getSettings();

                // Skip auto-invalidation if using scheduled cache generation
                if ($settings->cacheGenerationSchedule !== 'manual') {
                    return;
                }

                /** @var Submission $submission */
                $submission = $event->sender;

                // Clear cache for this form's statistics
                if ($submission->formId) {
                    $this->get('statistics')->clearCacheForForm($submission->formId);
                }
            }
        );

        Event::on(
            Submission::class,
            Submission::EVENT_AFTER_DELETE,
            function(Event $event) {
                $settings = $this->getSettings();

                // Skip auto-invalidation if using scheduled cache generation
                if ($settings->cacheGenerationSchedule !== 'manual') {
                    return;
                }

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

        // Schedule cache generation job if enabled and not already queued
        if ($settings->cacheGenerationSchedule !== 'manual') {
            $this->scheduleInitialCacheGeneration();
        }

        Craft::info(
            'Formie Rating Field plugin loaded',
            __METHOD__
        );
    }

    /**
     * Schedule initial cache generation job if not already queued
     */
    private function scheduleInitialCacheGeneration(): void
    {
        // Check if a job is already scheduled
        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'GenerateCacheJob'])
            ->andWhere(['<=', 'timePushed', time() + 86400])
            ->exists();

        if (!$existingJob) {
            $job = new \lindemannrock\formieratingfield\jobs\GenerateCacheJob([
                'reschedule' => true,
            ]);

            // Calculate delay until next scheduled run time
            $delay = $job->calculateNextRunDelay();

            Craft::$app->getQueue()->delay($delay)->push($job);

            Craft::info('Scheduled initial cache generation job. Delay: ' . $delay . 's, Next run: ' . date('Y-m-d H:i:s', time() + $delay), __METHOD__);
        }
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
