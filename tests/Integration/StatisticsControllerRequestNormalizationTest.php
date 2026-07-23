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
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\formieratingfield\controllers\StatisticsController;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use verbb\formie\elements\Form;
use yii\web\Response;

/**
 * Covers mixed request-value normalization before typed controller sinks.
 *
 * @since 3.22.0
 */
final class StatisticsControllerRequestNormalizationTest extends TestCase
{
    private ?object $originalRequest = null;
    private ?object $originalResponse = null;
    private ?object $originalUser = null;

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

        parent::tearDown();
    }

    #[DataProvider('missingFieldHandleProvider')]
    public function testMissingEmptyAndNonStringFieldHandlesKeepRequiredResponse(mixed $fieldHandle): void
    {
        $form = $this->seedForm();
        $service = new RequestNormalizationStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $this->installHarness(bodyParams: [
            'formId' => $form->id,
            'fieldHandle' => $fieldHandle,
            'type' => 'trend',
        ]);

        $response = $this->controller()->actionGetData();

        self::assertSame([
            'success' => false,
            'error' => 'Field handle is required',
        ], $response->data);
        self::assertSame(0, $service->typedHandleSinkCalls);
        self::assertNull($service->receivedHandle);
    }

    public static function missingFieldHandleProvider(): iterable
    {
        yield 'missing-equivalent null' => [null];
        yield 'empty string' => [''];
        yield 'array' => [['rating']];
    }

    public function testUnknownStringFieldHandleKeepsNotFoundResponseWithoutTypedSinkCall(): void
    {
        $form = $this->seedForm();
        $service = new RequestNormalizationStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $this->installHarness(bodyParams: [
            'formId' => $form->id,
            'fieldHandle' => 'removedRating',
            'type' => 'distribution',
        ]);

        $response = $this->controller()->actionGetData();

        self::assertSame([
            'success' => false,
            'error' => 'Field not found',
        ], $response->data);
        self::assertSame(0, $service->typedHandleSinkCalls);
        self::assertNull($service->receivedHandle);
    }

    public function testCurrentRatingFieldReachesTypedSinkAsString(): void
    {
        $form = $this->seedForm();
        $service = new RequestNormalizationStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $this->installHarness(bodyParams: [
            'formId' => $form->id,
            'fieldHandle' => 'rating',
            'type' => 'trend',
        ]);

        $response = $this->controller()->actionGetData();

        self::assertSame(true, $response->data['success'] ?? null);
        self::assertSame(['safe' => true], $response->data['data'] ?? null);
        self::assertSame(1, $service->typedHandleSinkCalls);
        self::assertSame('rating', $service->receivedHandle);
    }

    public function testGetDataPinsBothTypedSinksToTheRatingFieldNormalizer(): void
    {
        $source = $this->methodSource('actionGetData');

        self::assertStringContainsString(
            "\$fieldHandle = is_string(\$rawFieldHandle) ? \$rawFieldHandle : '';",
            $source,
        );
        self::assertSame(2, substr_count($source, '$this->_normalizeRatingFieldHandle('));
        self::assertSame(2, substr_count($source, '$statisticsService->getRatingFieldByHandle($form, $fieldHandle)'));
        self::assertStringNotContainsString('getRatingFieldByHandle($form, $rawFieldHandle)', $source);

        $offset = 0;
        for ($i = 0; $i < 2; $i++) {
            $normalize = strpos($source, '$this->_normalizeRatingFieldHandle(', $offset);
            $sink = strpos($source, '$statisticsService->getRatingFieldByHandle($form, $fieldHandle)', $offset);
            self::assertIsInt($normalize);
            self::assertIsInt($sink);
            self::assertGreaterThan($normalize, $sink);
            $offset = $sink + 1;
        }
    }

    #[DataProvider('validFormatProvider')]
    public function testValidExportFormatsAndAliasesRetainTheirCanonicalBehavior(string $format, string $canonical): void
    {
        $normalized = $this->normalizeFormat($format);

        self::assertSame($format, $normalized);
        self::assertSame($canonical, ExportHelper::normalizeFormat($normalized));
    }

    public static function validFormatProvider(): iterable
    {
        yield 'csv' => ['csv', 'csv'];
        yield 'json' => ['json', 'json'];
        yield 'excel' => ['excel', 'excel'];
        yield 'xlsx alias' => ['xlsx', 'excel'];
        yield 'xls alias' => ['xls', 'excel'];
    }

    public function testArrayExportFormatNormalizesToRejectedStringAtBothActions(): void
    {
        $format = $this->normalizeFormat(['csv']);

        self::assertSame('', $format);
        self::assertFalse(ExportHelper::isFormatEnabled($format, 'formie-rating-field'));

        foreach (['actionExportGroup', 'actionExport'] as $action) {
            $source = $this->methodSource($action);
            $normalize = strpos(
                $source,
                "\$format = \$this->_normalizeFormat(\$request->getBodyParam('format', 'csv'));",
            );
            $enabledGate = strpos($source, "ExportHelper::isFormatEnabled(\$format, 'formie-rating-field')");

            self::assertIsInt($normalize, "{$action} must normalize format.");
            self::assertIsInt($enabledGate, "{$action} must keep the enabled-format gate.");
            self::assertGreaterThan($normalize, $enabledGate);
            self::assertStringNotContainsString(
                "\$format = \$request->getBodyParam('format', 'csv');",
                $source,
            );

            foreach ([
                'ExportHelper::normalizeFormat($format)',
                'ExportHelper::extensionForFormat($format)',
                'format: $format',
                'ExportHelper::dispatchSections($sections, $format',
            ] as $typedUse) {
                $position = strpos($source, $typedUse);
                if ($position !== false) {
                    self::assertGreaterThan($normalize, $position);
                }
            }
        }
    }

    public function testArrayIndexValuesResolveToEstablishedDefaults(): void
    {
        $service = new RequestNormalizationStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $this->installHarness(queryParams: [
            'search' => ['needle'],
            'sort' => ['title'],
            'dir' => ['asc'],
        ]);
        $controller = $this->controller();

        $controller->actionIndex();

        self::assertSame('', $controller->renderVariables['search'] ?? null);
        self::assertSame('totalSubmissions', $controller->renderVariables['sort'] ?? null);
        self::assertSame('desc', $controller->renderVariables['dir'] ?? null);
    }

    public function testIndexPinsEveryMixedStringReadToAnIsStringGuard(): void
    {
        $source = $this->methodSource('actionIndex');

        self::assertStringContainsString(
            "\$search = is_string(\$rawSearch) ? trim(\$rawSearch) : '';",
            $source,
        );
        self::assertStringContainsString(
            "if (!is_string(\$rawSort) || !in_array(\$rawSort, \$validSortFields, true))",
            $source,
        );
        self::assertStringContainsString(
            "\$dir = is_string(\$rawDir) && strtolower(\$rawDir) === 'asc' ? 'asc' : 'desc';",
            $source,
        );
        self::assertStringNotContainsString("(string) \$request->getQueryParam('search'", $source);
        self::assertStringNotContainsString("(string) \$request->getQueryParam('sort'", $source);
        self::assertStringNotContainsString("(string) \$request->getQueryParam('dir'", $source);
    }

    public function testStatisticsNormalizersHaveCompleteDocblocks(): void
    {
        $parameterCounts = [
            '_resolveSiteId' => 1,
            '_normalizeGroupByHandle' => 2,
            '_normalizeRatingFieldHandle' => 2,
            '_normalizeGroupValue' => 1,
            '_normalizeDateRange' => 2,
            '_normalizeFormat' => 1,
        ];

        foreach ($parameterCounts as $methodName => $parameterCount) {
            $docblock = (new ReflectionMethod(StatisticsController::class, $methodName))->getDocComment();

            self::assertIsString($docblock, "{$methodName} must have a docblock.");
            self::assertSame($parameterCount, substr_count($docblock, '@param '), "{$methodName} must document every parameter.");
            self::assertStringContainsString('@return ', $docblock, "{$methodName} must document its return value.");
        }
    }

    private function seedForm(): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Request normalization ', 'form');
        $form->handle = $this->nextTestMarker('requestNormalization', 'form');
        $this->saveTestElement($form);

        return $form;
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     */
    private function installHarness(array $queryParams = [], array $bodyParams = []): void
    {
        $this->originalRequest ??= Craft::$app->get('request');
        $this->originalResponse ??= Craft::$app->get('response');
        $this->originalUser ??= Craft::$app->get('user');
        Craft::$app->set('request', new RequestNormalizationRequest($queryParams, $bodyParams));
        Craft::$app->set('response', new Response());
        Craft::$app->set('user', new RequestNormalizationUser());
    }

    private function controller(): RequestNormalizationController
    {
        return new RequestNormalizationController('statistics', FormieRatingField::$plugin);
    }

    private function normalizeFormat(mixed $format): string
    {
        $method = new ReflectionMethod(StatisticsController::class, '_normalizeFormat');
        $result = $method->invoke($this->controller(), $format);
        self::assertIsString($result);

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

final class RequestNormalizationController extends StatisticsController
{
    /** @var array<string, mixed> */
    public array $renderVariables = [];

    public function requireCpRequest(): void
    {
    }

    public function requireAcceptsJson(): void
    {
    }

    public function requirePermission(string $permissionName): void
    {
    }

    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): Response
    {
        $this->renderVariables = $variables;

        return Craft::$app->getResponse();
    }

    protected function requireFormieSubmissionAccess(Form $form): void
    {
    }
}

final class RequestNormalizationRequest extends ConsoleRequest
{
    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     */
    public function __construct(
        private readonly array $queryParams,
        private readonly array $bodyParams,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    public function getQueryParam($name, $defaultValue = null): mixed
    {
        return array_key_exists($name, $this->queryParams) ? $this->queryParams[$name] : $defaultValue;
    }

    public function getBodyParam($name, $defaultValue = null): mixed
    {
        return array_key_exists($name, $this->bodyParams) ? $this->bodyParams[$name] : $defaultValue;
    }
}

final class RequestNormalizationUser extends ConsoleUser
{
    public function checkPermission(string $permissionName): bool
    {
        return $permissionName === 'formieRatingField:viewStatistics';
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

final class RequestNormalizationStatisticsService extends StatisticsService
{
    public int $typedHandleSinkCalls = 0;
    public ?string $receivedHandle = null;

    private Rating $ratingField;

    public function __construct(array $config = [])
    {
        $this->ratingField = new Rating([
            'handle' => 'rating',
            'label' => 'Rating',
        ]);

        parent::__construct($config);
    }

    public function getFormsWithRatingFields(int|string|array $siteId = 'all'): array
    {
        return [];
    }

    public function getRatingFieldsForForm(Form $form): array
    {
        return [$this->ratingField];
    }

    public function getRatingFieldByHandle(Form $form, string $handle): ?Rating
    {
        $this->typedHandleSinkCalls++;
        $this->receivedHandle = $handle;

        return $handle === $this->ratingField->handle ? $this->ratingField : null;
    }

    public function getTrendData(Form $form, Rating $field, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        return ['safe' => true];
    }

    public function getDistributionData(Form $form, Rating $field, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        return ['safe' => true];
    }
}
