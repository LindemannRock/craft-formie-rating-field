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
use lindemannrock\formieratingfield\traits\FormieSubmissionPermissionTrait;
use lindemannrock\formieratingfield\widgets\RatingStatisticsWidget;
use ReflectionMethod;
use verbb\formie\elements\Form;
use yii\web\ForbiddenHttpException;

/**
 * Verifies Formie's global/per-form submission ACL and the statistics wiring.
 *
 * The permission decisions and filtering are exercised behaviorally against
 * Craft's active user component. Small source-order assertions pin the wiring
 * because the integration bootstrap is a console application and cannot issue
 * real CP template/AJAX requests through every web action.
 *
 * @since 3.22.0
 */
final class FormieSubmissionPermissionTest extends TestCase
{
    public function testGlobalFormieSubmissionPermissionGrantsEveryForm(): void
    {
        $firstForm = $this->form(101, 'form-a');
        $secondForm = $this->form(202, 'form-b');

        $this->withPermissions([
            'formieRatingField:viewStatistics',
            'formie-viewSubmissions',
        ], function() use ($firstForm, $secondForm): void {
            $acl = new FormieSubmissionPermissionHarness();

            self::assertTrue($acl->canView($firstForm));
            self::assertTrue($acl->canView($secondForm));
            self::assertSame(
                ['form-a', 'form-b'],
                $this->filteredFormUids($acl, [$firstForm, $secondForm]),
            );

            $acl->requireAccess($firstForm);
            $this->addToAssertionCount(1);
        });
    }

    public function testPerFormPermissionGrantsOnlyMatchingForm(): void
    {
        $allowedForm = $this->form(101, 'form-a');
        $deniedForm = $this->form(202, 'form-b');

        $this->withPermissions([
            'formieRatingField:viewStatistics',
            'formie-viewSubmissions:form-a',
        ], function() use ($allowedForm, $deniedForm): void {
            $acl = new FormieSubmissionPermissionHarness();

            self::assertTrue($acl->canView($allowedForm));
            self::assertFalse($acl->canView($deniedForm));
            self::assertSame(['form-a'], $this->filteredFormUids($acl, [$allowedForm, $deniedForm]));

            try {
                $acl->requireAccess($deniedForm);
                self::fail('The unrelated form must be denied.');
            } catch (ForbiddenHttpException $exception) {
                self::assertSame('', $exception->getMessage());
                self::assertSame(403, $exception->statusCode);
            }
        });
    }

    public function testPluginPermissionAloneDoesNotGrantFormieSubmissionAccess(): void
    {
        $form = $this->form(101, 'form-a');

        $this->withPermissions([
            'formieRatingField:viewStatistics',
        ], function() use ($form): void {
            $acl = new FormieSubmissionPermissionHarness();

            self::assertFalse($acl->canView($form));
            self::assertSame([], $this->filteredFormUids($acl, [$form]));

            $this->expectException(ForbiddenHttpException::class);
            $acl->requireAccess($form);
        });
    }

    public function testFormiePermissionAloneDoesNotBypassPluginControllerGate(): void
    {
        $this->withPermissions([
            'formie-viewSubmissions',
        ], function(): void {
            $controller = new class('statistics', Craft::$app) extends StatisticsController {
                public function requireCpRequest(): void
                {
                }
            };

            $this->expectException(ForbiddenHttpException::class);
            $controller->actionForm();
        });
    }

    public function testEveryFormSpecificActionKeepsBothPermissionLayers(): void
    {
        $permissions = [
            'actionForm' => 'formieRatingField:viewStatistics',
            'actionGroupDetail' => 'formieRatingField:viewStatistics',
            'actionGetData' => 'formieRatingField:viewStatistics',
            'actionClearCache' => 'formieRatingField:refreshStatistics',
            'actionExportGroup' => 'formieRatingField:exportStatistics',
            'actionExport' => 'formieRatingField:exportStatistics',
        ];

        foreach ($permissions as $method => $pluginPermission) {
            $body = $this->methodSource(StatisticsController::class, $method);

            self::assertStringContainsString(
                "\$this->requirePermission('$pluginPermission');",
                $body,
                "$method must keep its plugin permission gate.",
            );
            self::assertStringContainsString(
                '$this->requireFormieSubmissionAccess($form);',
                $body,
                "$method must also enforce Formie's form ACL.",
            );
        }
    }

    public function testIndexAndWidgetFilterBeforeSearchSortOrSlice(): void
    {
        $indexBody = $this->methodSource(StatisticsController::class, 'actionIndex');
        $indexLoad = strpos($indexBody, 'getFormsWithRatingFields($siteId)');
        $indexAcl = strpos($indexBody, 'filterFormsByFormieSubmissionAccess($formsWithRatings)');
        $indexSearch = strpos($indexBody, "if (\$search !== '')");

        self::assertIsInt($indexLoad);
        self::assertIsInt($indexAcl);
        self::assertIsInt($indexSearch);
        self::assertLessThan($indexAcl, $indexLoad);
        self::assertLessThan($indexSearch, $indexAcl);
        self::assertStringContainsString(
            "checkPermission('formieRatingField:viewStatistics')",
            $indexBody,
            'The index must keep the plugin-level view permission.',
        );

        $widgetBody = $this->methodSource(RatingStatisticsWidget::class, 'getBodyHtml');
        $widgetPluginGate = strpos($widgetBody, "checkPermission('formieRatingField:viewStatistics')");
        $widgetLoad = strpos($widgetBody, 'getFormsWithRatingFields($this->effectiveSiteId())');
        $widgetAcl = strpos($widgetBody, 'filterFormsByFormieSubmissionAccess($forms)');
        $widgetSort = strpos($widgetBody, 'usort($forms');
        $widgetSlice = strpos($widgetBody, 'array_slice($forms');

        self::assertIsInt($widgetPluginGate);
        self::assertIsInt($widgetLoad);
        self::assertIsInt($widgetAcl);
        self::assertIsInt($widgetSort);
        self::assertIsInt($widgetSlice);
        self::assertLessThan($widgetLoad, $widgetPluginGate);
        self::assertLessThan($widgetAcl, $widgetLoad);
        self::assertLessThan($widgetSort, $widgetAcl);
        self::assertLessThan($widgetSlice, $widgetSort);
    }

    /**
     * @param list<Form> $forms
     * @return list<string>
     */
    private function filteredFormUids(FormieSubmissionPermissionHarness $acl, array $forms): array
    {
        $items = array_map(static fn(Form $form): array => [
            'form' => $form,
            'ratingFieldCount' => 1,
            'totalSubmissions' => 10,
        ], $forms);

        return array_map(
            static fn(array $item): string => (string) $item['form']->uid,
            $acl->filter($items),
        );
    }

    private function form(int $id, string $uid): Form
    {
        $form = new Form();
        $form->id = $id;
        $form->uid = $uid;

        return $form;
    }

    /**
     * @param list<string> $permissions
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withPermissions(array $permissions, callable $callback): mixed
    {
        $originalUser = Craft::$app->getUser();
        Craft::$app->set('user', new FormieSubmissionPermissionUser($permissions));

        try {
            return $callback();
        } finally {
            Craft::$app->set('user', $originalUser);
        }
    }

    /**
     * @param class-string $class
     */
    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
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
 * Test harness exposing the trait's protected behavioral contract.
 *
 * @since 3.22.0
 */
final class FormieSubmissionPermissionHarness
{
    use FormieSubmissionPermissionTrait;

    public function canView(Form $form): bool
    {
        return $this->canViewFormieSubmissions($form);
    }

    public function requireAccess(Form $form): void
    {
        $this->requireFormieSubmissionAccess($form);
    }

    /**
     * @param array<int, array{form: Form, ratingFieldCount: int, totalSubmissions: int|null}> $forms
     * @return array<int, array{form: Form, ratingFieldCount: int, totalSubmissions: int|null}>
     */
    public function filter(array $forms): array
    {
        return $this->filterFormsByFormieSubmissionAccess($forms);
    }
}

/**
 * Craft user component with an explicit permission set.
 *
 * @since 3.22.0
 */
final class FormieSubmissionPermissionUser extends ConsoleUser
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(private readonly array $permissions)
    {
        parent::__construct();
    }

    public function checkPermission(string $permissionName): bool
    {
        return in_array($permissionName, $this->permissions, true);
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
