<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use lindemannrock\formieratingfield\controllers\SettingsController;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 3.21.0
 */
#[CoversClass(SettingsController::class)]
final class SettingsControllerSectionScopeTest extends TestCase
{
    public function testSettingsSectionsMatchRenderedFormScopes(): void
    {
        $controller = new SettingsController('settings', FormieRatingField::$plugin);
        $method = new \ReflectionMethod($controller, 'validationAttributesForSection');

        $expected = [
            'general' => [
                'pluginName',
                'defaultRatingType',
                'defaultEmojiRenderMode',
                'defaultRatingSize',
                'defaultMinRating',
                'defaultMaxRating',
                'defaultAllowHalfRatings',
                'defaultSingleEmojiSelection',
                'defaultShowSelectedLabel',
                'defaultShowEndpointLabels',
                'defaultStartLabel',
                'defaultEndLabel',
            ],
            'interface' => [
                'itemsPerPage',
                'maxExportRows',
                'defaultDateRange',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'cache' => [
                'cacheStorageMethod',
                'cacheGenerationSchedule',
            ],
        ];

        foreach ($expected as $section => $attributes) {
            self::assertSame($attributes, $method->invoke($controller, $section), "Unexpected {$section} settings scope.");
        }

        self::assertContains('defaultSingleEmojiSelection', $expected['general']);
        self::assertNotContains('defaultSingleEmojiSelection', $expected['interface']);
        self::assertNotContains('defaultSingleEmojiSelection', $expected['cache']);
    }

    public function testGeneralTemplatePostsDefaultSingleEmojiSelectionWithConfigOverrideHandling(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/settings/general.twig');

        self::assertIsString($template);
        self::assertStringContainsString("label: 'Single Emoji Selection by Default'|t('formie-rating-field')", $template);
        self::assertStringContainsString("name: 'settings[defaultSingleEmojiSelection]'", $template);
        self::assertStringContainsString('on: settings.defaultSingleEmojiSelection ?? false', $template);
        self::assertStringContainsString("settings.isOverriddenByConfig('defaultSingleEmojiSelection')", $template);
        self::assertStringContainsString("disabled: settings.isOverriddenByConfig('defaultSingleEmojiSelection')", $template);
        self::assertStringContainsString("errors: settings.getErrors('defaultSingleEmojiSelection')", $template);

        $ratingType = strpos($template, "id: 'defaultRatingType'");
        $ratingSize = strpos($template, "id: 'defaultRatingSize'");
        $emojiMode = strpos($template, "id: 'defaultEmojiRenderMode'");
        $singleEmoji = strpos($template, "id: 'defaultSingleEmojiSelection'");
        $ratingRange = strpos($template, "'Rating Range'|t('formie-rating-field')");
        $halfRatings = strpos($template, "id: 'defaultAllowHalfRatings'");

        self::assertIsInt($ratingType);
        self::assertIsInt($ratingSize);
        self::assertIsInt($emojiMode);
        self::assertIsInt($singleEmoji);
        self::assertIsInt($ratingRange);
        self::assertIsInt($halfRatings);
        self::assertTrue(
            $ratingType < $ratingSize &&
            $ratingSize < $emojiMode &&
            $emojiMode < $singleEmoji &&
            $singleEmoji < $ratingRange &&
            $ratingRange < $halfRatings,
            'Unexpected Default Field Settings order.',
        );
    }
}
