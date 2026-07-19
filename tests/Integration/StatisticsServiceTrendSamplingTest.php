<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;

/**
 * Pins trend down-sampling to a bounded, chronological, endpoint-safe payload.
 *
 * @since 3.22.0
 */
final class StatisticsServiceTrendSamplingTest extends TestCase
{
    public function testFiftyPointsRemainUnchanged(): void
    {
        $points = $this->trendPoints(50);

        self::assertSame($points, $this->sampleTrendData($points));
    }

    public function testFiftyOnePointsAreCappedWhileRetainingFirstAndLast(): void
    {
        $points = $this->trendPoints(51);
        $sampled = $this->sampleTrendData($points);

        self::assertCount(50, $sampled);
        self::assertSame($points[0], $sampled[0]);
        self::assertSame($points[50], $sampled[49]);
    }

    public function testNinetyPointsRetainTheNewestBucket(): void
    {
        $points = $this->trendPoints(90);
        $sampled = $this->sampleTrendData($points);

        self::assertCount(50, $sampled);
        self::assertSame($points[0], $sampled[0]);
        self::assertSame($points[89], $sampled[49]);
    }

    public function testOneHundredPointsRemainWithinTheLimit(): void
    {
        $points = $this->trendPoints(100);
        $sampled = $this->sampleTrendData($points);

        self::assertLessThanOrEqual(50, count($sampled));
        self::assertSame($points[0], $sampled[0]);
        self::assertSame($points[99], $sampled[array_key_last($sampled)]);
    }

    public function testSampledPointsRemainChronologicalWithMatchingPayloads(): void
    {
        $points = $this->trendPoints(90);
        $sampled = $this->sampleTrendData($points);
        $previousIndex = -1;

        foreach ($sampled as $point) {
            $sourceIndex = (int)substr($point['date'], strlen('bucket-'));

            self::assertGreaterThan($previousIndex, $sourceIndex);
            self::assertSame($points[$sourceIndex], $point);

            $previousIndex = $sourceIndex;
        }
    }

    /**
     * @return array<int, array{date: string, value: float, count: int}>
     */
    private function trendPoints(int $count): array
    {
        $points = [];

        for ($index = 0; $index < $count; $index++) {
            $points[] = [
                'date' => 'bucket-' . $index,
                'value' => $index + 0.25,
                'count' => $index + 1,
            ];
        }

        return $points;
    }

    /**
     * @param array<int, array{date: string, value: float, count: int}> $chartData
     * @return array<int, array{date: string, value: float, count: int}>
     */
    private function sampleTrendData(array $chartData): array
    {
        $method = new ReflectionMethod(StatisticsService::class, 'sampleTrendData');
        $result = $method->invoke($this->statistics, $chartData);

        self::assertIsArray($result);

        return $result;
    }
}
