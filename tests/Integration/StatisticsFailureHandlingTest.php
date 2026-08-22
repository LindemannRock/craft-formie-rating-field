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
use craft\console\Request as ConsoleRequest;
use craft\console\User as ConsoleUser;
use lindemannrock\formieratingfield\controllers\StatisticsController;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use lindemannrock\formieratingfield\widgets\RatingStatisticsWidget;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use verbb\formie\elements\Form;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Verifies friendly failure isolation for statistics widgets and CP detail pages.
 *
 * @since 3.22.0
 */
final class StatisticsFailureHandlingTest extends TestCase
{
    private ?object $originalRequest = null;
    private ?object $originalResponse = null;
    private ?object $originalUser = null;
    private ?bool $originalDevMode = null;
    private ?int $originalLogFlushInterval = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLogFlushInterval = Craft::getLogger()->flushInterval;
        Craft::getLogger()->flushInterval = 0;
    }

    protected function tearDown(): void
    {
        if ($this->originalRequest !== null) {
            Craft::$app->set('request', $this->originalRequest);
        }
        if ($this->originalResponse !== null) {
            Craft::$app->set('response', $this->originalResponse);
        }
        if ($this->originalUser !== null) {
            Craft::$app->set('user', $this->originalUser);
        }
        if ($this->originalDevMode !== null) {
            Craft::$app->getConfig()->getGeneral()->devMode = $this->originalDevMode;
        }
        if ($this->originalLogFlushInterval !== null) {
            Craft::getLogger()->flushInterval = $this->originalLogFlushInterval;
        }

        parent::tearDown();
    }

    public function testWidgetPermissionDeniedStateIsDistinct(): void
    {
        $this->installUser([]);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => 'forms', 'message' => 'must-not-load']),
        );

        $html = $this->widget()->getBodyHtml();

        self::assertIsString($html);
        self::assertStringContainsString('You do not have permission to view statistics.', $html);
        self::assertStringNotContainsString('No forms with rating fields found.', $html);
        self::assertStringNotContainsString('must-not-load', $html);
    }

    public function testAuthorizedWidgetEmptyStateRemainsDistinct(): void
    {
        $this->installUser(['formieRatingField:viewStatistics']);
        $this->swapPluginComponent('formie-rating-field', 'statistics', new EmptyStatisticsService());

        $html = $this->widget()->getBodyHtml();

        self::assertIsString($html);
        self::assertStringContainsString('No forms with rating fields found.', $html);
        self::assertStringNotContainsString('You do not have permission to view statistics.', $html);
        self::assertStringNotContainsString('An error occurred. Please check the logs for details.', $html);
    }

    public function testAuthorizedWidgetServiceFailureIsFriendlyInProduction(): void
    {
        $this->installUser(['formieRatingField:viewStatistics']);
        $this->setDevMode(false);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => 'forms', 'message' => 'widget-raw-secret']),
        );
        $logOffset = count(Craft::getLogger()->messages);

        $html = $this->widget()->getBodyHtml();

        self::assertIsString($html);
        self::assertStringContainsString('An error occurred. Please check the logs for details.', $html);
        self::assertStringNotContainsString('widget-raw-secret', $html);
        self::assertStringNotContainsString('No forms with rating fields found.', $html);
        $this->assertExceptionLogged($logOffset, RatingStatisticsWidget::class . '::getBodyHtml', 'widget-raw-secret');
    }

    public function testAuthorizedWidgetServiceFailureShowsRawMessageInDevMode(): void
    {
        $this->installUser(['formieRatingField:viewStatistics']);
        $this->setDevMode(true);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => 'forms', 'message' => 'widget-dev-detail']),
        );

        $html = $this->widget()->getBodyHtml();

        self::assertIsString($html);
        self::assertStringContainsString('widget-dev-detail', $html);
        self::assertStringNotContainsString('An error occurred. Please check the logs for details.', $html);
    }

    public function testIndexServiceFailureIsLoggedFlashedAndRedirected(): void
    {
        $this->installWebHarness();
        $this->installUser(['formieRatingField:viewStatistics']);
        $this->setDevMode(false);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => 'forms', 'message' => 'index-raw-secret']),
        );
        $logOffset = count(Craft::getLogger()->messages);

        $controller = $this->controller();
        $response = $controller->actionIndex();

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('dashboard', (string) $response->getHeaders()->get('Location'));
        self::assertSame('An error occurred. Please check the logs for details.', $controller->errorMessage);
        self::assertStringNotContainsString('index-raw-secret', (string) $controller->errorMessage);
        $this->assertExceptionLogged(
            $logOffset,
            StatisticsController::class . '::actionIndex',
            'index-raw-secret',
        );
    }

    public function testIndexServiceFailureShowsRawMessageInDevMode(): void
    {
        $this->installWebHarness();
        $this->installUser(['formieRatingField:viewStatistics']);
        $this->setDevMode(true);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => 'forms', 'message' => 'index-dev-detail']),
        );

        $controller = $this->controller();
        $controller->actionIndex();

        self::assertSame('index-dev-detail', $controller->errorMessage);
    }

    #[DataProvider('controllerFailureProvider')]
    public function testControllerServiceFailuresAreLoggedFlashedAndRedirected(
        string $action,
        string $throwOn,
        string $rawMessage,
        string $expectedPath,
    ): void {
        $form = $this->seedForm();
        $this->installWebHarness([
            'dateRange' => 'last7days',
            'groupBy' => 'product',
            'fieldHandle' => 'rating',
        ]);
        $this->installUser([
            'formieRatingField:viewStatistics',
            'formie-viewSubmissions',
        ]);
        $this->setDevMode(false);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => $throwOn, 'message' => $rawMessage]),
        );
        $logOffset = count(Craft::getLogger()->messages);

        $controller = $this->controller();
        $response = $action === 'form'
            ? $controller->actionForm((int) $form->id)
            : $controller->actionGroupDetail((int) $form->id, 'Books');

        self::assertSame(302, $response->getStatusCode());
        $location = (string) $response->getHeaders()->get('Location');
        self::assertStringContainsString($expectedPath, $location);
        self::assertStringNotContainsString('group-detail', $location);
        self::assertSame('An error occurred. Please check the logs for details.', $controller->errorMessage);
        self::assertStringNotContainsString($rawMessage, $location);
        self::assertStringNotContainsString($rawMessage, (string) $controller->errorMessage);
        $this->assertExceptionLogged(
            $logOffset,
            StatisticsController::class . ($action === 'form' ? '::actionForm' : '::actionGroupDetail'),
            $rawMessage,
        );

        if ($action === 'group') {
            self::assertStringContainsString('dateRange=last7days', $location);
            self::assertStringContainsString('groupBy=product', $location);
            self::assertStringContainsString('field=rating', $location);
            self::assertStringContainsString('siteId=all', $location);
        }
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function controllerFailureProvider(): iterable
    {
        yield 'form statistics' => [
            'form',
            'ratingFields',
            'form-raw-secret',
            'formie-rating-field/statistics',
        ];
        yield 'group detail' => [
            'group',
            'groupableFields',
            'group-raw-secret',
            'formie-rating-field/statistics/form/',
        ];
    }

    #[DataProvider('controllerDevModeProvider')]
    public function testControllerDevModeUsesRawExceptionMessage(string $action, string $throwOn, string $rawMessage): void
    {
        $form = $this->seedForm();
        $this->installWebHarness(['groupBy' => 'product']);
        $this->installUser([
            'formieRatingField:viewStatistics',
            'formie-viewSubmissions',
        ]);
        $this->setDevMode(true);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => $throwOn, 'message' => $rawMessage]),
        );

        $controller = $this->controller();
        if ($action === 'form') {
            $controller->actionForm((int) $form->id);
        } else {
            $controller->actionGroupDetail((int) $form->id, 'Books');
        }

        self::assertSame($rawMessage, $controller->errorMessage);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function controllerDevModeProvider(): iterable
    {
        yield 'form statistics' => ['form', 'ratingFields', 'form-dev-detail'];
        yield 'group detail' => ['group', 'groupableFields', 'group-dev-detail'];
    }

    public function testMissingFormAndMalformedRequiredParametersRemainHttpErrors(): void
    {
        $this->installWebHarness();
        $this->installUser([
            'formieRatingField:viewStatistics',
            'formie-viewSubmissions',
        ]);
        $this->swapPluginComponent(
            'formie-rating-field',
            'statistics',
            new ThrowingStatisticsService(['throwOn' => 'forms', 'message' => 'must-not-reach-service']),
        );

        try {
            $this->controller()->actionForm(999999999);
            self::fail('A missing form must remain a 404.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame(404, $exception->statusCode);
            self::assertSame('Form not found', $exception->getMessage());
        }

        $this->expectException(BadRequestHttpException::class);
        $this->controller()->actionGroupDetail(null, null);
    }

    public function testControllerAndWidgetTryBoundariesPreserveAllAccessGates(): void
    {
        $indexSource = $this->methodSource(StatisticsController::class, 'actionIndex');
        $indexTry = strpos($indexSource, 'try {');
        self::assertIsInt($indexTry);
        foreach ([
            '$this->requireCpRequest();',
            "checkPermission('formieRatingField:viewStatistics')",
            '$this->_resolveSiteId(',
        ] as $needle) {
            $gate = strpos($indexSource, $needle);
            self::assertIsInt($gate, "actionIndex must keep the {$needle} gate.");
            self::assertLessThan($indexTry, $gate, "actionIndex must run {$needle} before its try block.");
        }
        foreach ([
            'getFormsWithRatingFields($siteScope)',
            'filterFormsByFormieSubmissionAccess($formsWithRatings)',
            'usort($formsWithRatings',
            'array_slice($formsWithRatings',
            "renderTemplate('formie-rating-field/statistics/index'",
        ] as $needle) {
            $operation = strpos($indexSource, $needle);
            self::assertIsInt($operation);
            self::assertGreaterThan($indexTry, $operation);
        }
        self::assertStringContainsString("return \$this->redirect('dashboard');", $indexSource);

        foreach (['actionForm', 'actionGroupDetail'] as $method) {
            $source = $this->methodSource(StatisticsController::class, $method);
            $try = strpos($source, 'try {');
            $service = strpos($source, 'FormieRatingField::$plugin->statistics', $try ?: 0);
            $render = strpos($source, 'return $this->renderTemplate(', $try ?: 0);

            self::assertIsInt($try);
            self::assertIsInt($service);
            self::assertIsInt($render);
            $gateNeedles = [
                '$this->requireCpRequest();',
                '$this->requirePermission(\'formieRatingField:viewStatistics\');',
                'Form::find()->id($formId)->one()',
                'if (!$form instanceof Form)',
                '$this->requireFormieSubmissionAccess($form);',
                '$this->_resolveSiteId(',
            ];
            $gateNeedles[] = $method === 'actionForm'
                ? 'if (!$formId)'
                : "if (!\$formId || \$groupValue === null || \$groupValue === '')";

            foreach ($gateNeedles as $needle) {
                $gate = strpos($source, $needle);
                self::assertIsInt($gate, "{$method} must keep the {$needle} gate.");
                self::assertLessThan($try, $gate, "{$method} must run {$needle} before its try block.");
            }
            if ($method === 'actionGroupDetail') {
                $groupByGate = strpos($source, 'if (!is_string($groupBy)');
                self::assertIsInt($groupByGate);
                self::assertLessThan($try, $groupByGate);
            }
            self::assertGreaterThan($try, $service);
            self::assertGreaterThan($try, $render);
        }

        $flashSource = $this->methodSource(StatisticsController::class, 'setStatisticsError');
        self::assertStringContainsString('Craft::$app->getSession()->setError($message);', $flashSource);

        $widgetSource = $this->methodSource(RatingStatisticsWidget::class, 'getBodyHtml');
        $widgetTry = strpos($widgetSource, 'try {');
        $widgetPermission = strpos($widgetSource, "checkPermission('formieRatingField:viewStatistics')");
        self::assertIsInt($widgetTry);
        self::assertIsInt($widgetPermission);
        self::assertLessThan($widgetTry, $widgetPermission);
        foreach ([
            'getFormsWithRatingFields(',
            'filterFormsByFormieSubmissionAccess(',
            'usort($forms',
            'array_slice($forms',
            "renderTemplate('formie-rating-field/widgets/rating-statistics/body'",
        ] as $needle) {
            self::assertGreaterThan($widgetTry, strpos($widgetSource, $needle));
        }
    }

    private function seedForm(): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Rating failure test ', 'form');
        $form->handle = $this->nextTestMarker('ratingFailureTest', 'form');
        $this->saveTestForm($form);

        return $form;
    }

    /** @param array<string, mixed> $queryParams */
    private function installWebHarness(array $queryParams = []): void
    {
        $this->originalRequest ??= Craft::$app->get('request');
        $this->originalResponse ??= Craft::$app->get('response');
        Craft::$app->set('request', new StatisticsTestRequest($queryParams));
        Craft::$app->set('response', new Response());
    }

    /** @param list<string> $permissions */
    private function installUser(array $permissions): void
    {
        $this->originalUser ??= Craft::$app->get('user');
        Craft::$app->set('user', new StatisticsTestUser($permissions));
    }

    private function setDevMode(bool $devMode): void
    {
        $this->originalDevMode ??= (bool) Craft::$app->getConfig()->getGeneral()->devMode;
        Craft::$app->getConfig()->getGeneral()->devMode = $devMode;
    }

    private function widget(): RatingStatisticsWidget
    {
        return new class([ 'siteId' => (string) Craft::$app->getSites()->getPrimarySite()->id, ]) extends RatingStatisticsWidget {
            protected function editableSiteIds(): array
            {
                return [(int)Craft::$app->getSites()->getPrimarySite()->id];
            }
        };
    }

    private function controller(): StatisticsFailureTestController
    {
        return new StatisticsFailureTestController('statistics', FormieRatingField::$plugin);
    }

    private function assertExceptionLogged(int $offset, string $category, string $rawMessage): void
    {
        $messages = array_slice(Craft::getLogger()->messages, $offset);
        foreach ($messages as $message) {
            if (($message[2] ?? null) !== $category) {
                continue;
            }

            self::assertStringContainsString($rawMessage, (string) ($message[0] ?? ''));
            self::assertStringContainsString('Stack trace:', (string) ($message[0] ?? ''));
            return;
        }

        self::fail("Expected a complete exception log in category {$category}.");
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

final class StatisticsFailureTestController extends StatisticsController
{
    public ?string $errorMessage = null;

    public function requireCpRequest(): void
    {
    }

    protected function setStatisticsError(string $message): void
    {
        $this->errorMessage = $message;
    }
}

final class StatisticsTestRequest extends ConsoleRequest
{
    /** @param array<string, mixed> $queryParams */
    public function __construct(private readonly array $queryParams = [], array $config = [])
    {
        parent::__construct($config);
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return $this->queryParams[$name] ?? $defaultValue;
    }

    public function getIsAjax(): bool
    {
        return false;
    }

    public function getHostInfo()
    {
        return 'https://formie-rating-field-fixture.example.test';
    }
}

final class StatisticsTestUser extends ConsoleUser
{
    /** @param list<string> $permissions */
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

final class EmptyStatisticsService extends StatisticsService
{
    public function getFormsWithRatingFields(int|string|array $siteId = 'all'): array
    {
        return [];
    }
}

final class ThrowingStatisticsService extends StatisticsService
{
    public string $throwOn = 'forms';
    public string $message = 'statistics failure';

    public function getFormsWithRatingFields(int|string|array $siteId = 'all'): array
    {
        $this->failWhen('forms');

        return [];
    }

    public function getRatingFieldsForForm(Form $form): array
    {
        $this->failWhen('ratingFields');

        return [];
    }

    public function getGroupableFieldsForForm(Form $form): array
    {
        $this->failWhen('groupableFields');

        return [];
    }

    private function failWhen(string $method): void
    {
        if ($this->throwOn === $method) {
            throw new RuntimeException($this->message);
        }
    }
}
