<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

use craft\models\Site;

$projectRoot = $_SERVER['FORMIE_RATING_FIELD_TEST_PROJECT_ROOT']
    ?? $_ENV['FORMIE_RATING_FIELD_TEST_PROJECT_ROOT']
    ?? null;
$projectPattern = '#^' . preg_quote(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), '#')
    . '/formie-rating-field-fixture-[a-f0-9]{16}$#';
if (!is_string($projectRoot) || preg_match($projectPattern, $projectRoot) !== 1) {
    throw new RuntimeException('Site seeding requires the exact disposable project boundary.');
}

require $projectRoot . '/bootstrap.php';
require $projectRoot . '/vendor/craftcms/cms/bootstrap/console.php';

$sites = Craft::$app->getSites();
$primary = $sites->getPrimarySite();
if ($sites->getSiteByHandle('fixtureSecondary') === null) {
    $secondary = new Site([
        'name' => 'Fixture Secondary',
        'handle' => 'fixtureSecondary',
        'language' => 'en-GB',
        'baseUrl' => 'https://formie-rating-field-secondary.example.test',
        'groupId' => $primary->groupId,
        'primary' => false,
        'enabled' => true,
    ]);
    if (!$sites->saveSite($secondary)) {
        throw new RuntimeException('Unable to save fixture site: ' . json_encode($secondary->getErrors()));
    }
}

if (count($sites->getAllSites()) !== 2) {
    throw new RuntimeException('Formie Rating Field fixture must contain exactly two sites.');
}
