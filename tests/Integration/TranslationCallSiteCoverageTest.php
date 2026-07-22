<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use lindemannrock\formieratingfield\tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Pins literal plugin translation call sites to the English catalogue.
 *
 * @since 3.22.0
 */
final class TranslationCallSiteCoverageTest extends TestCase
{
    public function testEveryLiteralPluginCallSiteExistsInEnglishCatalogue(): void
    {
        $pluginRoot = dirname(__DIR__, 2);
        $english = require $pluginRoot . '/src/translations/en/formie-rating-field.php';
        self::assertIsArray($english);

        $callSites = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot . '/src'));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = $file->getExtension();
            if (!in_array($extension, ['php', 'twig', 'js'], true)) {
                continue;
            }
            if (str_contains($file->getPathname(), '/translations/')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);

            $patterns = match ($extension) {
                'php' => [
                    "/Craft::t\\(\\s*['\"]formie-rating-field['\"]\\s*,\\s*'((?:\\\\'|[^'])*)'/s",
                    '/Craft::t\\(\\s*[\'\"]formie-rating-field[\'\"]\\s*,\\s*"((?:\\\\"|[^"])*)"/s',
                ],
                'twig' => [
                    "/'((?:\\\\'|[^'])*)'\\s*\\|\\s*t\\(\\s*['\"]formie-rating-field['\"]/s",
                    '/"((?:\\\\"|[^"])*)"\\s*\\|\\s*t\\(\\s*[\'\"]formie-rating-field[\'\"]/s',
                ],
                'js' => [
                    "/\\b_t\\(\\s*'((?:\\\\'|[^'])*)'/s",
                    '/\\b_t\\(\\s*"((?:\\\\"|[^"])*)"/s',
                ],
            };

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $source, $matches);
                foreach ($matches[1] ?? [] as $key) {
                    $callSites[stripcslashes($key)][] = $file->getPathname();
                }
            }
        }

        $missing = array_diff_key($callSites, $english);
        self::assertSame([], $missing, 'Missing EN translation keys for call sites: ' . implode(', ', array_keys($missing)));
    }

    public function testAllLocalesMatchEnglishOrderSectionsAndPlaceholders(): void
    {
        $translationRoot = dirname(__DIR__, 2) . '/src/translations';
        $englishPath = $translationRoot . '/en/formie-rating-field.php';
        $english = require $englishPath;
        self::assertIsArray($english);
        $expectedKeys = array_keys($english);
        $expectedSections = $this->translationSections($englishPath);

        foreach (glob($translationRoot . '/*/formie-rating-field.php') ?: [] as $path) {
            $language = basename(dirname($path));
            $translations = require $path;
            self::assertIsArray($translations);
            self::assertSame($expectedKeys, array_keys($translations), "Key order differs in {$language}.");
            self::assertSame($expectedSections, $this->translationSections($path), "Section order differs in {$language}.");

            foreach ($translations as $key => $value) {
                self::assertSame(
                    $this->placeholders($key),
                    $this->placeholders($value),
                    "Placeholder mismatch for {$key} in {$language}.",
                );
            }
        }
    }

    public function testEnglishCatalogueHasOnlyDocumentedNormalizedDuplicateKeys(): void
    {
        $english = require dirname(__DIR__, 2) . '/src/translations/en/formie-rating-field.php';
        self::assertIsArray($english);
        $normalized = [];
        $allowedPairs = [
            'manage settings' => ['Manage settings', 'Manage Settings'],
            'reviews' => ['Reviews', 'reviews'],
            'view statistics' => ['View statistics', 'View Statistics'],
        ];

        foreach (array_keys($english) as $key) {
            $candidate = mb_strtolower((string)preg_replace('/\s+/u', ' ', trim($key)));
            $candidate = (string)preg_replace('/(?:\.\.\.|…|[.!?])$/u', '', $candidate);
            if (isset($normalized[$candidate])) {
                self::assertSame(
                    $allowedPairs[$candidate] ?? null,
                    [$normalized[$candidate], $key],
                    "Undocumented normalized duplicate translation keys: {$normalized[$candidate]} and {$key}.",
                );
            }
            $normalized[$candidate] = $key;
        }
    }

    public function testRetiredKeysRemainAbsentFromEveryLocale(): void
    {
        $retired = [
            'Search',
            'View',
            'Form Name',
            'Clear search',
            'no forms',
            '{count, plural, =1{form} other{forms}}',
            '{start} – {end} of {total} {label}',
            'statistics pagination',
            'Previous Page',
            'Next Page',
            'Unknown export format: {format}',
            'Actions',
            'Manual Only',
            'Every 3 Hours',
            'Every 6 Hours',
            'Every 12 Hours',
            'Daily (Midnight)',
            'Daily at 2am (Low Traffic)',
            'Twice Daily (Midnight & Noon)',
            'Weekly (Sunday Midnight)',
            'All Products',
            'Search products...',
            'Across all groups',
            'Show All',
            'Show Top 10',
        ];

        foreach (glob(dirname(__DIR__, 2) . '/src/translations/*/formie-rating-field.php') ?: [] as $path) {
            $translations = require $path;
            self::assertIsArray($translations);
            foreach ($retired as $key) {
                self::assertArrayNotHasKey($key, $translations, "Retired key remains in {$path}: {$key}");
            }
        }
    }

    public function testTranslationsDoNotAddPeriodsToEnglishFragments(): void
    {
        $translationRoot = dirname(__DIR__, 2) . '/src/translations';
        $english = require $translationRoot . '/en/formie-rating-field.php';
        self::assertIsArray($english);
        $abbreviationExceptions = [
            'da' => ['Avg', 'Max', 'Min'],
        ];

        $violations = [];
        foreach (glob($translationRoot . '/*/formie-rating-field.php') ?: [] as $path) {
            $language = basename(dirname($path));
            $translations = require $path;
            self::assertIsArray($translations);
            foreach ($english as $key => $englishValue) {
                if (preg_match('/[.!?…]$/u', $englishValue) === 1) {
                    continue;
                }
                if (in_array($key, $abbreviationExceptions[$language] ?? [], true)) {
                    continue;
                }
                if (preg_match('/[.!?。？！…]$/u', $translations[$key]) === 1) {
                    $violations[] = "{$language}: {$key} => {$translations[$key]}";
                }
            }
        }

        self::assertSame([], $violations, "Translations add terminal punctuation to fragments:\n" . implode("\n", $violations));
    }

    /** @return list<string> */
    private function translationSections(string $path): array
    {
        $source = file_get_contents($path);
        self::assertIsString($source);
        preg_match_all('/^\s*\/\/\s+(.+)$/m', $source, $matches);

        return array_values($matches[1] ?? []);
    }

    /** @return list<string> */
    private function placeholders(string $value): array
    {
        preg_match_all('/\{[A-Za-z][A-Za-z0-9]*\}/', $value, $matches);
        $placeholders = $matches[0] ?? [];
        sort($placeholders);

        return array_values($placeholders);
    }
}
