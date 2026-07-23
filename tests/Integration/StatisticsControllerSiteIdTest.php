<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use Craft;
use craft\console\User as ConsoleUser;
use lindemannrock\formieratingfield\controllers\StatisticsController;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;
use yii\web\ForbiddenHttpException;

/**
 * Covers request-shape normalization and authorization for statistics site scopes.
 *
 * @since 3.22.0
 */
final class StatisticsControllerSiteIdTest extends TestCase
{
    private ?object $originalUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalUser = Craft::$app->get('user');
        Craft::$app->set('user', new StatisticsSiteIdUser());
        Craft::$app->getSites()->refreshSites();
    }

    protected function tearDown(): void
    {
        if ($this->originalUser !== null) {
            Craft::$app->set('user', $this->originalUser);
            Craft::$app->getSites()->refreshSites();
        }

        parent::tearDown();
    }

    public function testQueryArrayResolvesToAllSites(): void
    {
        self::assertSame('all', $this->resolveSiteId(['1']));
    }

    public function testBodyArrayResolvesToAllSites(): void
    {
        self::assertSame('all', $this->resolveSiteId(['site' => '1']));
    }

    public function testNullEmptyStringAndAllResolveToAllSites(): void
    {
        self::assertSame('all', $this->resolveSiteId(null));
        self::assertSame('all', $this->resolveSiteId(''));
        self::assertSame('all', $this->resolveSiteId('all'));
    }

    public function testValidNumericStringResolvesToEditableSiteId(): void
    {
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;

        self::assertSame($siteId, $this->resolveSiteId((string)$siteId));
    }

    public function testForbiddenNumericSiteIdStillThrows(): void
    {
        $forbiddenSiteId = '2147483647';
        self::assertNotContains((int)$forbiddenSiteId, Craft::$app->getSites()->getEditableSiteIds());

        $this->expectException(ForbiddenHttpException::class);
        $this->resolveSiteId($forbiddenSiteId);
    }

    public function testAllSixActionCallSitesDelegateRawSiteInputToResolver(): void
    {
        $calls = [
            'actionIndex' => "\$this->_resolveSiteId(\$request->getQueryParam('siteId'))",
            'actionForm' => "\$this->_resolveSiteId(Craft::\$app->getRequest()->getQueryParam('siteId'))",
            'actionGroupDetail' => "\$this->_resolveSiteId(\$request->getQueryParam('siteId'))",
            'actionGetData' => "\$this->_resolveSiteId(\$request->getBodyParam('siteId'))",
            'actionExportGroup' => "\$this->_resolveSiteId(\$request->getBodyParam('siteId'))",
            'actionExport' => "\$this->_resolveSiteId(\$request->getBodyParam('siteId'))",
        ];

        foreach ($calls as $action => $call) {
            self::assertStringContainsString($call, $this->methodSource($action));
        }
    }

    private function resolveSiteId(mixed $rawSiteId): int|string
    {
        $controller = new StatisticsController('statistics', Craft::$app);
        $method = new ReflectionMethod($controller, '_resolveSiteId');
        $result = $method->invoke($controller, $rawSiteId);
        self::assertTrue(is_int($result) || $result === 'all');

        return $result;
    }

    private function methodSource(string $method): string
    {
        $reflection = new ReflectionMethod(StatisticsController::class, $method);
        $filename = $reflection->getFileName();
        self::assertIsString($filename);

        $lines = file($filename);
        self::assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }
}

/**
 * Craft user component with editable access to every configured site.
 *
 * @since 3.22.0
 */
final class StatisticsSiteIdUser extends ConsoleUser
{
    public function checkPermission(string $permissionName): bool
    {
        return str_starts_with($permissionName, 'editSite:');
    }

    public function getId(): ?int
    {
        return 1;
    }

    public function getIsGuest(): bool
    {
        return false;
    }
}
