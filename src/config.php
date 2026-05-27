<?php
/**
 * Formie Rating config.php
 *
 * This file exists only as a template for the Formie Rating settings.
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
         * Usually set via Settings → Formie Rating instead.
         */
        // 'pluginName' => 'Custom Rating Name',


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
         * Show selected value label by default
         * Displays the selected rating value as text label
         */
        'defaultShowSelectedLabel' => false,

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


        // ========================================
        // INTERFACE SETTINGS
        // ========================================
        // Control Panel interface preferences

        /**
         * Number of items to display per page in statistics lists
         * Range: 10-500
         * Default: 100
         */
        'itemsPerPage' => 100,

        /**
         * Maximum rows included in the "Raw Responses" export.
         * Hard cap protects against PHP out-of-memory errors on high-volume forms
         * (each row hydrates a full submission element through Formie's per-field-type
         * value rendering pipeline). When the cap is hit, a warning is logged.
         *
         * Range: 0-1000000
         * Default: 50000 (covers typical use)
         * Set to 0 for unlimited (use only if PHP memory_limit is generous)
         */
        'maxExportRows' => 50000,


        // ========================================
        // CACHE SETTINGS
        // ========================================
        // Statistics cache configuration

        /**
         * Cache storage method
         * How cache data is stored
         *
         * Options:
         * - 'file': File system (default, single server)
         * - 'redis': Redis/Database (load-balanced, multi-server, cloud hosting)
         *
         * Default: 'file'
         * Recommended for Servd/AWS/Platform.sh: 'redis'
         */
        'cacheStorageMethod' => 'file',

        /**
         * Schedule for automatic cache generation
         * Pre-generating cache improves performance for large datasets
         *
         * Options:
         * - 'disabled': Generate on-demand only (may be slow for 1000+ submissions)
         * - 'every3hours': Every 3 hours
         * - 'every6hours': Every 6 hours
         * - 'every12hours': Every 12 hours
         * - 'daily': Daily at midnight
         * - 'daily2am': Daily at 2am (recommended for low traffic)
         * - 'weekly': Weekly (Sunday midnight)
         *
         * Default: 'disabled'
         * Recommended for production: 'daily2am' or 'every6hours'
         */
        'cacheGenerationSchedule' => 'disabled',


        // ========================================
        // BASE PLUGIN OVERRIDES
        // ========================================
        // These settings override lindemannrock-base defaults for this plugin only.
        // Global defaults: vendor/lindemannrock/craft-plugin-base/src/config.php
        // To customize globally: copy to config/lindemannrock-base.php

        /**
         * Default date range for the statistics page
         * Options: 'today', 'yesterday', 'thisWeek', 'lastWeek', 'last7days',
         *          'last14days', 'last30days', 'last90days', 'thisMonth',
         *          'lastMonth', 'thisQuarter', 'lastQuarter', 'thisYear',
         *          'lastYear', 'last12months', 'all'
         *
         * Cascades: plugin config → plugin DB/CP value → base config → 'last30days'.
         * Set it here to force a value for this plugin; otherwise edit it in
         * Settings → Interface → Default Date Range, or set globally in
         * config/lindemannrock-base.php.
         */
        // 'defaultDateRange' => 'last7days',

        /**
         * Export format overrides
         * Enable/disable specific export formats for this plugin
         * Default: all enabled (from base plugin)
         */
        // 'exports' => [
        //     'csv' => true,
        //     'json' => true,
        //     'excel' => true,
        // ],

        /**
         * Date/time formatting overrides
         * Override base plugin date/time display settings for this plugin
         * Defaults: from config/lindemannrock-base.php
         */
        // 'timeFormat' => '24',      // '12' (AM/PM) or '24' (military)
        // 'monthFormat' => 'short',  // 'numeric' (01), 'short' (Jan), 'long' (January)
        // 'dateOrder' => 'dmy',      // 'dmy', 'mdy', 'ymd'
        // 'dateSeparator' => '/',    // '/', '-', '.'
        // 'showSeconds' => false,    // Show seconds in time display
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
