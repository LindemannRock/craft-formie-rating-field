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
 * Covers hydration of settings written by earlier plugin versions.
 *
 * @since 3.22.1
 */
final class RatingFieldSettingsCompatibilityTest extends TestCase
{
    public function testRetiredGoogleReviewButtonClassLoadsWithoutBeingSerializedAgain(): void
    {
        $field = new Rating([
            'googleReviewButtonClass' => 'legacy-review-button',
        ]);

        self::assertSame('legacy-review-button', $field->googleReviewButtonClass);
        self::assertArrayNotHasKey('googleReviewButtonClass', $field->getSettings());
    }
}
