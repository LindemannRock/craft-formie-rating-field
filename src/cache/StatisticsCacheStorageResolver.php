<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\cache;

use lindemannrock\base\cache\CacheBackendStatus;
use lindemannrock\base\helpers\PluginHelper;
use yii\caching\CacheInterface;

/**
 * Resolves configured statistics storage to the storage usable on this host.
 *
 * @since 3.22.0
 */
final class StatisticsCacheStorageResolver
{
    private const APPLICATION_CACHE_CONTEXT = 'formie-rating-field:statistics';

    private bool $applicationCacheResolved = false;
    private ?CacheInterface $applicationCache = null;

    public function resolve(string $configuredStorage, bool $ephemeral): StatisticsCacheStorageDecision
    {
        if ($configuredStorage === 'file' && !$ephemeral) {
            return new StatisticsCacheStorageDecision(
                $configuredStorage,
                StatisticsCacheStorageDecision::EFFECTIVE_FILE,
                false,
                CacheBackendStatus::fromCache(null),
                null,
            );
        }

        if (!in_array($configuredStorage, ['file', 'redis', 'craft'], true)) {
            return new StatisticsCacheStorageDecision(
                $configuredStorage,
                StatisticsCacheStorageDecision::EFFECTIVE_DISABLED,
                $ephemeral,
                CacheBackendStatus::fromCache(null),
                null,
            );
        }

        $cache = $this->getApplicationCache();
        $status = CacheBackendStatus::fromCache($cache);
        $effectiveStorage = $cache !== null && $status->supportsCrossRequest($ephemeral)
            ? StatisticsCacheStorageDecision::EFFECTIVE_APPLICATION
            : StatisticsCacheStorageDecision::EFFECTIVE_DISABLED;

        return new StatisticsCacheStorageDecision(
            $configuredStorage,
            $effectiveStorage,
            $ephemeral,
            $status,
            $cache,
        );
    }

    private function getApplicationCache(): ?CacheInterface
    {
        if (!$this->applicationCacheResolved) {
            $this->applicationCache = PluginHelper::getApplicationCacheOrLog(self::APPLICATION_CACHE_CONTEXT);
            $this->applicationCacheResolved = true;
        }

        return $this->applicationCache;
    }
}
