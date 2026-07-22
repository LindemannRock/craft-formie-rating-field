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
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use lindemannrock\formieratingfield\widgets\RatingStatisticsWidget;
use lindemannrock\formieratingfield\widgets\SiteFilterTrait;

/**
 * Covers render-time dashboard-widget site authorization.
 *
 * @since 3.22.0
 */
final class WidgetSiteAccessTest extends TestCase
{
    private ?object $originalUser = null;

    protected function tearDown(): void
    {
        if ($this->originalUser !== null) {
            Craft::$app->set('user', $this->originalUser);
        }

        parent::tearDown();
    }

    public function testAllAndSpecificScopesUseTheLiveEditableSiteList(): void
    {
        $filter = new SiteFilterHarness();
        $filter->editableIds = [7, 9];

        $filter->siteId = 'all';
        self::assertSame([7, 9], $filter->effective());

        $filter->siteId = '9';
        self::assertSame(9, $filter->effective());

        $filter->editableIds = [7];
        self::assertSame([], $filter->effective());
    }

    public function testUnavailableStoredValuesFailClosed(): void
    {
        $filter = new SiteFilterHarness();
        $filter->editableIds = [7];

        foreach (['9', 'deleted', '7x', '-7', '', '0'] as $storedValue) {
            $filter->siteId = $storedValue;
            self::assertSame([], $filter->effective(), "Stored site value {$storedValue} must fail closed.");
        }
    }

    public function testUnavailableSiteScopeCannotReachStatisticsServiceOrRenderedLinks(): void
    {
        $this->installAuthorizedUser();
        $statistics = new CapturingSiteScopeStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $statistics);
        $widget = new SiteAccessWidget(['siteId' => '9']);
        $widget->editableIds = [7];

        $html = $widget->getBodyHtml();

        self::assertSame([], $statistics->receivedScopes);
        self::assertIsString($html);
        self::assertStringContainsString('Statistics are unavailable for the saved site selection.', $html);
        self::assertStringNotContainsString('Should only render when the service is called', $html);
        self::assertStringNotContainsString('formie-rating-field/statistics', $html);
        self::assertStringNotContainsString('siteId=9', $html);
    }

    public function testAuthorizedSpecificSiteReachesStatisticsServiceAsInteger(): void
    {
        $this->installAuthorizedUser();
        $statistics = new CapturingSiteScopeStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $statistics);
        $widget = new SiteAccessWidget(['siteId' => '7']);
        $widget->editableIds = [7, 9];

        $html = $widget->getBodyHtml();

        self::assertSame([7], $statistics->receivedScopes);
        self::assertIsString($html);
        self::assertStringContainsString('Should only render when the service is called', $html);
        self::assertStringContainsString('siteId=7', $html);
    }

    public function testAllScopeReachesStatisticsServiceAsLiveEditableSiteList(): void
    {
        $this->installAuthorizedUser();
        $statistics = new CapturingSiteScopeStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $statistics);
        $widget = new SiteAccessWidget(['siteId' => 'all']);
        $widget->editableIds = [7, 9];

        $html = $widget->getBodyHtml();

        self::assertSame([[7, 9]], $statistics->receivedScopes);
        self::assertIsString($html);
        self::assertStringContainsString('Should only render when the service is called', $html);
        self::assertStringNotContainsString('siteId%5B', $html);
    }

    private function installAuthorizedUser(): void
    {
        $this->originalUser ??= Craft::$app->get('user');
        Craft::$app->set('user', new WidgetSiteAccessUser());
    }
}

final class SiteFilterHarness
{
    use SiteFilterTrait;

    /** @var list<int> */
    public array $editableIds = [];

    public function effective(): int|array
    {
        return $this->effectiveSiteId();
    }

    protected function editableSiteIds(): array
    {
        return $this->editableIds;
    }
}

final class SiteAccessWidget extends RatingStatisticsWidget
{
    /** @var list<int> */
    public array $editableIds = [];

    protected function editableSiteIds(): array
    {
        return $this->editableIds;
    }
}

final class CapturingSiteScopeStatisticsService extends StatisticsService
{
    /** @var list<int|string|array<int>> */
    public array $receivedScopes = [];

    public function getFormsWithRatingFields(int|string|array $siteId = 'all'): array
    {
        $this->receivedScopes[] = $siteId;

        $form = new \verbb\formie\elements\Form();
        $form->id = 123;
        $form->uid = 'widget-site-access-form';
        $form->title = 'Should only render when the service is called';

        return [[
            'form' => $form,
            'ratingFieldCount' => 1,
            'totalSubmissions' => 10,
        ]];
    }
}

final class WidgetSiteAccessUser extends ConsoleUser
{
    public function checkPermission(string $permissionName): bool
    {
        return in_array($permissionName, [
            'formieRatingField:viewStatistics',
            'formie-viewSubmissions',
        ], true);
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
