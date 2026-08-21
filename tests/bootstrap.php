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

$baseBootstrap = dirname(__DIR__, 3) . '/vendor/lindemannrock/craft-plugin-base/src/testing/bootstrap.php';

if (!file_exists($baseBootstrap)) {
    fwrite(STDERR, "Base plugin testing bootstrap not found at {$baseBootstrap}\n");
    fwrite(STDERR, "Run `composer install` and ensure lindemannrock/craft-plugin-base ^5.0 is present.\n");
    exit(1);
}

require_once $baseBootstrap;

\lindemannrock\base\testing\bootstrap();
