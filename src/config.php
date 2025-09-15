<?php
/**
 * Formie Rating Field Plugin Configuration
 *
 * Copy this file to your craft/config/ directory as 'formie-rating-field.php'
 * to override plugin settings.
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

return [
    /**
     * Plugin name shown in Control Panel (optional)
     * Usually set via Settings → Plugins → Formie Rating Field instead.
     */
    // 'pluginName' => 'Custom Rating Field Name',
    
    /**
     * Default rating type for new rating fields
     * Options: 'star', 'emoji', 'nps'
     */
    'defaultRatingType' => 'star',
    
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
    
    /**
     * Allow half ratings by default (star type only)
     */
    'defaultAllowHalfRatings' => false,
    
    /**
     * Show selected value label by default
     * Note: This feature is currently hidden in the Formie UI
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
];