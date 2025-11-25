<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * Feed Me integration for the Rating field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\integrations\feedme\fields;

use lindemannrock\formieratingfield\fields\Rating as RatingField;

use craft\feedme\fields\Number as FeedMeNumber;
use verbb\formie\integrations\feedme\fields\BaseFieldTrait;

/**
 * Feed Me integration for the Rating field
 *
 * Extends Feed Me's Number field handler since Rating stores numeric values.
 * Uses Formie's BaseFieldTrait to handle field scoping.
 *
 * @author LindemannRock
 * @since 1.0.0
 */
class Rating extends FeedMeNumber
{
    // Traits
    // =========================================================================

    use BaseFieldTrait;


    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Rating';

    /**
     * @var string
     */
    public static string $class = RatingField::class;
}
