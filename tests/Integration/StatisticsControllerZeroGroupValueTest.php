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
use craft\web\Response;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\formieratingfield\controllers\StatisticsController;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use verbb\formie\elements\Form;
use verbb\formie\fields\SingleLineText;
use verbb\formie\models\FieldLayout;
use yii\web\BadRequestHttpException;

/**
 * Covers the literal zero group value across detail and export actions.
 *
 * @since 3.22.0
 */
final class StatisticsControllerZeroGroupValueTest extends TestCase
{
    private ?object $originalRequest = null;
    private ?object $originalResponse = null;

    protected function tearDown(): void
    {
        if ($this->originalRequest !== null) {
            Craft::$app->set('request', $this->originalRequest);
        }
        if ($this->originalResponse !== null) {
            Craft::$app->set('response', $this->originalResponse);
        }

        parent::tearDown();
    }

    public function testZeroReachesPaginatedDetailAndEveryEnabledExportRepresentation(): void
    {
        $form = $this->seedForm();
        $service = new ZeroGroupStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $controller = new ZeroGroupStatisticsController('statistics', FormieRatingField::$plugin);

        $this->installRequest(queryParams: ['groupBy' => 'branch']);
        $detailResponse = $controller->actionGroupDetail((int)$form->id, '0');

        self::assertSame(200, $detailResponse->getStatusCode());
        self::assertSame('0', $controller->renderVariables['groupValue'] ?? null);
        self::assertSame(['0'], $service->paginatedGroupValues);

        $enabledFormats = ExportHelper::getEnabledFormats('formie-rating-field');
        self::assertNotSame([], $enabledFormats);

        foreach ($enabledFormats as $format) {
            $this->installRequest(bodyParams: [
                'formId' => $form->id,
                'groupBy' => 'branch',
                'groupValue' => '0',
                'dateRange' => 'all',
                'format' => $format,
                'siteId' => 'all',
            ]);
            $response = $controller->actionExportGroup();
            $content = $response->content;

            self::assertSame(200, $response->getStatusCode());
            self::assertIsString($content);
            self::assertNotSame('', $content);
            self::assertStringContainsString('attachment;', (string)$response->getHeaders()->get('Content-Disposition'));

            if ($format === 'json') {
                $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('0', $payload['groupValue'] ?? null);
            }
        }

        self::assertSame(
            array_fill(0, count($enabledFormats), '0'),
            $service->exportGroupValues,
        );
        foreach ($service->receivedSiteScopes as $scope) {
            self::assertIsArray($scope);
        }
    }

    public function testEmptyAndArrayGroupValuesRemainRejected(): void
    {
        $form = $this->seedForm();
        $service = new ZeroGroupStatisticsService();
        $this->swapPluginComponent('formie-rating-field', 'statistics', $service);
        $controller = new ZeroGroupStatisticsController('statistics', FormieRatingField::$plugin);
        $rejected = 0;

        foreach (['', ['0']] as $groupValue) {
            $this->installRequest(bodyParams: [
                'formId' => $form->id,
                'groupBy' => 'branch',
                'groupValue' => $groupValue,
                'format' => 'csv',
            ]);

            try {
                $controller->actionExportGroup();
            } catch (BadRequestHttpException) {
                $rejected++;
            }
        }

        self::assertSame(2, $rejected);
        self::assertSame([], $service->exportGroupValues);

        foreach ([null, ''] as $groupValue) {
            try {
                $controller->actionGroupDetail((int)$form->id, $groupValue);
            } catch (BadRequestHttpException) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected);
        self::assertSame([], $service->paginatedGroupValues);
    }

    private function seedForm(): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Zero group controller ', 'form');
        $form->handle = $this->nextTestMarker('zeroGroupController', 'form');
        $layout = new FieldLayout();
        $layout->setPages([[
            'label' => 'Page 1',
            'rows' => [[
                'fields' => [[
                    'type' => SingleLineText::class,
                    'handle' => 'branch',
                    'label' => 'Branch',
                ]],
            ]],
        ]]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);

        return $form;
    }

    /** @param array<string, mixed> $queryParams @param array<string, mixed> $bodyParams */
    private function installRequest(array $queryParams = [], array $bodyParams = []): void
    {
        $this->originalRequest ??= Craft::$app->get('request');
        $this->originalResponse ??= Craft::$app->get('response');
        Craft::$app->set('request', new ZeroGroupRequest($queryParams, $bodyParams));
        Craft::$app->set('response', new Response());
    }
}

final class ZeroGroupStatisticsController extends StatisticsController
{
    /** @var array<string, mixed> */
    public array $renderVariables = [];

    public function requireCpRequest(): void
    {
    }

    public function requirePostRequest(): void
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

final class ZeroGroupStatisticsService extends StatisticsService
{
    /** @var list<string> */
    public array $paginatedGroupValues = [];
    /** @var list<string> */
    public array $exportGroupValues = [];
    /** @var list<int|string|array<int>> */
    public array $receivedSiteScopes = [];

    public function getPaginatedGroupSubmissions(
        Form $form,
        string $groupByHandle,
        string $groupValue,
        string $dateRange = 'all',
        int|string|array $siteId = 'all',
        int $limit = 100,
        int $offset = 0,
    ): array {
        $this->paginatedGroupValues[] = $groupValue;
        $this->receivedSiteScopes[] = $siteId;

        return ['submissions' => [], 'totalCount' => 0];
    }

    public function getGroupSubmissions(
        Form $form,
        string $groupByHandle,
        string $groupValue,
        string $dateRange = 'all',
        int|string|array $siteId = 'all',
        ?int $limit = null,
    ): array {
        $this->exportGroupValues[] = $groupValue;
        $this->receivedSiteScopes[] = $siteId;

        return [];
    }
}

final class ZeroGroupRequest extends ConsoleRequest
{
    /** @param array<string, mixed> $queryParams @param array<string, mixed> $bodyParams */
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
