<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\tests\TestCase;

/**
 * Pins the `Rating::init()` defaulting chain.
 *
 * Audit #2.6 LOW called out that the min/max defaulting + NPS override
 * blocks were order-sensitive: any refactor that re-orders the branches
 * could leave non-NPS fields with `minValue=null` or let NPS fields
 * inherit a non-0-10 range from plugin settings. Two contracts here:
 *
 *  - NPS unconditionally clamps to 0-10 — even when explicit `minValue`
 *    / `maxValue` were supplied in the config.
 *  - Non-NPS fields preserve explicit `minValue` / `maxValue` from the
 *    config (the plugin-settings defaulting only kicks in when the
 *    config left them null).
 *
 * @since 3.19.0
 */
final class RatingFieldInitTest extends TestCase
{
    public function testNpsOverridesMinMaxToZeroAndTenEvenWhenExplicitValuesProvided(): void
    {
        // Caller supplies "wrong" min/max alongside ratingType=nps. NPS
        // semantics demand a 0-10 scale — distribution buckets, promoter
        // (>=9) / detractor (<=6) thresholds, and the cached chart scaleMax
        // all hard-depend on it. The init() override is the only place this
        // clamp happens.
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_NPS,
            'minValue' => 99,
            'maxValue' => 100,
        ]);

        self::assertSame(Rating::RATING_TYPE_NPS, $field->ratingType);
        self::assertSame(0, $field->minValue);
        self::assertSame(10, $field->maxValue);
    }

    public function testStarFieldPreservesExplicitMinMaxAndDoesNotInheritNpsClamp(): void
    {
        // Non-NPS path: the explicit minValue/maxValue passed in via config
        // must survive init() unchanged. If the NPS clamp ever fired here,
        // every custom-range star field would silently snap to 0-10.
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 2,
            'maxValue' => 7,
        ]);

        self::assertSame(Rating::RATING_TYPE_STAR, $field->ratingType);
        self::assertSame(2, $field->minValue);
        self::assertSame(7, $field->maxValue);
    }
}
