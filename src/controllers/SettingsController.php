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
use lindemannrock\formieratingfield\FormieRatingField;
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

        $settings = FormieRatingField::$plugin->getSettings();

        return $this->renderTemplate('formie-rating-field/settings/general', [
            'settings' => $settings,
        ]);
    }

    /**
     * Interface settings
     */
    public function actionInterface(): Response
    {
        $this->requireCpRequest();

        $settings = FormieRatingField::$plugin->getSettings();

        return $this->renderTemplate('formie-rating-field/settings/interface', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save settings
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();

        $params = Craft::$app->getRequest()->getBodyParam('settings', []);
        $plugin = FormieRatingField::$plugin;
        $settings = $plugin->getSettings();

        // Set the new values
        $settings->setAttributes($params, false);

        // Validate
        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('formie-rating-field', 'Could not save settings.'));
            return $this->asFailure('Could not save settings.');
        }

        // Save the settings
        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $params)) {
            Craft::$app->getSession()->setError(Craft::t('formie-rating-field', 'Could not save settings.'));
            return $this->asFailure('Could not save settings.');
        }

        Craft::$app->getSession()->setNotice(Craft::t('formie-rating-field', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
