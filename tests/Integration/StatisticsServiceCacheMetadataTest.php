<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use craft\helpers\Json;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\models\FieldLayout;

/**
 * Covers generation metadata on cached statistics payloads.
 *
 * @since 3.22.0
 */
final class StatisticsServiceCacheMetadataTest extends TestCase
{
    public function testFieldStatisticsCacheStoresAndPreservesGeneratedAt(): void
    {
        [$form, $field] = $this->seedRatingFormAndSubmission();

        $this->withFileCache(function() use ($form, $field): void {
            try {
                $this->statistics->clearCacheForForm((int)$form->id);
                $first = $this->statistics->getFieldStatistics($form, $field);
                $cacheFile = $this->statisticsCachePath() . $this->statistics->getCacheFilename((int)$form->id, $field, 'all');

                $this->assertFreshApiTimestamp($first['generatedAt'] ?? null);
                $this->assertCachedTimestamp($cacheFile, $first['generatedAt']);

                $preservedTimestamp = '2000-01-01T00:00:00+00:00';
                $this->replaceCachedTimestamp($cacheFile, $preservedTimestamp);
                $cached = $this->statistics->getFieldStatistics($form, $field);

                self::assertSame($preservedTimestamp, $cached['generatedAt']);
            } finally {
                $this->statistics->clearCacheForForm((int)$form->id);
            }
        });
    }

    public function testTrendCacheStoresAndPreservesGeneratedAt(): void
    {
        [$form, $field] = $this->seedRatingFormAndSubmission();

        $this->withFileCache(function() use ($form, $field): void {
            try {
                $this->statistics->clearCacheForForm((int)$form->id);
                $first = $this->statistics->getTrendData($form, $field);
                $cacheFile = $this->statisticsCachePath() . $this->statistics->getCacheFilename(
                    (int)$form->id,
                    $field,
                    'all',
                    '__trend__',
                );

                $this->assertFreshApiTimestamp($first['generatedAt'] ?? null);
                $this->assertCachedTimestamp($cacheFile, $first['generatedAt']);

                $preservedTimestamp = '2001-02-03T04:05:06+00:00';
                $this->replaceCachedTimestamp($cacheFile, $preservedTimestamp);
                $cached = $this->statistics->getTrendData($form, $field);

                self::assertSame($preservedTimestamp, $cached['generatedAt']);
            } finally {
                $this->statistics->clearCacheForForm((int)$form->id);
            }
        });
    }

    public function testCacheTimestampsUseTheBaseApiFormatterWithMutableDates(): void
    {
        foreach (['getFieldStatistics', 'getTrendData'] as $methodName) {
            $source = $this->methodSource($methodName);

            self::assertStringContainsString('DateFormatHelper::toApiString(new \\DateTime(', $source);
            self::assertStringNotContainsString('DateTimeImmutable', $source);
            $savePosition = strpos($source, 'saveToCache(');
            $timestampPosition = strpos($source, 'generatedAt');

            self::assertIsInt($savePosition);
            self::assertIsInt($timestampPosition);
            self::assertLessThan(
                $savePosition,
                $timestampPosition,
                "{$methodName} must add generatedAt before saving the payload.",
            );
        }
    }

    /** @return array{Form, Rating} */
    private function seedRatingFormAndSubmission(): array
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Rating cache metadata test ', 'form');
        $form->handle = $this->nextTestMarker('ratingCacheMetadataTest', 'form');

        $layout = new FieldLayout();
        $layout->setPages([
            [
                'label' => 'Page 1',
                'rows' => [
                    [
                        'fields' => [
                            [
                                'type' => Rating::class,
                                'handle' => 'satisfaction',
                                'label' => 'Satisfaction',
                                'ratingType' => Rating::RATING_TYPE_STAR,
                                'minValue' => 1,
                                'maxValue' => 5,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->title = $this->nextTestMarker('ratingCacheMetadataTest', 'submission');
        $submission->setFieldValue('satisfaction', 4);
        $this->saveTestElement($submission, false, false, false);

        $field = $this->statistics->getRatingFieldByHandle($form, 'satisfaction');
        self::assertInstanceOf(Rating::class, $field);

        return [$form, $field];
    }

    private function withFileCache(callable $callback): void
    {
        $settings = FormieRatingField::$plugin->getSettings();
        $originalStorageMethod = $settings->cacheStorageMethod;
        $settings->cacheStorageMethod = 'file';

        try {
            $callback();
        } finally {
            $settings->cacheStorageMethod = $originalStorageMethod;
        }
    }

    private function assertFreshApiTimestamp(mixed $timestamp): void
    {
        self::assertIsString($timestamp);
        $date = \DateTime::createFromFormat(DATE_ATOM, $timestamp);

        self::assertNotFalse($date);
        self::assertSame('+00:00', $date->format('P'));
    }

    private function assertCachedTimestamp(string $cacheFile, mixed $expectedTimestamp): void
    {
        self::assertFileExists($cacheFile);
        $payload = Json::decode((string)file_get_contents($cacheFile));

        self::assertIsArray($payload);
        self::assertSame($expectedTimestamp, $payload['generatedAt'] ?? null);
    }

    private function replaceCachedTimestamp(string $cacheFile, string $timestamp): void
    {
        $payload = Json::decode((string)file_get_contents($cacheFile));
        self::assertIsArray($payload);
        $payload['generatedAt'] = $timestamp;

        self::assertNotFalse(file_put_contents($cacheFile, Json::encode($payload)));
    }

    private function methodSource(string $methodName): string
    {
        $reflection = new ReflectionMethod(StatisticsService::class, $methodName);
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
