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
use lindemannrock\formieratingfield\controllers\StatisticsController;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;

/**
 * Pins controller-side groupBy query normalization.
 *
 * @since 3.22.0
 */
final class StatisticsControllerGroupByTest extends TestCase
{
    public function testNormalizeGroupByHandleKeepsValidGroupableField(): void
    {
        self::assertSame('branch', $this->normalizeGroupByHandle('branch'));
    }

    public function testNormalizeGroupByHandleDropsStaleOrInvalidGroupBy(): void
    {
        self::assertNull($this->normalizeGroupByHandle(null));
        self::assertNull($this->normalizeGroupByHandle(''));
        self::assertNull($this->normalizeGroupByHandle('removedField'));
        self::assertNull($this->normalizeGroupByHandle(['branch']));
    }

    public function testNormalizeGroupByHandleSupportsSharedControllerEntrypoints(): void
    {
        self::assertSame('region', $this->normalizeGroupByHandle('region'));
        self::assertNull($this->normalizeGroupByHandle('staleRegion'));
    }

    private function normalizeGroupByHandle(mixed $groupBy): ?string
    {
        $controller = new StatisticsController('statistics', Craft::$app);
        $method = new ReflectionMethod($controller, '_normalizeGroupByHandle');
        $result = $method->invoke($controller, $groupBy, [
            [
                'handle' => 'branch',
                'label' => 'Branch',
                'type' => 'verbb\\formie\\fields\\PlainText',
            ],
            [
                'handle' => 'region',
                'label' => 'Region',
                'type' => 'verbb\\formie\\fields\\Dropdown',
            ],
        ]);

        self::assertTrue(is_string($result) || $result === null);

        return $result;
    }
}
