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

        // Get date range from query params
        $dateRange = Craft::$app->getRequest()->getQueryParam('dateRange', 'all');
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
     * Export statistics to CSV
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
        $statisticsService = FormieRatingField::$plugin->get('statistics');

        // Generate CSV
        $csv = $statisticsService->generateCsvExport($form, $dateRange, $groupBy);

        // Build filename following analytics pattern
        $settings = FormieRatingField::$plugin->getSettings();
        $filenamePart = strtolower(str_replace(' ', '-', $settings->getPluralLowerDisplayName()));
        $filename = $filenamePart . '-statistics-' . $form->handle . '-' . $dateRange . '-' . date('Y-m-d') . '.csv';

        return Craft::$app->getResponse()
            ->sendContentAsFile($csv, $filename, [
                'mimeType' => 'text/csv',
            ]);
    }
}
