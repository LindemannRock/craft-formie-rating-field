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
use lindemannrock\formieratingfield\tests\TestCase;

/**
 * Pins the file-cache half of {@see StatisticsService}.
 *
 * Two contracts are coupled here and both have to hold for the cache layer
 * to behave:
 *
 *  - `getCacheFilename()` must produce a filename that starts with the
 *    literal `{$formId}-` (audit #2.1 — the per-form cache-clear is a
 *    `glob("{$formId}-*.cache")` against the cache dir).
 *  - `clearCacheForForm($formId)` must only delete files whose name starts
 *    with that prefix, leaving every other form's cache intact (audit #2.1
 *    CRITICAL — previously deleted ALL `*.cache` files, wiping every form
 *    on every save with `cacheGenerationSchedule=manual`).
 *
 * Pinning both in one file makes the coupling visible: rename the prefix
 * format and both tests fail together.
 *
 * @since 3.19.0
 */
final class StatisticsServiceCacheTest extends TestCase
{
    public function testGetCacheFilenameUsesFormIdPrefixAndIsDeterministic(): void
    {
        $a = $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'rating', 'all');
        $b = $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'rating', 'all');

        // Determinism — same inputs, same filename. Cache hits depend on this.
        self::assertSame($a, $b);

        // Audit #2.1 contract — the per-form clear is a glob("{$formId}-*").
        // If this prefix ever changes, clearCacheForForm() silently no-ops.
        self::assertStringStartsWith(self::TEST_FORM_ID . '-', $a);
        self::assertStringEndsWith('.cache', $a);

        // Varying any of the cache-key components must produce a different
        // filename — otherwise per-field / per-range / per-group cache cells
        // would collide and serve stale data.
        $byField = $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'other', 'all');
        $byRange = $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'rating', 'last7days');
        $byGroup = $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'rating', 'all', 'country');
        $bySite = $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'rating', 'all', null, 2);

        self::assertNotSame($a, $byField);
        self::assertNotSame($a, $byRange);
        self::assertNotSame($a, $byGroup);
        self::assertNotSame($a, $bySite);
    }

    public function testClearCacheForFormDeletesOnlyMatchingFormFiles(): void
    {
        $cachePath = $this->statisticsCachePath();
        FileHelper::createDirectory($cachePath);

        // Seed 2 files for our target form + 1 for a neighbour form (sentinel + 1).
        // Filenames go through getCacheFilename() so this test fails the way it
        // should if the prefix contract drifts.
        $targetA = $cachePath . $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'rating', 'all');
        $targetB = $cachePath . $this->statistics->getCacheFilename(self::TEST_FORM_ID, 'rating', 'last7days');
        $neighbour = $cachePath . $this->statistics->getCacheFilename(self::TEST_FORM_ID + 1, 'rating', 'all');

        try {
            self::assertNotFalse(file_put_contents($targetA, '{"totalResponses":1}'));
            self::assertNotFalse(file_put_contents($targetB, '{"totalResponses":2}'));
            self::assertNotFalse(file_put_contents($neighbour, '{"totalResponses":3}'));

            self::assertTrue($this->statistics->clearCacheForForm(self::TEST_FORM_ID));

            // Audit #2.1 — the OLD bug was `glob("*.cache")` which wiped every
            // form. After the fix, only the target form's files disappear and
            // the neighbour's cache survives.
            self::assertFileDoesNotExist($targetA);
            self::assertFileDoesNotExist($targetB);
            self::assertFileExists($neighbour);
        } finally {
            // Belt-and-braces — cleanupExternalState() handles the sentinel form,
            // but the neighbour file uses formId + 1 so we drop it explicitly.
            @unlink($neighbour);
        }
    }
}
