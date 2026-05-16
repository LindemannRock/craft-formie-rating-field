<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests;

use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\services\StatisticsService;

/**
 * Base test case for formie-rating-field integration tests.
 *
 * Layers plugin-specific shorthand on top of the shared {@see IntegrationTestCase}:
 *  - direct accessor for the statistics service
 *  - sentinel test formId (`TEST_FORM_ID`) used as the filename prefix when
 *    writing test cache files, so {@see cleanupExternalState()} can purge them
 *    via a single glob without touching real-form cache entries
 *  - {@see statisticsCachePath()} shorthand
 *
 * @since 3.19.0
 */
abstract class TestCase extends IntegrationTestCase
{
    /**
     * Sentinel formId used as the prefix for any cache files this suite writes.
     *
     * `StatisticsService::getCacheFilename()` prefixes every file with the
     * literal `{$formId}-`, so using a value no real Formie form will ever
     * have lets `cleanupExternalState()` purge test-written files via a single
     * `{TEST_FORM_ID}-*.cache` glob without scanning real cache contents.
     */
    protected const TEST_FORM_ID = 9999999;

    protected StatisticsService $statistics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statistics = FormieRatingField::$plugin->statistics;
    }

    /**
     * Wipe any cache files this suite wrote. Runs from
     * {@see IntegrationTestCase::tearDown()} BEFORE component restoration.
     */
    protected function cleanupExternalState(): void
    {
        $files = glob($this->statisticsCachePath() . self::TEST_FORM_ID . '-*.cache') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * Absolute filesystem path to the plugin's statistics cache directory,
     * matching `StatisticsService::getCachePath()`.
     */
    protected function statisticsCachePath(): string
    {
        return PluginHelper::getCachePath(FormieRatingField::$plugin, 'statistics');
    }
}
