<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\twigextensions;

use lindemannrock\formieratingfield\FormieRatingField;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Plugin Name Twig Extension
 *
 * Provides centralized access to plugin name variations in Twig templates.
 *
 * Usage in templates:
 * - {{ ratingHelper.displayName }}             // "Rating" (singular, no Field)
 * - {{ ratingHelper.pluralDisplayName }}       // "Ratings" (plural, no Field)
 * - {{ ratingHelper.fullName }}                // "Formie Rating Field" (as configured)
 * - {{ ratingHelper.lowerDisplayName }}        // "rating" (lowercase singular)
 * - {{ ratingHelper.pluralLowerDisplayName }}  // "ratings" (lowercase plural)
 * @since 3.3.0
 */
class PluginNameExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Formie Rating Field - Plugin Name Helper';
    }

    /**
     * Make plugin name helper available as global Twig variable
     *
     * @return array
     */
    public function getGlobals(): array
    {
        return [
            'ratingHelper' => new PluginNameHelper(),
        ];
    }
}

/**
 * Plugin Name Helper
 *
 * Helper class that exposes Settings methods as properties for clean Twig syntax.
 */
class PluginNameHelper
{
    /**
     * Get display name (singular, without "Field")
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return FormieRatingField::$plugin->getSettings()->getDisplayName();
    }

    /**
     * Get plural display name (without "Field")
     *
     * @return string
     */
    public function getPluralDisplayName(): string
    {
        return FormieRatingField::$plugin->getSettings()->getPluralDisplayName();
    }

    /**
     * Get full plugin name (as configured)
     *
     * @return string
     */
    public function getFullName(): string
    {
        return FormieRatingField::$plugin->getSettings()->getFullName();
    }

    /**
     * Get lowercase display name (singular, without "Field")
     *
     * @return string
     */
    public function getLowerDisplayName(): string
    {
        return FormieRatingField::$plugin->getSettings()->getLowerDisplayName();
    }

    /**
     * Get lowercase plural display name (without "Field")
     *
     * @return string
     */
    public function getPluralLowerDisplayName(): string
    {
        return FormieRatingField::$plugin->getSettings()->getPluralLowerDisplayName();
    }

    /**
     * Magic getter to allow property-style access in Twig
     * Enables: {{ ratingHelper.displayName }} instead of {{ ratingHelper.getDisplayName() }}
     *
     * @param string $name
     * @return string|null
     */
    public function __get(string $name): ?string
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}
