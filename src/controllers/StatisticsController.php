<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\formieratingfield\FormieRatingField;
use verbb\formie\elements\Form;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Statistics Controller
 *
 * Handles analytics and statistics pages for rating field data
 *
 * @author LindemannRock
 * @since 3.3.0
 */
class StatisticsController extends Controller
{
    /**
     * Resolve a raw siteId query/body param to a validated int or 'all'.
     *
     * - null / empty / 'all' → 'all' (cross-site)
     * - numeric string → cast to int and verify it is an editable site; throws ForbiddenHttpException if not
     *
     * @param string|null $rawSiteId
     * @return int|string int for a specific site, 'all' for cross-site
     * @throws ForbiddenHttpException
     */
    private function _resolveSiteId(?string $rawSiteId): int|string
    {
        if (!$rawSiteId || $rawSiteId === 'all') {
            return 'all';
        }

        $siteId = (int)$rawSiteId;
        $editableIds = Craft::$app->getSites()->getEditableSiteIds();

        if (!in_array($siteId, $editableIds, true)) {
            throw new ForbiddenHttpException('You do not have permission to access that site.');
        }

        return $siteId;
    }

    /**
     * Display statistics index - list of all forms with rating fields
     */
    public function actionIndex(): Response
    {
        $this->requireCpRequest();

        $plugin = FormieRatingField::$plugin;
        if (!$plugin) {
            throw new \yii\web\ServerErrorHttpException('Plugin not found');
        }

        $user = Craft::$app->getUser();
        $settings = $plugin->getSettings();

        // If user doesn't have viewStatistics permission, redirect to first accessible section
        if (!$user->checkPermission('formieRatingField:viewStatistics')) {
            $sections = $plugin->getCpSections($settings);
            $route = CpNavHelper::firstAccessibleRoute($user, $settings, $sections);
            if ($route) {
                return $this->redirect($route);
            }
            // No permissions at all - throw forbidden
            $this->requirePermission('formieRatingField:viewStatistics');
        }

        $statisticsService = $plugin->statistics;
        $request = Craft::$app->getRequest();

        // Get query parameters
        $search = $request->getQueryParam('search', '');
        $sort = $request->getQueryParam('sort', 'totalSubmissions');
        $dir = $request->getQueryParam('dir', 'desc');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = $settings->itemsPerPage;
        $siteId = $this->_resolveSiteId($request->getQueryParam('siteId'));

        // Get all forms that have rating fields
        $formsWithRatings = $statisticsService->getFormsWithRatingFields();

        // Apply search filter
        if ($search) {
            $formsWithRatings = array_filter($formsWithRatings, function($item) use ($search) {
                $searchLower = strtolower($search);
                return stripos($item['form']->title, $search) !== false ||
                       stripos($item['form']->handle, $search) !== false;
            });
        }

        // Apply sorting
        usort($formsWithRatings, function($a, $b) use ($sort, $dir) {
            $aVal = $sort === 'title' ? $a['form']->title :
                   ($sort === 'ratingFieldCount' ? $a['ratingFieldCount'] : $a['totalSubmissions']);
            $bVal = $sort === 'title' ? $b['form']->title :
                   ($sort === 'ratingFieldCount' ? $b['ratingFieldCount'] : $b['totalSubmissions']);

            if ($dir === 'asc') {
                return $aVal <=> $bVal;
            }
            return $bVal <=> $aVal;
        });

        // Calculate pagination
        $totalItems = count($formsWithRatings);
        $totalPages = ceil($totalItems / $limit);
        $offset = ($page - 1) * $limit;

        // Get paginated results
        $paginatedForms = array_slice($formsWithRatings, $offset, $limit);

        return $this->renderTemplate('formie-rating-field/statistics/index', [
            'forms' => $paginatedForms,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'siteId' => $siteId,
            'editableSites' => Craft::$app->getSites()->getEditableSites(),
        ]);
    }

    /**
     * Display detailed statistics for a specific form
     */
    public function actionForm(?int $formId = null): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('formieRatingField:viewStatistics');

        if (!$formId) {
            throw new \yii\web\BadRequestHttpException('Form ID is required');
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $statisticsService = FormieRatingField::$plugin->statistics;
        $settings = FormieRatingField::$plugin->getSettings();

        // Get date range from query params, fall back to base helper which respects
        // config/formie-rating-field.php → config/lindemannrock-base.php → 'last30days'.
        $dateRange = Craft::$app->getRequest()->getQueryParam('dateRange', DateRangeHelper::getDefaultDateRange('formie-rating-field'));
        $groupBy = Craft::$app->getRequest()->getQueryParam('groupBy', null);
        $fieldFilter = Craft::$app->getRequest()->getQueryParam('field', null);
        $siteId = $this->_resolveSiteId(Craft::$app->getRequest()->getQueryParam('siteId'));

        // Get rating fields for this form
        $ratingFields = $statisticsService->getRatingFieldsForForm($form);

        if (empty($ratingFields)) {
            Craft::$app->getSession()->setError(Craft::t('formie-rating-field', 'This form does not contain any rating fields.'));
            return $this->redirect('formie-rating-field/statistics');
        }

        // Get groupable fields for this form
        $groupableFields = $statisticsService->getGroupableFieldsForForm($form);

        // Filter rating fields if specified
        $fieldsToDisplay = $ratingFields;
        if ($fieldFilter) {
            $fieldsToDisplay = array_filter($ratingFields, fn($field) => $field->handle === $fieldFilter);
        }

        // Get statistics for each rating field to display
        $fieldStats = [];
        foreach ($fieldsToDisplay as $field) {
            $fieldStats[$field->handle] = $statisticsService->getFieldStatistics($form, $field, $dateRange, $groupBy, $siteId);
        }

        return $this->renderTemplate('formie-rating-field/statistics/form', [
            'form' => $form,
            'allRatingFields' => $ratingFields,
            'ratingFields' => $fieldsToDisplay,
            'groupableFields' => $groupableFields,
            'fieldStats' => $fieldStats,
            'dateRange' => $dateRange,
            'groupBy' => $groupBy,
            'fieldFilter' => $fieldFilter,
            'siteId' => $siteId,
            'editableSites' => Craft::$app->getSites()->getEditableSites(),
        ]);
    }

    /**
     * Display individual submissions for a specific group
     */
    public function actionGroupDetail(?int $formId = null, ?string $groupValue = null): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('formieRatingField:viewStatistics');

        if (!$formId || !$groupValue) {
            throw new \yii\web\BadRequestHttpException('Form ID and group value are required');
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $statisticsService = FormieRatingField::$plugin->statistics;
        $dateRange = Craft::$app->getRequest()->getQueryParam('dateRange', 'all');
        $groupBy = Craft::$app->getRequest()->getQueryParam('groupBy');
        $siteId = $this->_resolveSiteId(Craft::$app->getRequest()->getQueryParam('siteId'));

        if (!$groupBy) {
            Craft::$app->getSession()->setError(Craft::t('formie-rating-field', 'Group by parameter is required'));
            return $this->redirect('formie-rating-field/statistics/form/' . $formId);
        }

        // Decode the group value (might be URL encoded)
        $groupValue = urldecode($groupValue);

        // Get submissions for this specific group
        $submissions = $statisticsService->getGroupSubmissions($form, $groupBy, $groupValue, $dateRange, $siteId);
        $ratingFields = $statisticsService->getRatingFieldsForForm($form);

        // Get the groupBy field label
        $groupByLabel = $groupBy;
        foreach ($form->getFields() as $field) {
            if ($field->handle === $groupBy) {
                $groupByLabel = $field->label;
                break;
            }
        }

        return $this->renderTemplate('formie-rating-field/statistics/group-detail', [
            'form' => $form,
            'groupBy' => $groupBy,
            'groupByLabel' => $groupByLabel,
            'groupValue' => $groupValue,
            'submissions' => $submissions,
            'ratingFields' => $ratingFields,
            'dateRange' => $dateRange,
            'totalSubmissions' => count($submissions),
        ]);
    }

    /**
     * AJAX endpoint to get statistics data for dynamic updates
     */
    public function actionGetData(): Response
    {
        $this->requireCpRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('formieRatingField:viewStatistics');

        $request = Craft::$app->getRequest();
        $formId = $request->getBodyParam('formId');
        $fieldHandle = $request->getBodyParam('fieldHandle');
        $dateRange = $request->getBodyParam('dateRange', 'all');
        $type = $request->getBodyParam('type', 'summary');
        $siteId = $this->_resolveSiteId($request->getBodyParam('siteId'));

        if (!$formId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Form ID is required',
            ]);
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            return $this->asJson([
                'success' => false,
                'error' => 'Form not found',
            ]);
        }

        $statisticsService = FormieRatingField::$plugin->statistics;

        try {
            $data = null;

            switch ($type) {
                case 'summary':
                    // Get summary stats for all rating fields
                    $ratingFields = $statisticsService->getRatingFieldsForForm($form);
                    $fieldStats = [];

                    foreach ($ratingFields as $field) {
                        $fieldStats[$field->handle] = $statisticsService->getFieldStatistics($form, $field, $dateRange, null, $siteId);
                    }

                    $data = [
                        'fieldStats' => $fieldStats,
                        'totalSubmissions' => $statisticsService->getTotalSubmissions($form, $dateRange, $siteId),
                    ];
                    break;

                case 'trend':
                    // Get trend data for a specific field
                    if (!$fieldHandle) {
                        return $this->asJson(['success' => false, 'error' => 'Field handle is required']);
                    }

                    $field = $statisticsService->getRatingFieldByHandle($form, $fieldHandle);
                    if (!$field) {
                        return $this->asJson(['success' => false, 'error' => 'Field not found']);
                    }

                    $data = $statisticsService->getTrendData($form, $field, $dateRange, $siteId);
                    break;

                case 'distribution':
                    // Get distribution data for a specific field
                    if (!$fieldHandle) {
                        return $this->asJson(['success' => false, 'error' => 'Field handle is required']);
                    }

                    $field = $statisticsService->getRatingFieldByHandle($form, $fieldHandle);
                    if (!$field) {
                        return $this->asJson(['success' => false, 'error' => 'Field not found']);
                    }

                    $data = $statisticsService->getDistributionData($form, $field, $dateRange, $siteId);
                    break;
            }

            return $this->asJson([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Craft::error('Error getting statistics data: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear statistics cache for a form
     */
    public function actionClearCache(): Response
    {
        $this->requireCpRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('formieRatingField:refreshStatistics');

        $formId = Craft::$app->getRequest()->getBodyParam('formId');

        if (!$formId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Form ID is required',
            ]);
        }

        try {
            $statisticsService = FormieRatingField::$plugin->statistics;
            $statisticsService->clearCacheForForm($formId);

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('formie-rating-field', 'Statistics cache cleared'),
            ]);
        } catch (\Exception $e) {
            Craft::error('Error clearing statistics cache: ' . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Export group detail submissions to CSV or JSON
     */
    public function actionExportGroup(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission('formieRatingField:exportStatistics');

        $request = Craft::$app->getRequest();
        $formId = $request->getBodyParam('formId');
        $groupBy = $request->getBodyParam('groupBy');
        $groupValue = urldecode($request->getBodyParam('groupValue', ''));
        $dateRange = $request->getBodyParam('dateRange', 'all');
        $format = $request->getBodyParam('format', 'csv');
        $siteId = $this->_resolveSiteId($request->getBodyParam('siteId'));

        if (!$formId || !$groupBy || !$groupValue) {
            throw new BadRequestHttpException('Missing required parameters');
        }

        // Gate by enabled export formats from config/formie-rating-field.php (or base default)
        if (!ExportHelper::isFormatEnabled($format, 'formie-rating-field')) {
            throw new BadRequestHttpException("Export format '{$format}' is not enabled.");
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $statisticsService = FormieRatingField::$plugin->statistics;

        // Get submissions for this group
        $submissions = $statisticsService->getGroupSubmissions($form, $groupBy, $groupValue, $dateRange, $siteId);

        $settings = FormieRatingField::$plugin->getSettings();
        $safeGroupValue = trim((string)preg_replace('/[^a-z0-9]+/i', '-', $groupValue), '-');
        $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;
        $extension = in_array($format, ['xlsx', 'excel'], true) ? 'xlsx' : $format;

        $siteSlug = is_int($siteId)
            ? strtolower(preg_replace('/[^a-z0-9]+/', '-', Craft::$app->getSites()->getSiteById($siteId)?->handle ?? '') ?: null)
            : null;

        $filename = ExportHelper::filename($settings, array_values(array_filter([
            'statistics',
            $form->handle,
            $siteSlug,
            $safeGroupValue,
            $dateRangeLabel,
        ])), $extension);

        // Build headers and rows for CSV / Excel
        $headers = [
            Craft::t('formie-rating-field', 'Date'),
            Craft::t('formie-rating-field', 'Submission ID'),
        ];
        foreach ($form->getFields() as $field) {
            $headers[] = $field->label;
        }

        $rows = [];
        foreach ($submissions as $submission) {
            $row = [
                $submission->dateCreated->format('Y-m-d H:i:s'),
                $submission->id,
            ];

            foreach ($form->getFields() as $field) {
                $value = $submission->getFieldValue($field->handle);
                $row[] = $value ?? '';
            }

            $rows[] = $row;
        }

        // Build JSON data structure
        $jsonData = [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'handle' => $form->handle,
            ],
            'groupBy' => $groupBy,
            'groupValue' => $groupValue,
            'dateRange' => $dateRange,
            'exportedAt' => date('Y-m-d H:i:s'),
            'totalSubmissions' => count($submissions),
            'submissions' => [],
        ];

        foreach ($submissions as $submission) {
            $submissionData = [
                'id' => $submission->id,
                'dateCreated' => $submission->dateCreated->format('Y-m-d H:i:s'),
                'fields' => [],
            ];

            foreach ($form->getFields() as $field) {
                $value = $submission->getFieldValue($field->handle);
                $submissionData['fields'][$field->handle] = [
                    'label' => $field->label,
                    'value' => $value,
                ];
            }

            $jsonData['submissions'][] = $submissionData;
        }

        return match ($format) {
            'csv' => ExportHelper::toCsv($rows, $headers, $filename),
            'json' => ExportHelper::toJson($jsonData, $filename),
            'xlsx', 'excel' => ExportHelper::toExcel($rows, $headers, $filename, [], [
                'sheetTitle' => 'Statistics',
            ]),
            default => throw new BadRequestHttpException("Unknown export format: {$format}"),
        };
    }

    /**
     * Export statistics to Excel (multi-sheet), CSV (ZIP of CSVs), or JSON (nested payload).
     *
     * Always includes Summary and Raw Responses sections. The By Group section is included
     * only when a groupBy field handle is provided.
     */
    public function actionExport(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission('formieRatingField:exportStatistics');

        $request = Craft::$app->getRequest();
        $formId = $request->getBodyParam('formId');

        if (!$formId) {
            throw new BadRequestHttpException('Form ID is required');
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $dateRange = $request->getBodyParam('dateRange', 'all');
        $groupBy = $request->getBodyParam('groupBy', null);
        $format = $request->getBodyParam('format', 'csv');
        $siteId = $this->_resolveSiteId($request->getBodyParam('siteId'));

        // Gate by enabled export formats from config/formie-rating-field.php (or base default)
        if (!ExportHelper::isFormatEnabled($format, 'formie-rating-field')) {
            throw new BadRequestHttpException("Export format '{$format}' is not enabled.");
        }

        $statisticsService = FormieRatingField::$plugin->statistics;
        $settings = FormieRatingField::$plugin->getSettings();
        $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;

        // Build site handle slug for filename when a specific site is selected
        $siteSlug = is_int($siteId)
            ? strtolower(preg_replace('/[^a-z0-9]+/', '-', Craft::$app->getSites()->getSiteById($siteId)?->handle ?? '') ?: null)
            : null;

        try {
            // Build all sections
            $summary = $statisticsService->buildSummaryExportRows($form, $dateRange, $siteId);
            $raw = $statisticsService->buildRawResponsesExportRows($form, $dateRange, $siteId);
            $byGroup = $groupBy ? $statisticsService->buildGroupedExportRows($form, $dateRange, $groupBy, $siteId) : null;

            if ($format === 'xlsx' || $format === 'excel') {
                $filename = ExportHelper::filename($settings, array_values(array_filter([
                    'statistics',
                    $form->handle,
                    $siteSlug,
                    $dateRangeLabel,
                ])), 'xlsx');

                $sheets = [
                    [
                        'title' => Craft::t('formie-rating-field', 'Summary'),
                        'headers' => $summary['headers'],
                        'rows' => $summary['rows'],
                    ],
                    [
                        'title' => Craft::t('formie-rating-field', 'Raw Responses'),
                        'headers' => $raw['headers'],
                        'rows' => $raw['rows'],
                    ],
                ];

                if ($byGroup) {
                    $sheets[] = [
                        'title' => Craft::t('formie-rating-field', 'By Group'),
                        'headers' => $byGroup['headers'],
                        'rows' => $byGroup['rows'],
                    ];
                }

                return ExportHelper::toExcelMulti($sheets, $filename);
            }

            if ($format === 'json') {
                $filename = ExportHelper::filename($settings, array_values(array_filter([
                    'statistics',
                    $form->handle,
                    $siteSlug,
                    $dateRangeLabel,
                ])), 'json');

                $payload = [
                    'exported' => date('c'),
                    'form' => [
                        'id' => $form->id,
                        'title' => $form->title,
                        'handle' => $form->handle,
                    ],
                    'dateRange' => $dateRange,
                    'summary' => [
                        'columns' => $summary['headers'],
                        'rows' => $summary['rows'],
                    ],
                    'rawResponses' => [
                        'columns' => $raw['headers'],
                        'rows' => $raw['rows'],
                    ],
                ];

                if ($byGroup) {
                    $payload['byGroup'] = [
                        'groupBy' => $groupBy,
                        'columns' => $byGroup['headers'],
                        'rows' => $byGroup['rows'],
                    ];
                }

                return ExportHelper::toJson($payload, $filename);
            }

            // CSV: ZIP of multiple CSV files
            $filename = ExportHelper::filename($settings, array_values(array_filter([
                'statistics',
                $form->handle,
                $siteSlug,
                $dateRangeLabel,
                'csv',
            ])), 'zip');

            $suffix = $form->handle . ($siteSlug ? '-' . $siteSlug : '') . '-' . $dateRangeLabel;
            $files = [
                "summary-{$suffix}.csv" => ExportHelper::csvContent($summary['rows'], $summary['headers']),
                "raw-responses-{$suffix}.csv" => ExportHelper::csvContent($raw['rows'], $raw['headers']),
            ];

            if ($byGroup) {
                $safeGroupBy = trim((string)preg_replace('/[^a-z0-9]+/i', '-', (string)$groupBy), '-');
                $files["by-group-{$safeGroupBy}-{$suffix}.csv"] = ExportHelper::csvContent($byGroup['rows'], $byGroup['headers']);
            }

            return ExportHelper::toZip($files, $filename);
        } catch (\Exception $e) {
            Craft::error('Statistics export failed: ' . $e->getMessage(), __METHOD__);

            Craft::$app->getSession()->setError(
                Craft::$app->getConfig()->getGeneral()->devMode
                    ? $e->getMessage()
                    : Craft::t('formie-rating-field', 'Export failed. Please check the logs for details.')
            );

            return $this->redirect($request->getReferrer() ?? 'formie-rating-field/statistics');
        }
    }
}
