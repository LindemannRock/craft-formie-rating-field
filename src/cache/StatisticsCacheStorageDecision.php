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
use yii\caching\CacheInterface;

/**
 * Resolved statistics-cache storage for the current host.
 *
 * @since 3.23.0
 */
final readonly class StatisticsCacheStorageDecision
{
    public const EFFECTIVE_APPLICATION = 'application';
    public const EFFECTIVE_DISABLED = 'disabled';
    public const EFFECTIVE_FILE = 'file';

    public function __construct(
        public string $configuredStorage,
        public string $effectiveStorage,
        public bool $ephemeral,
        public CacheBackendStatus $backendStatus,
        public ?CacheInterface $applicationCache,
    ) {
    }

    public function usesApplicationCache(): bool
    {
        return $this->effectiveStorage === self::EFFECTIVE_APPLICATION;
    }

    public function usesFileCache(): bool
    {
        return $this->effectiveStorage === self::EFFECTIVE_FILE;
    }

    public function isDisabled(): bool
    {
        return $this->effectiveStorage === self::EFFECTIVE_DISABLED;
    }
}
