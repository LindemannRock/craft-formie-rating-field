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
     *     heading: string,
     *     explanation: string|null,
     *     statusType: 'success'|'info'|'warning',
     *     filePath: string|null,
     *     usesFile: bool,
     *     utilityValue: string,
     *     utilityDescription: string
     * }
     */
    public function present(StatisticsCacheStorageDecision $decision, ?string $filePath = null): array
    {
        if ($decision->isDisabled()) {
            return [
                'heading' => 'Caching disabled',
                'explanation' => 'No suitable cross-request cache is available. Statistics are recomputed as needed.',
                'statusType' => 'warning',
                'filePath' => null,
                'usesFile' => false,
                'utilityValue' => 'Disabled',
                'utilityDescription' => 'Recomputed as needed',
            ];
        }

        if ($decision->usesFileCache()) {
            return [
                'heading' => 'Using file cache',
                'explanation' => null,
                'statusType' => 'success',
                'filePath' => $filePath,
                'usesFile' => true,
                'utilityValue' => 'Active',
                'utilityDescription' => 'File cache · {count} entries',
            ];
        }

        $backend = $decision->backendStatus->backend;
        if ($backend === CacheBackendStatus::BACKEND_UNKNOWN) {
            return [
                'heading' => 'Using application cache',
                'explanation' => 'Cross-request persistence could not be confirmed.',
                'statusType' => 'info',
                'filePath' => null,
                'usesFile' => false,
                'utilityValue' => 'Best effort',
                'utilityDescription' => 'Application cache',
            ];
        }

        [$heading, $utilityDescription] = match ($backend) {
            CacheBackendStatus::BACKEND_MANAGED => ['Using managed cache', 'Managed cache'],
            CacheBackendStatus::BACKEND_REDIS => ['Using Redis cache', 'Redis cache'],
            CacheBackendStatus::BACKEND_DATABASE => ['Using database cache', 'Database cache'],
            CacheBackendStatus::BACKEND_FILESYSTEM => ['Using filesystem cache', 'Filesystem cache'],
            default => ['Using application cache', 'Application cache'],
        };

        return [
            'heading' => $heading,
            'explanation' => $decision->configuredStorage === 'file' && $decision->ephemeral
                ? 'This host has an ephemeral filesystem, so the application cache is used automatically.'
                : null,
            'statusType' => 'success',
            'filePath' => null,
            'usesFile' => false,
            'utilityValue' => 'Active',
            'utilityDescription' => $utilityDescription,
        ];
    }

    public function applicationOptionToken(string $configuredStorage): string
    {
        return in_array($configuredStorage, ['redis', 'craft'], true) ? $configuredStorage : 'craft';
    }
}
