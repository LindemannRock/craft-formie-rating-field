<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use Composer\Semver\Semver;
use lindemannrock\formieratingfield\tests\TestCase;

/**
 * Protects the minimum Base release supplying every imported shared API.
 *
 * @since 3.22.0
 */
final class PackageDependencyContractTest extends TestCase
{
    public function testCraftFloorMatchesRequiredBaseRelease(): void
    {
        $composer = json_decode(
            (string)file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $constraint = $composer['require']['craftcms/cms'];

        self::assertSame('^5.10', $constraint);
        self::assertFalse(Semver::satisfies('5.9.99', $constraint));
        self::assertTrue(Semver::satisfies('5.10.0', $constraint));
        self::assertTrue(Semver::satisfies('5.99.0', $constraint));
        self::assertFalse(Semver::satisfies('6.0.0', $constraint));
    }

    public function testBaseFloorStartsAtFirstProvenCompatibleRelease(): void
    {
        $composer = json_decode(
            (string)file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $constraint = $composer['require']['lindemannrock/craft-plugin-base'];

        self::assertSame('^5.38', $constraint);
        self::assertFalse(Semver::satisfies('5.37.99', $constraint));
        self::assertTrue(Semver::satisfies('5.38.0', $constraint));
        self::assertTrue(Semver::satisfies('5.99.0', $constraint));
        self::assertFalse(Semver::satisfies('6.0.0', $constraint));
    }
}
