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
use lindemannrock\formieratingfield\FormieRatingField;
use verbb\formie\elements\Form;
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
     * Display statistics index - list of all forms with rating fields
     */
    public function actionIndex(): Response
    {
        $this->requireCpRequest();

        $plugin = FormieRatingField::$plugin;
        if (!$plugin) {
            throw new \yii\web\ServerErrorHttpException('Plugin not found');
        }

        $statisticsService = $plugin->get('statistics');
        if (!$statisticsService) {
            throw new \yii\web\ServerErrorHttpException('Statistics service not available');
        }

        $settings = $plugin->getSettings();
        $request = Craft::$app->getRequest();

        // Get query parameters
        $search = $request->getQueryParam('search', '');
        $sort = $request->getQueryParam('sort', 'totalSubmissions');
        $dir = $request->getQueryParam('dir', 'desc');
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $limit = $settings->itemsPerPage;

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
        ]);
    }

    /**
     * Display detailed statistics for a specific form
     */
    public function actionForm(?int $formId = null): Response
    {
        $this->requireCpRequest();

        if (!$formId) {
            throw new \yii\web\BadRequestHttpException('Form ID is required');
        }

        $form = Form::find()->id($formId)->one();

        if (!$form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $statisticsService = FormieRatingField::$plugin->get('statistics');
        $settings = FormieRatingField::$plugin->getSettings();

        // Get date range from query params, use default from settings if not specified
        $dateRange = Craft::$app->getRequest()->getQueryParam('dateRange', $settings->defaultDateRange);
        $groupBy = Craft::$app->getRequest()->getQueryParam('groupBy', null);
        $fieldFilter = Craft::$app->getRequest()->getQueryParam('field', null);

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
            $fieldStats[$field->handle] = $statisticsService->getFieldStatistics($form, $field, $dateRange, $groupBy);
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
        ]);
    }

    /**
     * Display individual submissions for a specific group
     */
    public function actionGroupDetail(?int $formId = null, ?string $groupValue = null): Response
    {
        $this->requireCpRequest();

        if (!$formId || !$groupValue) {
            throw new \yii\web\BadRequestHttpException('Form ID and group value are required');
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $statisticsService = FormieRatingField::$plugin->get('statistics');
        $dateRange = Craft::$app->getRequest()->getQueryParam('dateRange', 'all');
        $groupBy = Craft::$app->getRequest()->getQueryParam('groupBy');

        if (!$groupBy) {
            Craft::$app->getSession()->setError(Craft::t('formie-rating-field', 'Group by parameter is required'));
            return $this->redirect('formie-rating-field/statistics/form/' . $formId);
        }

        // Decode the group value (might be URL encoded)
        $groupValue = urldecode($groupValue);

        // Get submissions for this specific group
        $submissions = $statisticsService->getGroupSubmissions($form, $groupBy, $groupValue, $dateRange);
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

        $request = Craft::$app->getRequest();
        $formId = $request->getBodyParam('formId');
        $fieldHandle = $request->getBodyParam('fieldHandle');
        $dateRange = $request->getBodyParam('dateRange', 'all');
        $type = $request->getBodyParam('type', 'summary');

        if (!$formId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Form ID is required',
            ]);
        }

        $form = Form::find()->id($formId)->one();

        if (!$form) {
            return $this->asJson([
                'success' => false,
                'error' => 'Form not found',
            ]);
        }

        $statisticsService = FormieRatingField::$plugin->get('statistics');

        try {
            $data = null;

            switch ($type) {
                case 'summary':
                    // Get summary stats for all rating fields
                    $ratingFields = $statisticsService->getRatingFieldsForForm($form);
                    $fieldStats = [];

                    foreach ($ratingFields as $field) {
                        $fieldStats[$field->handle] = $statisticsService->getFieldStatistics($form, $field, $dateRange);
                    }

                    $data = [
                        'fieldStats' => $fieldStats,
                        'totalSubmissions' => $statisticsService->getTotalSubmissions($form, $dateRange),
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

                    $data = $statisticsService->getTrendData($form, $field, $dateRange);
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

                    $data = $statisticsService->getDistributionData($form, $field, $dateRange);
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

        $formId = Craft::$app->getRequest()->getBodyParam('formId');

        if (!$formId) {
            return $this->asJson([
                'success' => false,
                'error' => 'Form ID is required',
            ]);
        }

        try {
            $statisticsService = FormieRatingField::$plugin->get('statistics');
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

        $request = Craft::$app->getRequest();
        $formId = $request->getQueryParam('formId');
        $groupBy = $request->getQueryParam('groupBy');
        $groupValue = urldecode($request->getQueryParam('groupValue', ''));
        $dateRange = $request->getQueryParam('dateRange', 'all');
        $format = $request->getQueryParam('format', 'csv');

        if (!$formId || !$groupBy || !$groupValue) {
            throw new \yii\web\BadRequestHttpException('Missing required parameters');
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $statisticsService = FormieRatingField::$plugin->get('statistics');

        // Get submissions for this group
        $submissions = $statisticsService->getGroupSubmissions($form, $groupBy, $groupValue, $dateRange);

        // Build filename
        $settings = FormieRatingField::$plugin->getSettings();
        $filenamePart = strtolower(str_replace(' ', '-', $settings->getLowerDisplayName()));
        $safeGroupValue = preg_replace('/[^a-z0-9]+/i', '-', $groupValue);
        $safeGroupValue = trim($safeGroupValue, '-');
        // Use "alltime" instead of "all" for clearer filename
        $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;

        if ($format === 'json') {
            // Build JSON data
            $exportData = [
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

                $exportData['submissions'][] = $submissionData;
            }

            $jsonData = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $filename = $filenamePart . '-statistics-' . $form->handle . '-' . $safeGroupValue . '-' . $dateRangeLabel . '-' . date('Y-m-d') . '.json';

            return Craft::$app->getResponse()
                ->sendContentAsFile($jsonData, $filename, [
                    'mimeType' => 'application/json',
                ]);
        }

        // Build CSV
        $rows = [];

        // Header
        $headers = ['Date', 'Submission ID'];
        foreach ($form->getFields() as $field) {
            $headers[] = $field->label;
        }
        $rows[] = $headers;

        // Data rows
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

        // Convert to CSV
        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $filename = $filenamePart . '-statistics-' . $form->handle . '-' . $safeGroupValue . '-' . $dateRangeLabel . '-' . date('Y-m-d') . '.csv';

        return Craft::$app->getResponse()
            ->sendContentAsFile($csv, $filename, [
                'mimeType' => 'text/csv',
            ]);
    }

    /**
     * Export statistics to CSV or JSON
     */
    public function actionExport(?int $formId = null): Response
    {
        $this->requireCpRequest();

        if (!$formId) {
            throw new \yii\web\BadRequestHttpException('Form ID is required');
        }

        $form = Form::find()->id($formId)->one();

        if (!$form instanceof Form) {
            throw new \yii\web\NotFoundHttpException('Form not found');
        }

        $dateRange = Craft::$app->getRequest()->getQueryParam('dateRange', 'all');
        $groupBy = Craft::$app->getRequest()->getQueryParam('groupBy', null);
        $format = Craft::$app->getRequest()->getQueryParam('format', 'csv');
        $statisticsService = FormieRatingField::$plugin->get('statistics');

        // Build filename following analytics pattern
        $settings = FormieRatingField::$plugin->getSettings();
        $filenamePart = strtolower(str_replace(' ', '-', $settings->getLowerDisplayName()));

        // Use "alltime" instead of "all" for clearer filename
        $dateRangeLabel = $dateRange === 'all' ? 'alltime' : $dateRange;

        // Include groupBy in filename if set
        $groupByPart = $groupBy ? '-' . $groupBy : '';

        if ($format === 'json') {
            // Generate JSON
            $data = $statisticsService->generateJsonExport($form, $dateRange, $groupBy);
            $filename = $filenamePart . '-statistics-' . $form->handle . $groupByPart . '-' . $dateRangeLabel . '-' . date('Y-m-d') . '.json';

            return Craft::$app->getResponse()
                ->sendContentAsFile($data, $filename, [
                    'mimeType' => 'application/json',
                ]);
        }

        // Generate CSV (default)
        $csv = $statisticsService->generateCsvExport($form, $dateRange, $groupBy);
        $filename = $filenamePart . '-statistics-' . $form->handle . $groupByPart . '-' . $dateRangeLabel . '-' . date('Y-m-d') . '.csv';

        return Craft::$app->getResponse()
            ->sendContentAsFile($csv, $filename, [
                'mimeType' => 'text/csv',
            ]);
    }
}
