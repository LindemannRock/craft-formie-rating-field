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
use craft\db\Query;
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
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\integrations\feedme\fields\Rating as FeedMeRatingField;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
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
        PluginHelper::bootstrap($this, 'ratingHelper', [], [], [
            'installExperience' => [
                'headline' => Craft::t('formie-rating-field', 'Formie Rating Field'),
                'body' => Craft::t('formie-rating-field', 'Configure rating fields, review submission statistics, and manage cache behavior from one control panel workspace.'),
                'ctaLabel' => Craft::t('formie-rating-field', 'Open Formie Rating Field'),
                'ctaUrl' => 'formie-rating-field',
                'redirectUri' => 'formie-rating-field',
                'confettiPreset' => 'surprise',
            ],
        ]);

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

        // Register utility — only for users who have at least one plugin permission.
        // Mirrors the gating used for the Clear Caches entry in 1.5; without it any
        // user with Craft's accessUtility:formie-rating could see the utility page
        // header and confirm the plugin is installed.
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $user = Craft::$app->getUser();
                if (
                    !$user->checkPermission('formieRatingField:viewStatistics')
                    && !$user->checkPermission('formieRatingField:manageCache')
                    && !$user->checkPermission('formieRatingField:manageSettings')
                ) {
                    return;
                }
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
        if ($settings->getEffectiveCacheGenerationSchedule() !== 'disabled') {
            $this->scheduleInitialCacheGeneration();
        }
    }

    /**
     * Schedule initial cache generation job if not already queued
     */
    private function scheduleInitialCacheGeneration(): void
    {
        // Mutex makes the check-then-push atomic across concurrent requests.
        // Without it, two simultaneous web requests can both pass the existsCheck()
        // and each push a duplicate job. Non-blocking acquire — if another request
        // is currently scheduling, this one skips silently.
        $mutex = Craft::$app->getMutex();
        $lockName = 'formie-rating-field:schedule-cache-job';

        if (!$mutex->acquire($lockName)) {
            return;
        }

        try {
            $settings = $this->getSettings();
            $schedule = $settings->getEffectiveCacheGenerationSchedule();
            $nextRun = ScheduleHelper::calculateNext($schedule);
            $delay = ScheduleHelper::calculateDelaySeconds($schedule);
            $existingJobIds = $this->findPendingScheduledCacheGenerationJobIds();
            if (!empty($existingJobIds)) {
                $this->collapseDuplicateScheduledCacheGenerationJobs($existingJobIds);
                return;
            }

            if ($nextRun !== null && $delay > 0) {
                $job = new GenerateCacheJob([
                    'reschedule' => true,
                    'scheduledMaster' => true,
                    'nextRunTime' => DateFormatHelper::formatCompactDatetimeFromSettings(
                        $nextRun,
                        $settings,
                        false,
                        false,
                    ),
                ]);

                Craft::$app->getQueue()->delay($delay)->push($job);

                Craft::info('Scheduled initial cache generation job', __METHOD__);
            }
        } finally {
            $mutex->release($lockName);
        }
    }

    /**
     * Handle automatic cache-generation schedule changes when settings are saved.
     *
     * @since 3.20.0
     */
    public function handleCacheGenerationScheduleChange(Settings $newSettings, string $oldSchedule): void
    {
        if ($newSettings->getEffectiveCacheGenerationSchedule() === $this->normalizeCacheGenerationSchedule($oldSchedule)) {
            return;
        }

        $this->cancelScheduledCacheGenerationJobs();

        if ($newSettings->getEffectiveCacheGenerationSchedule() === 'disabled') {
            Craft::info('Automatic cache generation disabled', __METHOD__);
            return;
        }

        $this->scheduleInitialCacheGeneration();

        Craft::info('Automatic cache generation schedule updated', __METHOD__);
    }

    /**
     * Find pending recurring cache-generation master queue rows.
     *
     * @return int[]
     */
    private function findPendingScheduledCacheGenerationJobIds(): array
    {
        return array_map('intval', (new Query())
            ->select(['id'])
            ->from('{{%queue}}')
            ->where($this->scheduledCacheGenerationJobCondition(true))
            ->orderBy(['id' => SORT_ASC])
            ->column());
    }

    /**
     * @param int[] $jobIds
     */
    private function collapseDuplicateScheduledCacheGenerationJobs(array $jobIds): void
    {
        $duplicateIds = array_slice($jobIds, 1);

        if (empty($duplicateIds)) {
            return;
        }

        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', ['id' => $duplicateIds])
            ->execute();
    }

    /**
     * Cancel pending recurring cache-generation master jobs.
     */
    private function cancelScheduledCacheGenerationJobs(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', $this->scheduledCacheGenerationJobCondition(false))
            ->execute();
    }

    /**
     * @return array<int, mixed>
     */
    private function scheduledCacheGenerationJobCondition(bool $pendingOnly): array
    {
        $condition = [
            'and',
            ['like', 'job', 'formieratingfield'],
            ['like', 'job', 'GenerateCacheJob'],
            [
                'or',
                ['like', 'job', '"scheduledMaster";b:1'],
                ['like', 'job', '"scheduledMaster":true'],
                [
                    'and',
                    ['not like', 'job', 'scheduledMaster'],
                    [
                        'or',
                        ['like', 'job', '"reschedule";b:1'],
                        ['like', 'job', '"reschedule":true'],
                    ],
                ],
            ],
        ];

        if ($pendingOnly) {
            $condition[] = ['fail' => false];
            $condition[] = ['timeUpdated' => null];
        }

        return $condition;
    }

    /**
     * Normalize cache-generation schedule values.
     */
    private function normalizeCacheGenerationSchedule(string $schedule): string
    {
        $settings = new Settings();
        $settings->cacheGenerationSchedule = $schedule;

        return $settings->getEffectiveCacheGenerationSchedule();
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
     * @since 3.17.0
     */
    public function getCpSections(Settings $settings): array
    {
        return [
            [
                'key' => 'statistics',
                'label' => Craft::t('formie-rating-field', 'Statistics'),
                'url' => 'formie-rating-field',
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
