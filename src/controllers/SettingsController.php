<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\SettingsPostHelper;
use lindemannrock\formieratingfield\cache\StatisticsCacheStoragePresenter;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\models\Settings;
use yii\web\Response;

/**
 * Settings Controller
 *
 * @author LindemannRock
 * @since 3.3.0
 */
class SettingsController extends Controller
{
    /**
     * @var bool Whether settings are read-only (project config mode)
     */
    private bool $readOnly;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        // Settings are read-only when allowAdminChanges is disabled (project config is read-only)
        $this->readOnly = !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
    }

    /**
     * Settings index - redirects to general
     */
    public function actionIndex(): Response
    {
        return $this->redirect('formie-rating-field/settings/general');
    }

    /**
     * General settings
     */
    public function actionGeneral(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('formieRatingField:manageSettings');

        $settings = FormieRatingField::$plugin->getSettings();

        return $this->renderTemplate('formie-rating-field/settings/general', [
            'settings' => $settings,
            'readOnly' => $this->readOnly,
        ]);
    }

    /**
     * Interface settings
     */
    public function actionInterface(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('formieRatingField:manageSettings');

        $settings = FormieRatingField::$plugin->getSettings();

        return $this->renderTemplate('formie-rating-field/settings/interface', [
            'settings' => $settings,
            'readOnly' => $this->readOnly,
        ]);
    }

    /**
     * Cache settings
     */
    public function actionCache(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('formieRatingField:manageSettings');

        $settings = FormieRatingField::$plugin->getSettings();

        return $this->renderTemplate('formie-rating-field/settings/cache', array_merge([
            'settings' => $settings,
            'readOnly' => $this->readOnly,
        ], $this->cacheTemplateVariables($settings)));
    }

    /**
     * Save settings
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requirePermission('formieRatingField:manageSettings');

        // Prevent saving if in read-only mode
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new \yii\web\ForbiddenHttpException(Craft::t('formie-rating-field', 'Administrative changes are disallowed in this environment.'));
        }

        $params = (array)Craft::$app->getRequest()->getBodyParam('settings', []);
        $section = $this->validSection(Craft::$app->getRequest()->getBodyParam('section', 'general'));
        $plugin = FormieRatingField::$plugin;
        $settings = $plugin->getSettings();
        $oldCacheGenerationSchedule = $settings->cacheGenerationSchedule;

        $sectionAttributes = $this->validationAttributesForSection($section);
        $result = SettingsPostHelper::apply(
            model: $settings,
            postedValues: $params,
            allowedAttributes: $sectionAttributes,
            shouldSkipAttribute: fn(string $attribute): bool => $settings->isOverriddenByConfig($attribute),
        );
        $attributesToValidate = $result->attributesToValidate;
        $params = array_intersect_key($settings->getAttributes($result->assignedAttributes), array_flip($attributesToValidate));

        // Validate
        if ($result->hasErrors || !$settings->validate($attributesToValidate)) {
            Craft::$app->getSession()->setError(Craft::t('formie-rating-field', 'Could not save settings.'));
            $variables = [
                'settings' => $settings,
                'readOnly' => $this->readOnly,
            ];
            if ($section === 'cache') {
                $variables = array_merge($variables, $this->cacheTemplateVariables($settings));
            }

            return $this->renderTemplate("formie-rating-field/settings/{$section}", $variables);
        }

        // Save the settings
        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $params)) {
            Craft::$app->getSession()->setError(Craft::t('formie-rating-field', 'Could not save settings.'));
            return $this->asFailure(Craft::t('formie-rating-field', 'Could not save settings.'));
        }

        if (in_array('cacheGenerationSchedule', $attributesToValidate, true)) {
            $plugin->setSettings([]);
            /** @var \lindemannrock\formieratingfield\models\Settings $settings */
            $settings = $plugin->getSettings();
            $plugin->handleCacheGenerationScheduleChange($settings, $oldCacheGenerationSchedule);
        }

        Craft::$app->getSession()->setNotice(Craft::t('formie-rating-field', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Normalize mixed section input to a supported settings section.
     *
     * @param mixed $section Raw request value
     * @return string Supported settings section
     */
    private function validSection(mixed $section): string
    {
        $allowed = ['general', 'interface', 'cache'];
        return is_string($section) && in_array($section, $allowed, true) ? $section : 'general';
    }

    /**
     * Build storage previews from the same resolver used by statistics requests.
     *
     * @return array{cacheStorage: array{
     *     applicationToken: string,
     *     selectedPanel: string,
     *     file: array<string, bool|string|null>,
     *     application: array<string, bool|string|null>
     * }}
     */
    private function cacheTemplateVariables(Settings $settings): array
    {
        $statistics = FormieRatingField::$plugin->statistics;
        $presenter = new StatisticsCacheStoragePresenter();
        $applicationToken = $presenter->applicationOptionToken($settings->cacheStorageMethod);
        $fileDecision = $statistics->getCacheStorageDecision('file');
        $applicationDecision = $statistics->getCacheStorageDecision($applicationToken);
        $filePath = $fileDecision->usesFileCache()
            ? PluginHelper::getCachePath(FormieRatingField::$plugin, 'statistics')
            : null;

        return [
            'cacheStorage' => [
                'applicationToken' => $applicationToken,
                'selectedPanel' => $settings->cacheStorageMethod === 'file' ? 'file' : 'application',
                'file' => $presenter->present($fileDecision, $filePath),
                'application' => $presenter->present($applicationDecision),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function validationAttributesForSection(string $section): array
    {
        return match ($section) {
            'general' => [
                'pluginName',
                'defaultRatingType',
                'defaultEmojiRenderMode',
                'defaultRatingSize',
                'defaultMinRating',
                'defaultMaxRating',
                'defaultAllowHalfRatings',
                'defaultSingleEmojiSelection',
                'defaultShowSelectedLabel',
                'defaultShowEndpointLabels',
                'defaultStartLabel',
                'defaultEndLabel',
            ],
            'interface' => [
                'itemsPerPage',
                'maxExportRows',
                'defaultDateRange',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'cache' => [
                'cacheStorageMethod',
                'cacheGenerationSchedule',
            ],
            default => [],
        };
    }
}
