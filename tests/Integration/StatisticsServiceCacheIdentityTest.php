<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use craft\helpers\FileHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\jobs\GenerateCacheJob;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use yii\db\Expression;

/**
 * Covers configuration-aware statistics cache identities.
 *
 * @since 3.22.0
 */
final class StatisticsServiceCacheIdentityTest extends TestCase
{
    public function testIdenticalFieldConfigurationProducesStableIdentities(): void
    {
        $first = $this->cacheIdentities($this->ratingField());
        $second = $this->cacheIdentities($this->ratingField());

        self::assertSame($first, $second);
    }

    #[DataProvider('fieldConfigurationMutationProvider')]
    public function testFieldConfigurationChangesInvalidateFileAndApplicationCacheIdentities(array $overrides): void
    {
        $baseline = $this->cacheIdentities($this->ratingField());
        $changed = $this->cacheIdentities($this->ratingField($overrides));

        self::assertNotSame($baseline['file'], $changed['file']);
        self::assertNotSame($baseline['application'], $changed['application']);
    }

    public static function fieldConfigurationMutationProvider(): array
    {
        return [
            'uid' => [['uid' => 'field-uid-b']],
            'handle' => [['handle' => 'recommendation']],
            'rating type' => [['ratingType' => Rating::RATING_TYPE_EMOJI]],
            'minimum' => [['minValue' => 0]],
            'maximum' => [['maxValue' => 8]],
            'label' => [['label' => 'Recommendation']],
            'half ratings' => [['allowHalfRatings' => true]],
        ];
    }

    public function testSiteGroupAndTrendVariantsRemainSegregated(): void
    {
        $field = $this->ratingField();
        $summary = $this->cacheIdentities($field);
        $site = $this->cacheIdentities($field, 'last30days', null, 2);
        $group = $this->cacheIdentities($field, 'last30days', 'branch');
        $trend = $this->cacheIdentities($field, 'last30days', '__trend__');

        self::assertCount(4, array_unique([
            $summary['file'],
            $site['file'],
            $group['file'],
            $trend['file'],
        ]));
        self::assertCount(4, array_unique([
            $summary['application'],
            $site['application'],
            $group['application'],
            $trend['application'],
        ]));
    }

    public function testEditableSiteListsHaveDeterministicOrderIndependentIdentities(): void
    {
        $field = $this->ratingField();
        $ordered = $this->cacheIdentities($field, 'last30days', null, [2, 5]);
        $reorderedWithDuplicate = $this->cacheIdentities($field, 'last30days', null, [5, 2, 5]);
        $different = $this->cacheIdentities($field, 'last30days', null, [2]);
        $empty = $this->cacheIdentities($field, 'last30days', null, []);

        self::assertSame($ordered, $reorderedWithDuplicate);
        self::assertNotSame($ordered, $different);
        self::assertNotSame($ordered, $empty);
        self::assertNotSame($empty, $this->cacheIdentities($field, 'last30days', null, 'all'));
    }

    public function testFileAndApplicationCacheDeriveFromTheSameIdentity(): void
    {
        $field = $this->ratingField();
        $identity = $this->buildCacheIdentity($field, 'last90days', 'region', 3);
        $identities = $this->cacheIdentities($field, 'last90days', 'region', 3);

        self::assertSame($identity, $identities['application']);
        self::assertSame(self::TEST_FORM_ID . '-' . md5($identity) . '.cache', $identities['file']);
    }

    #[TestWith(['craft'], 'owner saved Craft application cache')]
    #[TestWith(['redis'], 'owner saved legacy Redis application cache')]
    public function testFingerprintFilenamePreservesFormScopedClearingPrefix(string $savedStorageMethod): void
    {
        $this->withForcedDurableFileCache(
            $savedStorageMethod,
            function(StatisticsService $statistics): void {
                $cachePath = $this->statisticsCachePath();
                FileHelper::createDirectory($cachePath);

                $field = $this->ratingField();
                $target = $cachePath . $statistics->getCacheFilename(self::TEST_FORM_ID, $field, 'all');
                $neighbour = $cachePath . $statistics->getCacheFilename(self::TEST_FORM_ID + 1, $field, 'all');

                try {
                    self::assertStringStartsWith(self::TEST_FORM_ID . '-', basename($target));
                    self::assertNotFalse(file_put_contents($target, '{"totalResponses":1}'));
                    self::assertNotFalse(file_put_contents($neighbour, '{"totalResponses":2}'));

                    self::assertTrue($statistics->clearCacheForForm(self::TEST_FORM_ID));
                    self::assertFileDoesNotExist($target);
                    self::assertFileExists($neighbour);
                } finally {
                    @unlink($neighbour);
                }
            },
        );
    }

    public function testCachedStatisticsAndJobLoggingPassTheRatingConfiguration(): void
    {
        $fieldStatistics = $this->methodSource(StatisticsService::class, 'getFieldStatistics');
        $distribution = $this->methodSource(StatisticsService::class, 'getDistributionData');
        $trend = $this->methodSource(StatisticsService::class, 'getTrendData');
        $job = $this->methodSource(GenerateCacheJob::class, 'processBatch');

        self::assertStringContainsString('getFromCache($form->id, $field,', $fieldStatistics);
        self::assertStringContainsString('saveToCache($form->id, $field,', $fieldStatistics);
        self::assertStringContainsString('getFieldStatistics($form, $field,', $distribution);
        self::assertStringContainsString('getFromCache($form->id, $field,', $trend);
        self::assertStringContainsString('saveToCache($form->id, $field,', $trend);
        self::assertStringContainsString('getCacheFilename($form->id, $field,', $job);
    }

    public function testCachedEntryPointsCanonicalizeBeforeEveryDateRangeUse(): void
    {
        $this->assertDateRangeNormalizedBeforeUses('getFieldStatistics', [
            'getFromCache(',
            'getGroupedStatistics(',
            'calculateFieldStatistics(',
            'saveToCache(',
        ]);
        $this->assertDateRangeNormalizedBeforeUses('getTrendData', [
            'getFromCache(',
            'buildTrendBucketExpression(',
            'DateRangeHelper::getBounds(',
            'saveToCache(',
        ]);
    }

    public function testServiceDateRangeCanonicalizationPreservesEveryStandardRange(): void
    {
        $options = DateRangeHelper::getOptions('assoc', false);

        self::assertArrayNotHasKey('custom', $options);

        foreach (array_keys($options) as $dateRange) {
            self::assertSame($dateRange, $this->normaliseDateRange($dateRange));
        }
    }

    #[DataProvider('nonCanonicalServiceDateRangeProvider')]
    public function testNonCanonicalServiceDateRangesUseAllBehaviorAndIdentity(string $dateRange): void
    {
        $field = $this->ratingField();
        $canonicalDateRange = $this->normaliseDateRange($dateRange);

        self::assertSame('all', $canonicalDateRange);
        self::assertSame(
            $this->cacheIdentities($field, 'all'),
            $this->cacheIdentities($field, $dateRange),
        );
        self::assertSame(
            $this->trendBucketExpression('all'),
            $this->trendBucketExpression($canonicalDateRange),
        );
    }

    public static function nonCanonicalServiceDateRangeProvider(): array
    {
        return [
            'legacy alltime' => ['alltime'],
            'custom' => ['custom'],
            'unknown' => ['last31days'],
            'injected' => ['all/../../cache-poison'],
        ];
    }

    public function testCanonicalAllUsesWeeklyTrendBuckets(): void
    {
        $weeklyExpression = $this->trendBucketExpression($this->normaliseDateRange('all'));
        $dailyExpression = $this->trendBucketExpression($this->normaliseDateRange('last30days'));

        self::assertMatchesRegularExpression('/%x-%v|IYYY-IW/', $weeklyExpression);
        self::assertMatchesRegularExpression('/%Y-%m-%d|YYYY-MM-DD/', $dailyExpression);
        self::assertNotSame($weeklyExpression, $dailyExpression);
    }

    /**
     * @param array<string, int|string> $overrides
     */
    private function ratingField(array $overrides = []): Rating
    {
        $config = array_merge([
            'uid' => 'field-uid-a',
            'handle' => 'satisfaction',
            'label' => 'Satisfaction',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'allowHalfRatings' => false,
        ], $overrides);

        return new Rating($config);
    }

    /**
     * @return array{file: string, application: string}
     */
    private function cacheIdentities(
        Rating $field,
        string $dateRange = 'last30days',
        ?string $groupBy = null,
        int|string|array $siteId = 'all',
    ): array {
        $application = $this->buildCacheIdentity($field, $dateRange, $groupBy, $siteId);

        return [
            'file' => $this->statistics->getCacheFilename(self::TEST_FORM_ID, $field, $dateRange, $groupBy, $siteId),
            'application' => $application,
        ];
    }

    private function buildCacheIdentity(
        Rating $field,
        string $dateRange,
        ?string $groupBy,
        int|string|array $siteId,
    ): string {
        $method = new ReflectionMethod(StatisticsService::class, 'buildCacheIdentity');
        $identity = $method->invoke($this->statistics, self::TEST_FORM_ID, $field, $dateRange, $groupBy, $siteId);

        self::assertIsString($identity);

        return $identity;
    }

    private function normaliseDateRange(string $dateRange): string
    {
        $method = new ReflectionMethod(StatisticsService::class, 'normaliseDateRange');
        $result = $method->invoke($this->statistics, $dateRange);

        self::assertIsString($result);

        return $result;
    }

    private function trendBucketExpression(string $dateRange): string
    {
        $method = new ReflectionMethod(StatisticsService::class, 'buildTrendBucketExpression');
        $expression = $method->invoke($this->statistics, $dateRange, 'dateCreated');

        self::assertInstanceOf(Expression::class, $expression);
        self::assertIsString($expression->expression);

        return $expression->expression;
    }

    /**
     * @param string[] $uses
     */
    private function assertDateRangeNormalizedBeforeUses(string $method, array $uses): void
    {
        $source = $this->methodSource(StatisticsService::class, $method);
        $normalization = strpos($source, '$dateRange = $this->normaliseDateRange($dateRange);');

        self::assertIsInt($normalization, "{$method} must canonicalize dateRange.");
        self::assertSame(1, substr_count($source, '$dateRange = $this->normaliseDateRange($dateRange);'));

        foreach ($uses as $use) {
            $position = strpos($source, $use);

            self::assertIsInt($position, "{$method} must contain {$use}.");
            self::assertGreaterThan($normalization, $position, "{$method} must canonicalize dateRange before {$use}.");
        }
    }

    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
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
