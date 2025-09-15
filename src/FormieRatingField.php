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
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\models\Settings;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
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
 * @method Settings getSettings()
 */
class FormieRatingField extends Plugin
{
    /**
     * @var FormieRatingField
     */
    public static FormieRatingField $plugin;

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Set the alias for this module
        Craft::setAlias('@lindemannrock/formieratingfield', __DIR__);
        
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

        // Register our field
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELDS,
            function(RegisterFieldsEvent $event) {
                $event->fields[] = Rating::class;
            }
        );

        
        // Set the plugin name from settings
        $settings = $this->getSettings();
        if ($settings && !empty($settings->pluginName)) {
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
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(
            'formie-rating-field/settings',
            [
                'settings' => $this->getSettings(),
                'plugin' => $this,
            ]
        );
    }
}