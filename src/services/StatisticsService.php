<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\formieratingfield\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\fields\Categories;
use craft\fields\Dropdown;
use craft\fields\Entries;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Hidden;

/**
 * Statistics Service
 *
 * Handles all statistics calculations for rating fields
 *
 * @author LindemannRock
 * @since 3.3.0
 */
class StatisticsService extends Component
{
    /**
     * Get all forms that have at least one rating field
     *
     * @return array
     */
    public function getFormsWithRatingFields(): array
    {
        $forms = Form::find()->all();
        $formsWithRatings = [];

        foreach ($forms as $form) {
            if (!$form instanceof Form) {
                continue;
            }

            $ratingFields = $this->getRatingFieldsForForm($form);

            if (!empty($ratingFields)) {
                $formsWithRatings[] = [
                    'form' => $form,
                    'ratingFieldCount' => count($ratingFields),
                    'totalSubmissions' => Submission::find()->formId($form->id)->count(),
                ];
            }
        }

        return $formsWithRatings;
    }

    /**
     * Get all rating fields for a specific form
     *
     * @param Form $form
     * @return array
     */
    public function getRatingFieldsForForm(Form $form): array
    {
        $ratingFields = [];

        foreach ($form->getFields() as $field) {
            if ($field instanceof Rating) {
                $ratingFields[] = $field;
            }
        }

        return $ratingFields;
    }

    /**
     * Get all groupable fields for a specific form
     * Returns fields that can be used to group statistics
     *
     * @param Form $form
     * @return array
     */
    public function getGroupableFieldsForForm(Form $form): array
    {
        $groupableFields = [];

        foreach ($form->getFields() as $field) {
            // Skip rating fields themselves
            if ($field instanceof Rating) {
                continue;
            }

            // Include fields that are suitable for grouping
            if (
                $field instanceof PlainText ||
                $field instanceof Hidden ||
                $field instanceof Dropdown ||
                $field instanceof RadioButtons ||
                $field instanceof Entries ||
                $field instanceof Categories
            ) {
                $groupableFields[] = [
                    'handle' => $field->handle,
                    'label' => $field->label,
                    'type' => get_class($field),
                ];
            }
        }

        return $groupableFields;
    }

    /**
     * Get a specific rating field by handle
     *
     * @param Form $form
     * @param string $handle
     * @return Rating|null
     */
    public function getRatingFieldByHandle(Form $form, string $handle): ?Rating
    {
        foreach ($form->getFields() as $field) {
            if ($field instanceof Rating && $field->handle === $handle) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get statistics for a specific rating field
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @return array
     */
    public function getFieldStatistics(Form $form, Rating $field, string $dateRange = 'all', ?string $groupByHandle = null): array
    {
        // Try to get from cache
        $cachedData = $this->getFromCache($form->id, $field->handle, $dateRange, $groupByHandle);

        if ($cachedData !== null) {
            return $cachedData;
        }

        // If grouping is requested, return grouped statistics
        if ($groupByHandle) {
            $stats = $this->getGroupedStatistics($form, $field, $dateRange, $groupByHandle);
        } else {
            $stats = $this->calculateFieldStatistics($form, $field, $dateRange);
        }

        // Save to cache
        $this->saveToCache($form->id, $field->handle, $dateRange, $groupByHandle, $stats);

        return $stats;
    }

    /**
     * Calculate statistics for a specific rating field (not cached)
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @return array
     */
    private function calculateFieldStatistics(Form $form, Rating $field, string $dateRange = 'all'): array
    {
        $submissions = $this->getSubmissions($form, $dateRange);
        $values = $this->extractFieldValues($submissions, $field);

        $stats = [
            'fieldType' => $field->ratingType,
            'fieldLabel' => $field->label,
            'fieldHandle' => $field->handle,
            'totalResponses' => count($values),
            'minValue' => $field->minValue,
            'maxValue' => $field->maxValue,
        ];

        if (empty($values)) {
            if ($field->ratingType === Rating::RATING_TYPE_NPS) {
                $stats['npsScore'] = 0;
                $stats['promoters'] = 0;
                $stats['promotersPercentage'] = 0;
                $stats['passives'] = 0;
                $stats['passivesPercentage'] = 0;
                $stats['detractors'] = 0;
                $stats['detractorsPercentage'] = 0;
                $stats['average'] = 0;
            } else {
                $stats['average'] = 0;
                $stats['distribution'] = [];
                $stats['median'] = 0;
                $stats['mode'] = null;
            }
            return $stats;
        }

        // Calculate type-specific statistics
        switch ($field->ratingType) {
            case Rating::RATING_TYPE_NPS:
                $stats = array_merge($stats, $this->calculateNpsStats($values));
                break;

            case Rating::RATING_TYPE_STAR:
            case Rating::RATING_TYPE_EMOJI:
                $stats = array_merge($stats, $this->calculateAverageStats($values, $field));
                break;
        }

        return $stats;
    }

    /**
     * Get grouped statistics for a rating field
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @param string $groupByHandle
     * @return array
     */
    public function getGroupedStatistics(Form $form, Rating $field, string $dateRange, string $groupByHandle): array
    {
        // Get field UIDs for JSON extraction (Formie stores data by UID, not handle)
        $groupByField = null;
        $ratingFieldUid = $field->uid;

        foreach ($form->getFields() as $formField) {
            if ($formField->handle === $groupByHandle) {
                $groupByField = $formField;
                break;
            }
        }

        if (!$groupByField) {
            throw new \Exception("Group by field '{$groupByHandle}' not found in form.");
        }

        // Use database aggregation for better performance
        $dateStart = $this->getDateRangeStart($dateRange);
        $groupByUid = $groupByField->uid;

        // Build the query using field UIDs (escaped with quotes for JSON path)
        $query = (new Query())
            ->select([
                'groupValue' => "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(content, '$.\"" . $groupByUid . "\"')), ''), '(Not Set)')",
                'count' => 'COUNT(*)',
                'ratingValues' => "GROUP_CONCAT(CAST(JSON_UNQUOTE(JSON_EXTRACT(content, '$.\"" . $ratingFieldUid . "\"')) AS DECIMAL(10,2)))",
            ])
            ->from('{{%formie_submissions}}')
            ->where([
                'formId' => $form->id,
                'isIncomplete' => false,
                'isSpam' => false,
            ])
            ->andWhere("JSON_UNQUOTE(JSON_EXTRACT(content, '$.\"" . $ratingFieldUid . "\"')) IS NOT NULL")
            ->andWhere("JSON_UNQUOTE(JSON_EXTRACT(content, '$.\"" . $ratingFieldUid . "\"')) != ''")
            ->groupBy('groupValue')
            ->orderBy(['count' => SORT_DESC]);

        // Add date filter if specified
        if ($dateStart) {
            $query->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($dateStart)]);
        }

        $results = $query->all();

        // Calculate statistics for each group
        $groupedStats = [];

        foreach ($results as $row) {
            $groupLabel = $row['groupValue'] ?? '(Not Set)';
            $count = (int)$row['count'];

            // Parse the rating values
            $values = array_map('floatval', explode(',', $row['ratingValues']));

            $stats = [
                'label' => $groupLabel,
                'count' => $count,
            ];

            // Calculate type-specific statistics
            switch ($field->ratingType) {
                case Rating::RATING_TYPE_NPS:
                    $stats = array_merge($stats, $this->calculateNpsStats($values));
                    break;

                case Rating::RATING_TYPE_STAR:
                case Rating::RATING_TYPE_EMOJI:
                    $stats['average'] = round(array_sum($values) / $count, 2);
                    $stats['median'] = $this->calculateMedian($values);
                    break;
            }

            $groupedStats[] = $stats;
        }

        // Get the group field label
        $groupFieldLabel = $groupByHandle;
        foreach ($form->getFields() as $formField) {
            if ($formField->handle === $groupByHandle) {
                $groupFieldLabel = $formField->label;
                break;
            }
        }

        return [
            'fieldType' => $field->ratingType,
            'fieldLabel' => $field->label,
            'fieldHandle' => $field->handle,
            'groupByHandle' => $groupByHandle,
            'groupByLabel' => $groupFieldLabel,
            'groups' => $groupedStats,
            'totalGroups' => count($groupedStats),
        ];
    }

    /**
     * Get submissions for a specific group value
     *
     * @param Form $form
     * @param string $groupByHandle
     * @param string $groupValue
     * @param string $dateRange
     * @return array
     */
    public function getGroupSubmissions(Form $form, string $groupByHandle, string $groupValue, string $dateRange = 'all'): array
    {
        $submissions = $this->getSubmissions($form, $dateRange);
        $groupedSubmissions = [];

        foreach ($submissions as $submission) {
            $submissionGroupValue = $submission->getFieldValue($groupByHandle);

            // Get string representation
            $groupKey = $this->getGroupKeyFromValue($submissionGroupValue);

            if ($groupKey === null || $groupKey === '') {
                $groupKey = '(Not Set)';
            }

            // Match the group value
            if ($groupKey === $groupValue) {
                $groupedSubmissions[] = $submission;
            }
        }

        return $groupedSubmissions;
    }

    /**
     * Get group key from field value
     *
     * @param mixed $value
     * @return string|null
     */
    private function getGroupKeyFromValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Handle entry/category fields (returns element)
        if (is_object($value)) {
            if (property_exists($value, 'title') && isset($value->title)) {
                return (string)$value->title;
            }
            return (string)$value;
        }

        // Handle arrays (multi-select fields)
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string)$value;
    }

    /**
     * Generate cache key for Redis/database storage
     *
     * @param int $formId
     * @param string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @return string
     */
    private function getCacheKey(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle = null): string
    {
        $key = "formie-rating-stats-{$formId}-{$fieldHandle}-{$dateRange}";

        if ($groupByHandle) {
            $key .= "-{$groupByHandle}";
        }

        return $key;
    }

    /**
     * Get cache directory path
     *
     * @return string
     */
    private function getCachePath(): string
    {
        return PluginHelper::getCachePath(FormieRatingField::$plugin, 'statistics');
    }

    /**
     * Generate cache filename
     *
     * @param int $formId
     * @param string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @return string
     */
    public function getCacheFilename(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle = null): string
    {
        $key = "{$formId}-{$fieldHandle}-{$dateRange}";

        if ($groupByHandle) {
            $key .= "-{$groupByHandle}";
        }

        return md5($key) . '.cache';
    }

    /**
     * Get statistics from cache
     *
     * @param int $formId
     * @param string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @return array|null
     */
    private function getFromCache(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle = null): ?array
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            $cacheKey = $this->getCacheKey($formId, $fieldHandle, $dateRange, $groupByHandle);
            $cached = Craft::$app->cache->get($cacheKey);
            return $cached !== false ? $cached : null;
        }

        // Use file-based cache (default)
        $cachePath = $this->getCachePath();
        $filename = $this->getCacheFilename($formId, $fieldHandle, $dateRange, $groupByHandle);
        $filepath = $cachePath . $filename;

        if (!file_exists($filepath)) {
            return null;
        }

        // Read and unserialize cache
        $data = file_get_contents($filepath);
        if ($data === false) {
            return null;
        }

        return unserialize($data);
    }

    /**
     * Save statistics to cache
     *
     * @param int $formId
     * @param string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param array $stats
     * @return bool
     */
    private function saveToCache(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle, array $stats): bool
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            $cacheKey = $this->getCacheKey($formId, $fieldHandle, $dateRange, $groupByHandle);
            $cache = Craft::$app->cache;

            Craft::info("Attempting to save to cache. Type: " . get_class($cache) . ", Key: {$cacheKey}", __METHOD__);

            $result = $cache->set($cacheKey, $stats);

            if ($result) {
                // Increment count in Redis
                $this->incrementRedisCacheCount();
                Craft::info("Cache saved successfully: {$cacheKey}", __METHOD__);
            } else {
                Craft::error("Failed to save cache: {$cacheKey}", __METHOD__);
            }

            return $result;
        }

        // Use file-based cache (default)
        $cachePath = $this->getCachePath();

        // Create cache directory if it doesn't exist
        if (!is_dir($cachePath)) {
            FileHelper::createDirectory($cachePath);
        }

        $filename = $this->getCacheFilename($formId, $fieldHandle, $dateRange, $groupByHandle);
        $filepath = $cachePath . $filename;

        // Serialize and save
        $data = serialize($stats);

        $result = file_put_contents($filepath, $data) !== false;

        if ($result) {
            Craft::info("Cache saved to file: {$filename}", __METHOD__);
        } else {
            Craft::error("Failed to save cache to file: {$filename}", __METHOD__);
        }

        return $result;
    }

    /**
     * Clear statistics cache for a specific form
     *
     * @param int $formId
     * @return bool
     */
    public function clearCacheForForm(int $formId): bool
    {
        $cachePath = $this->getCachePath();

        if (!is_dir($cachePath)) {
            return true;
        }

        // Find all cache files for this form
        $pattern = $cachePath . md5($formId . '-*') . '*.cache';
        $files = glob($cachePath . '*.cache');

        if ($files === false) {
            return false;
        }

        $cleared = true;
        foreach ($files as $file) {
            // Check if filename contains the form ID
            $basename = basename($file, '.cache');
            // Since we use md5, we need to delete all files and let them regenerate
            // This is a simple approach - delete all cache files
            if (!@unlink($file)) {
                $cleared = false;
            }
        }

        return $cleared;
    }

    /**
     * Clear all statistics cache
     *
     * @return bool
     */
    public function clearAllCache(): bool
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Clear Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            // Reset count
            $this->resetRedisCacheCount();
            // Flush all cache keys matching our pattern
            return Craft::$app->cache->flush();
        }

        // Clear file-based cache (default)
        $cachePath = $this->getCachePath();

        if (!is_dir($cachePath)) {
            return true;
        }

        $files = glob($cachePath . '*.cache');

        if ($files === false) {
            return false;
        }

        $cleared = true;
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $cleared = false;
            }
        }

        return $cleared;
    }

    /**
     * Increment Redis cache count
     */
    private function incrementRedisCacheCount(): void
    {
        $cache = Craft::$app->cache;
        if ($cache instanceof \yii\redis\Cache) {
            $redis = $cache->redis;
            $redis->executeCommand('INCR', ['formie-rating-cache-count']);
        }
    }

    /**
     * Reset Redis cache count
     */
    private function resetRedisCacheCount(): void
    {
        $cache = Craft::$app->cache;
        if ($cache instanceof \yii\redis\Cache) {
            $redis = $cache->redis;
            $redis->executeCommand('SET', ['formie-rating-cache-count', 0]);
        }
    }

    /**
     * Get count of cache entries
     *
     * @return int
     */
    public function getCacheFileCount(): int
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // For Redis, get count from tracking key
        if ($settings->cacheStorageMethod === 'redis') {
            try {
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $redis = $cache->redis;
                    $count = $redis->executeCommand('GET', ['formie-rating-cache-count']);
                    return (int)($count ?: 0);
                }
                return 0;
            } catch (\Exception $e) {
                Craft::error('Failed to get Redis cache count: ' . $e->getMessage(), __METHOD__);
                return 0;
            }
        }

        // Count file-based cache
        $cachePath = $this->getCachePath();

        if (!is_dir($cachePath)) {
            return 0;
        }

        $files = glob($cachePath . '*.cache');

        return $files !== false ? count($files) : 0;
    }

    /**
     * Calculate NPS-specific statistics
     *
     * @param array $values
     * @return array
     */
    private function calculateNpsStats(array $values): array
    {
        $total = count($values);
        $promoters = count(array_filter($values, fn($v) => $v >= 9));
        $passives = count(array_filter($values, fn($v) => $v >= 7 && $v <= 8));
        $detractors = count(array_filter($values, fn($v) => $v <= 6));

        $npsScore = $total > 0 ? round((($promoters - $detractors) / $total) * 100, 1) : 0;

        return [
            'npsScore' => $npsScore,
            'promoters' => $promoters,
            'promotersPercentage' => $total > 0 ? round(($promoters / $total) * 100, 1) : 0,
            'passives' => $passives,
            'passivesPercentage' => $total > 0 ? round(($passives / $total) * 100, 1) : 0,
            'detractors' => $detractors,
            'detractorsPercentage' => $total > 0 ? round(($detractors / $total) * 100, 1) : 0,
            'average' => $total > 0 ? round(array_sum($values) / $total, 2) : 0,
        ];
    }

    /**
     * Calculate average-based statistics (for star and emoji types)
     *
     * @param array $values
     * @param Rating $field
     * @return array
     */
    private function calculateAverageStats(array $values, Rating $field): array
    {
        $total = count($values);
        $average = $total > 0 ? round(array_sum($values) / $total, 2) : 0;

        // Calculate distribution
        $distribution = [];
        $step = ($field->allowHalfRatings && $field->ratingType === Rating::RATING_TYPE_STAR) ? 0.5 : 1;

        for ($i = $field->minValue; $i <= $field->maxValue; $i += $step) {
            $count = count(array_filter($values, function($v) use ($i, $step) {
                if ($step === 0.5) {
                    return abs($v - $i) < 0.01; // Handle floating point comparison
                }
                return floor($v) == $i;
            }));

            $distribution[] = [
                'value' => $i,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return [
            'average' => $average,
            'distribution' => $distribution,
            'median' => $this->calculateMedian($values),
            'mode' => $this->calculateMode($values),
        ];
    }

    /**
     * Get trend data over time for a specific field
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @return array
     */
    public function getTrendData(Form $form, Rating $field, string $dateRange = 'all'): array
    {
        $submissions = $this->getSubmissions($form, $dateRange);

        $dateFormat = $this->getDateFormatForRange($dateRange);
        $trendData = [];

        foreach ($submissions as $submission) {
            $value = $submission->getFieldValue($field->handle);

            if ($value !== null && $value !== '') {
                $date = $submission->dateCreated->format($dateFormat);

                if (!isset($trendData[$date])) {
                    $trendData[$date] = [
                        'values' => [],
                        'count' => 0,
                    ];
                }

                $trendData[$date]['values'][] = (float)$value;
                $trendData[$date]['count']++;
            }
        }

        // Calculate averages for each date
        $chartData = [];
        foreach ($trendData as $date => $data) {
            $valueCount = count($data['values']);
            $average = round(array_sum($data['values']) / $valueCount, 2);

            $chartData[] = [
                'date' => $date,
                'average' => $average,
                'count' => $data['count'],
            ];
        }

        // Sort by date
        usort($chartData, fn($a, $b) => strcmp($a['date'], $b['date']));

        // Limit to max 50 data points for performance
        if (count($chartData) > 50) {
            $step = ceil(count($chartData) / 50);
            $sampledData = [];
            foreach ($chartData as $index => $data) {
                if ($index % $step === 0) {
                    $sampledData[] = $data;
                }
            }
            $chartData = $sampledData;
        }

        return [
            'labels' => array_column($chartData, 'date'),
            'averages' => array_column($chartData, 'average'),
            'counts' => array_column($chartData, 'count'),
        ];
    }

    /**
     * Get distribution data for chart display
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @return array
     */
    public function getDistributionData(Form $form, Rating $field, string $dateRange = 'all'): array
    {
        $stats = $this->getFieldStatistics($form, $field, $dateRange);

        if (isset($stats['distribution'])) {
            return [
                'labels' => array_column($stats['distribution'], 'value'),
                'values' => array_column($stats['distribution'], 'count'),
                'percentages' => array_column($stats['distribution'], 'percentage'),
            ];
        }

        return [
            'labels' => [],
            'values' => [],
            'percentages' => [],
        ];
    }

    /**
     * Get total submissions for a form within date range
     *
     * @param Form $form
     * @param string $dateRange
     * @return int
     */
    public function getTotalSubmissions(Form $form, string $dateRange = 'all'): int
    {
        return count($this->getSubmissions($form, $dateRange));
    }

    /**
     * Generate CSV export for form statistics
     *
     * @param Form $form
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @return string
     */
    public function generateCsvExport(Form $form, string $dateRange = 'all', ?string $groupByHandle = null): string
    {
        $ratingFields = $this->getRatingFieldsForForm($form);

        if (empty($ratingFields)) {
            return '';
        }

        $rows = [];

        // If grouped, export aggregated stats per group
        if ($groupByHandle) {
            // Get groupBy field label
            $groupByFieldLabel = $groupByHandle;
            foreach ($form->getFields() as $field) {
                if ($field->handle === $groupByHandle) {
                    $groupByFieldLabel = $field->label;
                    break;
                }
            }

            // Build header for grouped export
            $headers = [$groupByFieldLabel, 'Submissions Count'];
            foreach ($ratingFields as $field) {
                if ($field->ratingType === Rating::RATING_TYPE_NPS) {
                    $headers[] = $field->label . ' - NPS Score';
                    $headers[] = $field->label . ' - Promoters';
                    $headers[] = $field->label . ' - Passives';
                    $headers[] = $field->label . ' - Detractors';
                } else {
                    $headers[] = $field->label . ' - Average';
                    $headers[] = $field->label . ' - Median';
                }
            }
            $rows[] = $headers;

            // Get grouped stats for first rating field to get the groups
            $firstField = $ratingFields[0];
            $groupedStats = $this->getFieldStatistics($form, $firstField, $dateRange, $groupByHandle);

            if (isset($groupedStats['groups'])) {
                foreach ($groupedStats['groups'] as $group) {
                    $row = [$group['label'], $group['count']];

                    // Get stats for each rating field for this group
                    foreach ($ratingFields as $field) {
                        $fieldStats = $this->getFieldStatistics($form, $field, $dateRange, $groupByHandle);

                        // Find this group's stats
                        $groupStats = null;
                        foreach ($fieldStats['groups'] as $g) {
                            if ($g['label'] === $group['label']) {
                                $groupStats = $g;
                                break;
                            }
                        }

                        if ($groupStats) {
                            if ($field->ratingType === Rating::RATING_TYPE_NPS) {
                                $row[] = $groupStats['npsScore'];
                                $row[] = $groupStats['promoters'] . ' (' . $groupStats['promotersPercentage'] . '%)';
                                $row[] = $groupStats['passives'] . ' (' . $groupStats['passivesPercentage'] . '%)';
                                $row[] = $groupStats['detractors'] . ' (' . $groupStats['detractorsPercentage'] . '%)';
                            } else {
                                $row[] = $groupStats['average'];
                                $row[] = $groupStats['median'];
                            }
                        } else {
                            // No data for this group
                            $row[] = '';
                            $row[] = '';
                            if ($field->ratingType === Rating::RATING_TYPE_NPS) {
                                $row[] = '';
                                $row[] = '';
                            }
                        }
                    }

                    $rows[] = $row;
                }
            }
        } else {
            // Not grouped - export raw submission data
            $submissions = $this->getSubmissions($form, $dateRange);

            // Build CSV header
            $headers = ['Submission Date', 'Submission ID'];
            foreach ($ratingFields as $field) {
                $headers[] = $field->label;
            }
            $rows[] = $headers;

            foreach ($submissions as $submission) {
                $row = [
                    $submission->dateCreated->format('Y-m-d H:i:s'),
                    $submission->id,
                ];

                foreach ($ratingFields as $field) {
                    $value = $submission->getFieldValue($field->handle);
                    $row[] = $value ?? '';
                }

                $rows[] = $row;
            }
        }


        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Generate JSON export for form statistics
     *
     * @param Form $form
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @return string
     */
    public function generateJsonExport(Form $form, string $dateRange = 'all', ?string $groupByHandle = null): string
    {
        $ratingFields = $this->getRatingFieldsForForm($form);

        if (empty($ratingFields)) {
            return json_encode(['error' => 'No rating fields found'], JSON_PRETTY_PRINT);
        }

        $exportData = [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'handle' => $form->handle,
            ],
            'dateRange' => $dateRange,
            'exportedAt' => date('Y-m-d H:i:s'),
            'fields' => [],
        ];

        // If grouped, export aggregated stats per group
        if ($groupByHandle) {
            $exportData['groupBy'] = $groupByHandle;

            foreach ($ratingFields as $field) {
                $fieldStats = $this->getFieldStatistics($form, $field, $dateRange, $groupByHandle);

                $fieldData = [
                    'handle' => $field->handle,
                    'label' => $field->label,
                    'ratingType' => $field->ratingType,
                    'groups' => $fieldStats['groups'] ?? [],
                ];

                $exportData['fields'][] = $fieldData;
            }
        } else {
            // Not grouped - export raw submission data
            $submissions = $this->getSubmissions($form, $dateRange);

            $exportData['submissions'] = [];

            foreach ($submissions as $submission) {
                $submissionData = [
                    'id' => $submission->id,
                    'dateCreated' => $submission->dateCreated->format('Y-m-d H:i:s'),
                    'ratings' => [],
                ];

                foreach ($ratingFields as $field) {
                    $value = $submission->getFieldValue($field->handle);
                    $submissionData['ratings'][$field->handle] = [
                        'label' => $field->label,
                        'value' => $value,
                    ];
                }

                $exportData['submissions'][] = $submissionData;
            }

            // Also include summary statistics
            $exportData['summary'] = [];
            foreach ($ratingFields as $field) {
                $fieldStats = $this->getFieldStatistics($form, $field, $dateRange, null);
                $exportData['summary'][$field->handle] = [
                    'label' => $field->label,
                    'ratingType' => $field->ratingType,
                    'totalResponses' => $fieldStats['totalResponses'] ?? 0,
                    'average' => $fieldStats['average'] ?? null,
                    'median' => $fieldStats['median'] ?? null,
                    'mode' => $fieldStats['mode'] ?? null,
                    'npsScore' => $fieldStats['npsScore'] ?? null,
                    'promoters' => $fieldStats['promoters'] ?? null,
                    'passives' => $fieldStats['passives'] ?? null,
                    'detractors' => $fieldStats['detractors'] ?? null,
                ];
            }
        }

        return json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get submissions for a form within a date range
     *
     * @param Form $form
     * @param string $dateRange
     * @return array
     */
    private function getSubmissions(Form $form, string $dateRange = 'all'): array
    {
        $query = Submission::find()
            ->formId($form->id)
            ->orderBy(['dateCreated' => SORT_DESC]);

        $dateStart = $this->getDateRangeStart($dateRange);

        if ($dateStart) {
            $query->andWhere(['>=', 'dateCreated', $dateStart->format('Y-m-d H:i:s')]);
        }

        // For very large datasets, consider limiting
        // Uncomment if you need to cap at a maximum for performance
        // $query->limit(10000);

        return $query->all();
    }

    /**
     * Extract field values from submissions
     *
     * @param array $submissions
     * @param Rating $field
     * @return array
     */
    private function extractFieldValues(array $submissions, Rating $field): array
    {
        $values = [];

        foreach ($submissions as $submission) {
            $value = $submission->getFieldValue($field->handle);

            if ($value !== null && $value !== '') {
                $values[] = (float)$value;
            }
        }

        return $values;
    }

    /**
     * Get date range start date
     *
     * @param string $dateRange
     * @return \DateTime|null
     */
    private function getDateRangeStart(string $dateRange): ?\DateTime
    {
        $now = new \DateTime();

        switch ($dateRange) {
            case 'today':
                return $now->setTime(0, 0, 0);

            case 'yesterday':
                return $now->modify('-1 day')->setTime(0, 0, 0);

            case 'last7days':
                return $now->modify('-7 days')->setTime(0, 0, 0);

            case 'last30days':
                return $now->modify('-30 days')->setTime(0, 0, 0);

            case 'last90days':
                return $now->modify('-90 days')->setTime(0, 0, 0);

            case 'all':
            default:
                return null;
        }
    }

    /**
     * Get appropriate date format based on range
     *
     * @param string $dateRange
     * @return string
     */
    private function getDateFormatForRange(string $dateRange): string
    {
        switch ($dateRange) {
            case 'today':
            case 'yesterday':
                return 'Y-m-d H:00'; // Hourly

            case 'last7days':
                return 'Y-m-d'; // Daily

            case 'last30days':
            case 'last90days':
                return 'Y-m-d'; // Daily

            case 'all':
            default:
                // For large datasets, group by week instead of month
                return 'Y-W'; // Weekly (Year-Week)
        }
    }

    /**
     * Calculate median value
     *
     * @param array $values
     * @return float
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 == 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate mode value
     *
     * @param array $values
     * @return float|null
     */
    private function calculateMode(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        $frequency = array_count_values(array_map(fn($v) => (string)$v, $values));
        arsort($frequency);

        $maxFrequency = reset($frequency);
        $mode = (float)key($frequency);

        // Return null if all values appear only once
        return $maxFrequency > 1 ? $mode : null;
    }
}
