<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use Craft;
use craft\web\View;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers submission-value rendering and Form Builder preview output.
 *
 * @since 3.22.0
 */
final class RatingFieldDisplayTest extends TestCase
{
    #[DataProvider('emojiRangeProvider')]
    public function testTwigEmojiRenderingMatchesValueAsString(
        int $minValue,
        int $maxValue,
        int $value,
        string $expectedEmoji,
    ): void {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_EMOJI,
            'minValue' => $minValue,
            'maxValue' => $maxValue,
        ]);

        $view = Craft::$app->getView();
        $originalTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $html = $field->getValueAsHtml($value);
        } finally {
            $view->setTemplateMode($originalTemplateMode);
        }

        $renderedText = preg_replace('/\s+/u', ' ', trim(strip_tags($html)));

        self::assertSame($field->getValueAsString($value), $renderedText);
        self::assertStringStartsWith($expectedEmoji . ' ', $renderedText);
    }

    public static function emojiRangeProvider(): array
    {
        return [
            'five-point set' => [1, 5, 3, '😐'],
            'eight-point set' => [1, 8, 8, '😎'],
            'eleven-point midpoint' => [0, 10, 5, '😍'],
        ];
    }

    public function testPreviewUsesBranchSpecificMinimumFallbacks(): void
    {
        $preview = (new Rating())->getPreviewInputHtml();

        $starBranch = $this->between(
            $preview,
            '<div v-if="field.settings.ratingType === \'star\'"',
            '<div v-else-if="field.settings.ratingType === \'emoji\'"',
        );
        $emojiBranch = $this->between(
            $preview,
            '<div v-else-if="field.settings.ratingType === \'emoji\'"',
            '<div v-else-if="field.settings.ratingType === \'nps\'"',
        );
        $npsBranch = $this->between(
            $preview,
            '<div v-else-if="field.settings.ratingType === \'nps\'"',
            "</div>\n        </div>",
        );

        $minimumExpression = "field.settings.minValue !== undefined && field.settings.minValue !== '' ? parseInt(field.settings.minValue)";

        self::assertSame(1, substr_count($starBranch, $minimumExpression . ' : 1'));
        self::assertSame(0, substr_count($starBranch, $minimumExpression . ' : 0'));

        self::assertSame(0, substr_count($emojiBranch, $minimumExpression . ' : 1'));
        self::assertSame(3, substr_count($emojiBranch, $minimumExpression . ' : 0'));

        self::assertSame(0, substr_count($npsBranch, $minimumExpression . ' : 1'));
        self::assertSame(2, substr_count($npsBranch, $minimumExpression . ' : 0'));
        self::assertStringContainsString(
            "v-for=\"n in (parseInt(field.settings.maxValue) || 10) - ({$minimumExpression} : 0) + 1\"",
            $npsBranch,
        );
        self::assertStringContainsString(
            '$' . "{ ({$minimumExpression} : 0) + n - 1 }",
            $npsBranch,
        );
    }

    private function between(string $value, string $startMarker, string $endMarker): string
    {
        $start = strpos($value, $startMarker);
        self::assertNotFalse($start);

        $end = strpos($value, $endMarker, $start);
        self::assertNotFalse($end);

        return substr($value, $start, $end - $start);
    }
}
