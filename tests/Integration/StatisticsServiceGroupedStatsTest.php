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
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;

/**
 * Pins grouped-statistics payload building after the GROUP_CONCAT removal.
 *
 * @since 3.22.0
 */
final class StatisticsServiceGroupedStatsTest extends TestCase
{
    public function testGroupedStarAverageUsesSqlAggregateNotFetchedMedianValues(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
        ]);

        $groups = $this->buildGroupedStatsFromAggregateRows(
            [
                [
                    'groupValue' => 'Branch A',
                    'count' => 300,
                    'average' => '4.75',
                ],
            ],
            $field,
            [
                // Deliberately does not average to 4.75. This guards the regression
                // where grouped averages were derived from a transported value list.
                'Branch A' => array_fill(0, 200, 1.0),
            ],
        );

        self::assertSame('Branch A', $groups[0]['label']);
        self::assertSame(300, $groups[0]['count']);
        self::assertSame(4.75, $groups[0]['average']);
        self::assertSame(1.0, $groups[0]['median']);
    }

    public function testGroupedNpsStatsPreserveBreakdownAndDistributionShape(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_NPS,
        ]);

        $row = [
            'groupValue' => 'Branch B',
            'count' => 10,
            'average' => '7.40',
            'promoters' => 3,
            'passives' => 4,
            'detractors' => 3,
        ];

        for ($i = 0; $i <= 10; $i++) {
            $row["score{$i}"] = $i === 10 ? 3 : 0;
        }

        $groups = $this->buildGroupedStatsFromAggregateRows([$row], $field);

        self::assertSame('Branch B', $groups[0]['label']);
        self::assertSame(10, $groups[0]['count']);
        self::assertSame(0.0, $groups[0]['npsScore']);
        self::assertSame(3, $groups[0]['promoters']);
        self::assertSame(30.0, $groups[0]['promotersPercentage']);
        self::assertSame(4, $groups[0]['passives']);
        self::assertSame(40.0, $groups[0]['passivesPercentage']);
        self::assertSame(3, $groups[0]['detractors']);
        self::assertSame(30.0, $groups[0]['detractorsPercentage']);
        self::assertSame(7.4, $groups[0]['average']);
        self::assertCount(11, $groups[0]['distribution']);
        self::assertSame(['value' => 10, 'count' => 3, 'percentage' => 30.0], $groups[0]['distribution'][10]);
    }

    /**
     * @param array $rows
     * @param Rating $field
     * @param array<string, float[]> $medianValuesByGroup
     * @return array
     */
    private function buildGroupedStatsFromAggregateRows(array $rows, Rating $field, array $medianValuesByGroup = []): array
    {
        $method = new ReflectionMethod(StatisticsService::class, 'buildGroupedStatsFromAggregateRows');
        $result = $method->invoke($this->statistics, $rows, $field, $medianValuesByGroup);

        self::assertIsArray($result);

        return $result;
    }
}
