<?php

/**
 * PHPUnit bootstrap for the formie-rating-field plugin.
 *
 * Delegates to the shared base-plugin bootstrap, which initialises Craft as a
 * console application. The permanent Craft queue is hidden by a connection-
 * local temporary table before enabled plugins bootstrap, so tests cannot
 * inspect or mutate owner queue rows. Other database state remains live and is
 * cleaned by exact ownership (see `tests/TestCase.php`).
 *
 * @since 3.19.0
 */

declare(strict_types=1);

use lindemannrock\formieratingfield\tests\Support\IsolatedQueue;

if (!function_exists('craft_modify_app_config')) {
    /** Install the shadow queue before Craft bootstraps enabled plugins. */
    function craft_modify_app_config(array &$config, string $appType): void
    {
        if ($appType !== 'console') {
            throw new \RuntimeException('Formie Rating Field tests require Craft\'s console application.');
        }

        $queueConfig = $config['components']['queue'] ?? [];
        if (!is_array($queueConfig)) {
            throw new \RuntimeException('Formie Rating Field tests require an array-configured Craft queue.');
        }

        $queueConfig['class'] = IsolatedQueue::class;
        $queueConfig['proxyQueue'] = null;
        $config['components']['queue'] = $queueConfig;
    }
}

$baseBootstrapCandidates = [
    dirname(__DIR__) . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php',
    dirname(__DIR__, 3) . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php',
];
$baseBootstrap = null;
foreach ($baseBootstrapCandidates as $candidate) {
    if (is_file($candidate)) {
        $baseBootstrap = $candidate;
        break;
    }
}

if ($baseBootstrap === null) {
    fwrite(STDERR, "Base plugin testing bootstrap not found in the package or workspace vendor tree.\n");
    fwrite(STDERR, "Run `composer install` and ensure lindemannrock/craft-plugin-base ^5.38 is present.\n");
    exit(1);
}

require_once $baseBootstrap;

$projectRoot = $_SERVER['FORMIE_RATING_FIELD_TEST_PROJECT_ROOT']
    ?? $_ENV['FORMIE_RATING_FIELD_TEST_PROJECT_ROOT']
    ?? null;
\lindemannrock\base\testing\bootstrap(is_string($projectRoot) ? $projectRoot : null);
