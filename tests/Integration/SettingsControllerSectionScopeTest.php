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
    }
}
