<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Support;

use Composer\InstalledVersions;
use ReflectionClass;

/**
 * Resolves Base test resources from the Composer-installed package.
 *
 * @since 3.22.0
 */
final class InstalledBasePackage
{
    private const PACKAGE_NAME = 'lindemannrock/craft-plugin-base';

    public static function name(): string
    {
        self::packageRoot();

        return self::PACKAGE_NAME;
    }

    public static function sourceFile(string $relativePath): string
    {
        $sourceRoot = self::sourceRoot();
        $path = realpath($sourceRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));

        if (!is_string($path) || !is_file($path)) {
            throw new \RuntimeException(sprintf(
                'Required Base package source resource is absent: src/%s.',
                ltrim($relativePath, '/\\'),
            ));
        }

        if (!str_starts_with($path, $sourceRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException(sprintf(
                'Base package source resource resolved outside the installed package: %s.',
                $relativePath,
            ));
        }

        return $path;
    }

    /**
     * @param class-string $class
     */
    public static function reflectedClassFile(string $class): string
    {
        $filename = (new ReflectionClass($class))->getFileName();
        $path = is_string($filename) ? realpath($filename) : false;

        if (!is_string($path) || !is_file($path)) {
            throw new \RuntimeException(sprintf('Loaded Base class has no readable source file: %s.', $class));
        }

        $sourceRoot = self::sourceRoot();
        if (!str_starts_with($path, $sourceRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException(sprintf(
                'Loaded Base class %s does not belong to the Composer-installed Base package at %s.',
                $class,
                self::packageRoot(),
            ));
        }

        return $path;
    }

    private static function sourceRoot(): string
    {
        $sourceRoot = realpath(self::packageRoot() . '/src');

        if (!is_string($sourceRoot) || !is_dir($sourceRoot)) {
            throw new \RuntimeException('Composer-installed Base package source directory is absent.');
        }

        return $sourceRoot;
    }

    private static function packageRoot(): string
    {
        if (!InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            throw new \RuntimeException(sprintf('Required Composer package is not installed: %s.', self::PACKAGE_NAME));
        }

        $installPath = InstalledVersions::getInstallPath(self::PACKAGE_NAME);
        $packageRoot = is_string($installPath) ? realpath($installPath) : false;

        if (!is_string($packageRoot) || !is_dir($packageRoot)) {
            throw new \RuntimeException(sprintf(
                'Composer did not provide a readable install path for %s.',
                self::PACKAGE_NAME,
            ));
        }

        $composerFile = $packageRoot . '/composer.json';
        $composerContents = is_file($composerFile) ? file_get_contents($composerFile) : false;
        if (!is_string($composerContents)) {
            throw new \RuntimeException(sprintf(
                'Composer metadata is absent from the installed Base package: %s.',
                $composerFile,
            ));
        }

        try {
            $composer = json_decode($composerContents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Installed Base package Composer metadata is invalid.', previous: $exception);
        }

        if (!is_array($composer) || ($composer['name'] ?? null) !== self::PACKAGE_NAME) {
            throw new \RuntimeException(sprintf(
                'Composer install path does not contain the expected package %s.',
                self::PACKAGE_NAME,
            ));
        }

        return $packageRoot;
    }
}
