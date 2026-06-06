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
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\tests\TestCase;
use yii\caching\ArrayCache;

/**
 * Pins the Redis fail-closed contract of {@see \lindemannrock\formieratingfield\services\StatisticsService}.
 *
 * Audit #12.1 — when `cacheStorageMethod=redis` but Craft's cache component is
 * NOT a `yii\redis\Cache` (admin selected "Redis" without configuring a Redis
 * component in `config/app.php`), the Redis read/write paths must fail-closed:
 *
 *  - `getFromCache()` must return `null` (a miss → the caller recomputes), and
 *  - `saveToCache()` must return `false` (skip the write),
 *
 * matching the clear paths' no-op. The pre-fix code used `Craft::$app->cache`
 * directly, so it silently read/wrote whatever cache Craft actually had
 * (File/DB) while the clear paths no-opped — accumulating unclearable,
 * never-invalidated cache entries.
 *
 * @since 3.20.0
 */
final class StatisticsServiceRedisFailClosedTest extends TestCase
{
    public function testRedisModeFailsClosedWhenCacheComponentIsNotRedis(): void
    {
        // Reproduce the misconfig deterministically regardless of the test env's
        // real cache backend: plugin set to "redis" while Craft's actual cache
        // component is a plain (non-Redis) ArrayCache.
        $originalCache = Craft::$app->getCache();
        Craft::$app->set('cache', new ArrayCache());

        $settings = FormieRatingField::$plugin->getSettings();
        $originalMethod = $settings->cacheStorageMethod;
        $settings->cacheStorageMethod = 'redis';

        try {
            $save = new \ReflectionMethod($this->statistics, 'saveToCache');
            $save->setAccessible(true);
            $get = new \ReflectionMethod($this->statistics, 'getFromCache');
            $get->setAccessible(true);

            // Write must be skipped (false), not silently sent to the wrong store.
            $saved = $save->invoke($this->statistics, self::TEST_FORM_ID, 'rating', 'all', null, ['totalResponses' => 1], 'all');
            self::assertFalse($saved, 'saveToCache() must skip the write on a misconfigured Redis cache.');

            // Read must miss (null) so the caller recomputes fresh stats.
            $read = $get->invoke($this->statistics, self::TEST_FORM_ID, 'rating', 'all', null, 'all');
            self::assertNull($read, 'getFromCache() must miss on a misconfigured Redis cache.');
        } finally {
            $settings->cacheStorageMethod = $originalMethod;
            Craft::$app->set('cache', $originalCache);
        }
    }
}
