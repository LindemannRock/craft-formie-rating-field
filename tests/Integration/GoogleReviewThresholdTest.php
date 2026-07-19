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
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * @since 3.22.0
 */
final class GoogleReviewThresholdTest extends TestCase
{
    #[DataProvider('automaticThresholds')]
    public function testAutomaticThresholdUsesResolvedRatingScale(
        string $ratingType,
        int $minValue,
        int $maxValue,
        int $expectedThreshold,
    ): void {
        $field = new Rating([
            'ratingType' => $ratingType,
            'minValue' => $minValue,
            'maxValue' => $maxValue,
            'googleReviewThreshold' => null,
        ]);

        self::assertSame($expectedThreshold, $this->effectiveThreshold($field));
    }

    public function testExplicitValidThresholdIsPreserved(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'googleReviewThreshold' => 4,
        ]);

        self::assertSame(4, $this->effectiveThreshold($field));
    }

    #[DataProvider('outOfRangeThresholds')]
    public function testExplicitOutOfRangeThresholdFallsBackToAutomatic(int $threshold): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'googleReviewThreshold' => $threshold,
        ]);

        self::assertSame(5, $this->effectiveThreshold($field));
    }

    public function testGeneratedJavaScriptUsesEffectiveThresholdAndMediumOffset(): void
    {
        $field = new Rating([
            'handle' => 'satisfaction',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'googleReviewThreshold' => null,
            'enableGoogleReview' => true,
            'googlePlaceIdField' => 'googlePlaceId',
        ]);

        $js = $field->getGoogleReviewJs();

        self::assertStringContainsString('capturedRating >= 5', $js);
        self::assertStringContainsString('capturedRating >= (5 - 2)', $js);
    }

    public function testDisabledOrMissingPlaceIdReturnsNoJavaScript(): void
    {
        $disabled = new Rating([
            'enableGoogleReview' => false,
            'googlePlaceIdField' => 'googlePlaceId',
        ]);
        $missingPlaceId = new Rating([
            'enableGoogleReview' => true,
            'googlePlaceIdField' => '',
        ]);

        self::assertSame('', $disabled->getGoogleReviewJs());
        self::assertSame('', $missingPlaceId->getGoogleReviewJs());
    }

    #[DataProvider('ratingTypes')]
    public function testGoogleReviewSettingsRemainAvailableForEveryRatingType(string $ratingType): void
    {
        $field = new Rating([
            'ratingType' => $ratingType,
        ]);
        $settings = $field->defineSettingsSchema();
        $names = array_column($settings, 'name');
        $enableIndex = array_search('enableGoogleReview', $names, true);
        $thresholdIndex = array_search('googleReviewThreshold', $names, true);

        self::assertContains('enableGoogleReview', $names);
        self::assertContains('googleReviewThreshold', $names);
        self::assertIsInt($enableIndex);
        self::assertIsInt($thresholdIndex);
        self::assertArrayNotHasKey('if', $settings[$enableIndex]);
        self::assertArrayNotHasKey('value', $settings[$thresholdIndex]);
        self::assertArrayNotHasKey('required', $settings[$thresholdIndex]);
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function automaticThresholds(): iterable
    {
        yield 'five-point star' => [Rating::RATING_TYPE_STAR, 1, 5, 5];
        yield 'three-point emoji' => [Rating::RATING_TYPE_EMOJI, 1, 3, 3];
        yield 'NPS override' => [Rating::RATING_TYPE_NPS, 1, 5, 9];
        yield 'eight-point star' => [Rating::RATING_TYPE_STAR, 1, 8, 7];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeThresholds(): iterable
    {
        yield 'below minimum' => [0];
        yield 'above maximum' => [6];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ratingTypes(): iterable
    {
        yield 'star' => [Rating::RATING_TYPE_STAR];
        yield 'emoji' => [Rating::RATING_TYPE_EMOJI];
        yield 'NPS' => [Rating::RATING_TYPE_NPS];
    }

    private function effectiveThreshold(Rating $field): int
    {
        $method = new ReflectionMethod($field, 'getEffectiveGoogleReviewThreshold');
        $threshold = $method->invoke($field);

        self::assertIsInt($threshold);

        return $threshold;
    }
}
