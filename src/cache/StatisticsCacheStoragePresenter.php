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

/**
 * Maps a statistics storage decision to safe, translatable presentation data.
 *
 * @since 3.23.0
 */
final class StatisticsCacheStoragePresenter
{
    /**
     * @return array{
     *     configuredLabel: string,
     *     effectiveLabel: string,
     *     backendLabel: string,
     *     explanation: string,
     *     statusType: 'success'|'info'|'warning',
     *     filePath: string|null,
     *     usesFile: bool,
     *     showBackend: bool,
     *     differs: bool
     * }
     */
    public function present(StatisticsCacheStorageDecision $decision, ?string $filePath = null): array
    {
        $configuredLabel = $decision->configuredStorage === 'file'
            ? 'Local File Cache'
            : 'Craft Application Cache';
        $effectiveLabel = match ($decision->effectiveStorage) {
            StatisticsCacheStorageDecision::EFFECTIVE_FILE => 'Local File Cache',
            StatisticsCacheStorageDecision::EFFECTIVE_APPLICATION => 'Craft Application Cache',
            default => 'Disabled',
        };

        return [
            'configuredLabel' => $configuredLabel,
            'effectiveLabel' => $effectiveLabel,
            'backendLabel' => $this->backendLabel($decision->backendStatus),
            'explanation' => $this->explanation($decision),
            'statusType' => $this->statusType($decision),
            'filePath' => $decision->usesFileCache() ? $filePath : null,
            'usesFile' => $decision->usesFileCache(),
            'showBackend' => !$decision->usesFileCache(),
            'differs' => ($decision->configuredStorage === 'file') !== $decision->usesFileCache()
                || $decision->isDisabled(),
        ];
    }

    public function applicationOptionToken(string $configuredStorage): string
    {
        return in_array($configuredStorage, ['redis', 'craft'], true) ? $configuredStorage : 'craft';
    }

    private function backendLabel(CacheBackendStatus $status): string
    {
        return match ($status->backend) {
            CacheBackendStatus::BACKEND_MANAGED => 'Managed application cache',
            CacheBackendStatus::BACKEND_REDIS => 'Redis application cache',
            CacheBackendStatus::BACKEND_DATABASE => 'Database application cache',
            CacheBackendStatus::BACKEND_FILESYSTEM => 'Filesystem application cache',
            CacheBackendStatus::BACKEND_MEMORY => 'Request-local memory cache',
            CacheBackendStatus::BACKEND_UNKNOWN => 'Unknown application cache',
            default => 'Unavailable cache',
        };
    }

    private function explanation(StatisticsCacheStorageDecision $decision): string
    {
        if ($decision->usesFileCache()) {
            return 'Statistics are stored in the local runtime cache directory.';
        }

        if ($decision->configuredStorage === 'file' && $decision->ephemeral) {
            return $decision->usesApplicationCache()
                ? 'Local file caching is bypassed because this host has an ephemeral filesystem. Statistics use Craft\'s application cache instead.'
                : 'Local file caching is bypassed because this host has an ephemeral filesystem. No suitable application cache is available, so statistics are recomputed when needed.';
        }

        if ($decision->isDisabled()) {
            return 'No suitable Craft application cache is available, so statistics are recomputed when needed.';
        }

        if ($decision->backendStatus->backend === CacheBackendStatus::BACKEND_UNKNOWN) {
            return 'Statistics use Craft\'s application cache on a best-effort basis because cross-request persistence could not be confirmed.';
        }

        return 'Statistics use Craft\'s application cache.';
    }

    /** @return 'success'|'info'|'warning' */
    private function statusType(StatisticsCacheStorageDecision $decision): string
    {
        if ($decision->isDisabled()) {
            return 'warning';
        }

        if ($decision->usesFileCache() || in_array($decision->backendStatus->backend, [
            CacheBackendStatus::BACKEND_FILESYSTEM,
            CacheBackendStatus::BACKEND_UNKNOWN,
        ], true)) {
            return 'info';
        }

        return 'success';
    }
}
