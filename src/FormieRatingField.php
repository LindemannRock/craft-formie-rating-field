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
use craft\events\RegisterUserPermissionsEvent;
use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\utilities\ClearCaches;
use craft\web\UrlManager;
use craft\web\View;
use lindemannrock\base\helpers\CpNavHelper;
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
     * @var bool Whether the plugin settings page is accessible when allowAdminChanges is false
     */
    public bool $hasReadOnlyCpSettings = true;

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
                // Only show cache option if user has permission to manage cache
                if (!Craft::$app->getUser()->checkPermission('formieRatingField:manageCache')) {
                    return;
                }

                $settings = $this->getSettings();
                $displayName = $settings->getDisplayName();

                $event->options[] = [
                    'key' => 'formie-rating-cache',
                    'label' => Craft::t('formie-rating-field', '{displayName} caches', [
                        'displayName' => $displayName,
                    ]),
                    'action' => function() {
                        $this->statistics->clearAllCache();
                    },
                ];
            }
        );

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $settings = $this->getSettings();
                $event->permissions[] = [
                    'heading' => $settings->getFullName(),
                    'permissions' => $this->getPluginPermissions(),
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

        // Invalidate this form's statistics cache when submissions are saved/deleted.
        // Runs in every cacheGenerationSchedule mode — clearCacheForForm() is scoped
        // to the form (per 2.1 fix), so the cost is bounded. A scheduled regeneration
        // job will still recompute on its cycle; this just prevents stale stats between runs.
        Event::on(
            Submission::class,
            Submission::EVENT_AFTER_SAVE,
            function(Event $event) {
                /** @var Submission $submission */
                $submission = $event->sender;

                if ($submission->formId) {
                    $this->statistics->clearCacheForForm($submission->formId);
                }
            }
        );

        Event::on(
            Submission::class,
            Submission::EVENT_AFTER_DELETE,
            function(Event $event) {
                /** @var Submission $submission */
                $submission = $event->sender;

                if ($submission->formId) {
                    $this->statistics->clearCacheForForm($submission->formId);
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
    }

    /**
     * Schedule initial cache generation job if not already queued
     */
    private function scheduleInitialCacheGeneration(): void
    {
        // Check if a job is already scheduled
        $existingJob = (new \craft\db\Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'formieratingfield'])
            ->andWhere(['like', 'job', 'GenerateCacheJob'])
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
    public function getReadOnlySettingsResponse(): mixed
    {
        return Craft::$app->controller->redirect('formie-rating-field/settings');
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $user = Craft::$app->getUser();

        if ($item) {
            $settings = $this->getSettings();

            $item['label'] = $settings->getFullName();

            $sections = $this->getCpSections($settings);
            $item['subnav'] = CpNavHelper::buildSubnav($user, $settings, $sections);

            // Hide from nav if no accessible subnav items
            if (empty($item['subnav'])) {
                return null;
            }
        }

        return $item;
    }

    /**
     * Get CP sections for nav + default route resolution
     *
     * @param Settings $settings
     * @return array
     * @since 3.5.0
     */
    public function getCpSections(Settings $settings): array
    {
        return [
            [
                'key' => 'statistics',
                'label' => Craft::t('formie-rating-field', 'Statistics'),
                'url' => 'formie-rating-field/statistics',
                'permissionsAll' => ['formieRatingField:viewStatistics'],
            ],
            [
                'key' => 'settings',
                'label' => Craft::t('formie-rating-field', 'Settings'),
                'url' => 'formie-rating-field/settings',
                'permissionsAll' => ['formieRatingField:manageSettings'],
            ],
        ];
    }

    /**
     * Get plugin permissions
     *
     * @return array
     * @since 3.5.0
     */
    private function getPluginPermissions(): array
    {
        return [
            // Statistics - grouped (view = parent, write/sub-actions nested)
            'formieRatingField:viewStatistics' => [
                'label' => Craft::t('formie-rating-field', 'View statistics'),
                'nested' => [
                    'formieRatingField:exportStatistics' => [
                        'label' => Craft::t('formie-rating-field', 'Export statistics'),
                    ],
                    'formieRatingField:refreshStatistics' => [
                        'label' => Craft::t('formie-rating-field', 'Refresh statistics'),
                    ],
                ],
            ],
            // Cache utility (generate-all / clear-all)
            'formieRatingField:manageCache' => [
                'label' => Craft::t('formie-rating-field', 'Manage cache'),
            ],
            // Settings
            'formieRatingField:manageSettings' => [
                'label' => Craft::t('formie-rating-field', 'Manage settings'),
            ],
        ];
    }
}
