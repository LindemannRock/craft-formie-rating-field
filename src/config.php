<?php
/**
 * Formie Rating Field config.php
 *
 * This file exists only as a template for the Formie Rating Field settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'formie-rating-field.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 *
 * @since 1.0.0
 */

return [
    // Global settings
    '*' => [
        // ========================================
        // GENERAL SETTINGS
        // ========================================
        // Basic plugin configuration

        /**
         * Plugin name shown in Control Panel (optional)
         * Usually set via Settings → Plugins → Formie Rating Field instead.
         */
        // 'pluginName' => 'Custom Rating Field Name',


        // ========================================
        // FIELD DEFAULT SETTINGS
        // ========================================
        // Default settings for new rating fields

        /**
         * Default rating type for new rating fields
         * Options: 'star', 'emoji', 'nps'
         */
        'defaultRatingType' => 'star',

        /**
         * Default emoji render mode (for emoji rating type)
         * Controls how emoji ratings are rendered
         *
         * Options:
         * - 'system': Use native platform emojis (iOS, Android, Windows, etc.)
         * - 'noto-color': Load Noto Color Emoji (detailed, colorful style)
         * - 'noto-simple': Load Noto Emoji (simple, clean style)
         * - 'webfont': Deprecated, maps to 'noto-color' for backward compatibility
         *
         * Default: 'system'
         */
        'defaultEmojiRenderMode' => 'system',

        /**
         * Default rating size for new rating fields
         * Options: 'small', 'medium', 'large', 'xlarge'
         */
        'defaultRatingSize' => 'medium',

        /**
         * Default minimum rating value
         * Options: 0, 1
         */
        'defaultMinRating' => 1,

        /**
         * Default maximum rating value
         * Options: 3, 4, 5, 6, 7, 8, 9, 10
         */
        'defaultMaxRating' => 5,


        // ========================================
        // RATING TYPE SETTINGS
        // ========================================
        // Type-specific default settings

        /**
         * Allow half ratings by default (star type only)
         */
        'defaultAllowHalfRatings' => false,

        /**
         * Enable single emoji selection mode by default (emoji type only)
         * When enabled, only the selected emoji is highlighted (not cumulative)
         * and custom labels display beneath the selected emoji
         */
        'defaultSingleEmojiSelection' => false,


        // ========================================
        // LABEL SETTINGS
        // ========================================
        // Default label configuration

        /**
         * Show endpoint labels by default
         * Displays descriptive text at the start/end of the rating scale
         */
        'defaultShowEndpointLabels' => false,

        /**
         * Default start label text
         * Used when showEndpointLabels is enabled
         * Examples: 'Poor', 'Not Likely', 'Disagree'
         */
        'defaultStartLabel' => '',

        /**
         * Default end label text
         * Used when showEndpointLabels is enabled
         * Examples: 'Excellent', 'Very Likely', 'Strongly Agree'
         */
        'defaultEndLabel' => '',
    ],

    // Dev environment settings
    'dev' => [
        // Development-specific settings can go here
    ],

    // Staging environment settings
    'staging' => [
        // Staging-specific settings can go here
    ],

    // Production environment settings
    'production' => [
        // Production-specific settings can go here
    ],
];
