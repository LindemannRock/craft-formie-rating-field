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
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\formieratingfield\controllers\StatisticsController;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * Covers controller-side date-range normalization and wiring.
 *
 * @since 3.22.0
 */
final class StatisticsControllerDateRangeTest extends TestCase
{
    public function testEveryStandardDateRangeRemainsCanonical(): void
    {
        $options = DateRangeHelper::getOptions('assoc', false);

        self::assertArrayNotHasKey('custom', $options);

        foreach (array_keys($options) as $dateRange) {
            self::assertSame($dateRange, $this->normalizeDateRange($dateRange));
        }
    }

    public function testLegacyAllTimeNormalizesToAll(): void
    {
        self::assertSame('all', $this->normalizeDateRange('alltime'));
    }

    #[DataProvider('invalidDateRangeProvider')]
    public function testInvalidInputsUseTheEstablishedFallback(mixed $dateRange): void
    {
        self::assertSame('all', $this->normalizeDateRange($dateRange));
        self::assertSame('last30days', $this->normalizeDateRange($dateRange, 'last30days'));
    }

    public static function invalidDateRangeProvider(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'non-string integer' => [30],
            'non-string array' => [['last30days']],
            'unknown' => ['last31days'],
            'injected' => ['all/../../cache-poison'],
            'custom' => ['custom'],
        ];
    }

    public function testConfiguredDefaultIsValidatedForActionForm(): void
    {
        self::assertSame('last7days', $this->normalizeDateRange(null, 'last7days'));
        self::assertSame('last7days', $this->normalizeDateRange('unknown', 'last7days'));
        self::assertSame('last7days', $this->normalizeDateRange('custom', 'last7days'));
        self::assertSame('all', $this->normalizeDateRange(null, 'alltime'));
        self::assertSame('all', $this->normalizeDateRange(null, 'custom'));
        self::assertSame('all', $this->normalizeDateRange(null, 'invalid-default'));
    }

    public function testAllFiveActionsNormalizeBeforeUsingDateRange(): void
    {
        $sensitiveCalls = [
            'actionForm' => ['getFieldStatistics(', "'dateRange' => \$dateRange"],
            'actionGroupDetail' => ['getPaginatedGroupSubmissions(', "'dateRange' => \$dateRange"],
            'actionGetData' => ['getFieldStatistics(', 'getTrendData(', 'getDistributionData('],
            'actionExportGroup' => ['getGroupSubmissions(', '$dateRangeLabel =', "'dateRange' => \$dateRange"],
            'actionExport' => ['buildSummaryExportRows(', '$dateRangeLabel =', "'dateRange' => \$dateRange"],
        ];

        foreach ($sensitiveCalls as $action => $tokens) {
            $source = $this->methodSource($action);
            $normalization = strpos($source, '$dateRange = $this->_normalizeDateRange(');

            self::assertIsInt($normalization, "{$action} must normalize dateRange.");
            self::assertSame(1, substr_count($source, '$dateRange = $this->_normalizeDateRange('));

            foreach ($tokens as $token) {
                $use = strpos($source, $token);
                self::assertIsInt($use, "{$action} must contain {$token}.");
                self::assertGreaterThan($normalization, $use, "{$action} must normalize dateRange before {$token}.");
            }
        }

        $formSource = $this->methodSource('actionForm');
        self::assertStringContainsString("DateRangeHelper::getDefaultDateRange('formie-rating-field')", $formSource);
        self::assertStringContainsString('_normalizeDateRange(Craft::$app->getRequest()->getQueryParam(\'dateRange\'), $configuredDateRange)', $formSource);
    }

    public function testInvalidRequestValuesCollapseToOneCacheIdentity(): void
    {
        $field = new Rating([
            'uid' => 'date-range-cache-field',
            'handle' => 'satisfaction',
            'label' => 'Satisfaction',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
        ]);
        $firstRange = $this->normalizeDateRange('invalid-one');
        $secondRange = $this->normalizeDateRange('invalid-two');

        self::assertSame($firstRange, $secondRange);
        self::assertSame(
            $this->statistics->getCacheFilename(self::TEST_FORM_ID, $field, 'invalid-one'),
            $this->statistics->getCacheFilename(self::TEST_FORM_ID, $field, 'invalid-two'),
        );

        $buildIdentity = new ReflectionMethod(StatisticsService::class, 'buildCacheIdentity');
        $firstKey = $buildIdentity->invoke($this->statistics, self::TEST_FORM_ID, $field, 'invalid-one');
        $secondKey = $buildIdentity->invoke($this->statistics, self::TEST_FORM_ID, $field, 'invalid-two');

        self::assertSame($firstKey, $secondKey);
        self::assertSame(
            $this->statistics->getCacheFilename(self::TEST_FORM_ID, $field, 'all'),
            $this->statistics->getCacheFilename(self::TEST_FORM_ID, $field, 'custom'),
        );
    }

    public function testExportsBuildFilenamesAndPayloadsAfterNormalization(): void
    {
        foreach (['actionExportGroup', 'actionExport'] as $action) {
            $source = $this->methodSource($action);
            $normalization = strpos($source, '$dateRange = $this->_normalizeDateRange(');
            $label = strpos($source, '$dateRangeLabel = $dateRange === \'all\' ? \'alltime\' : $dateRange;');
            $payload = strpos($source, "'dateRange' => \$dateRange");

            self::assertIsInt($normalization);
            self::assertIsInt($label);
            self::assertIsInt($payload);
            self::assertGreaterThan($normalization, $label);
            self::assertGreaterThan($normalization, $payload);
        }
    }

    private function normalizeDateRange(mixed $dateRange, mixed $fallback = 'all'): string
    {
        $controller = new StatisticsController('statistics', Craft::$app);
        $method = new ReflectionMethod($controller, '_normalizeDateRange');
        $result = $method->invoke($controller, $dateRange, $fallback);

        self::assertIsString($result);

        return $result;
    }

    private function methodSource(string $method): string
    {
        $reflection = new ReflectionMethod(StatisticsController::class, $method);
        $filename = $reflection->getFileName();
        self::assertIsString($filename);

        $lines = file($filename);
        self::assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }
}
