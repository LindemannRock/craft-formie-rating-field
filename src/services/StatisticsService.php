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
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\DbHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Hidden;
use yii\db\Expression;

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
     * Redis SET key holding every cache key this plugin owns.
     * Used to scope-delete only our keys instead of flushing the shared Craft cache.
     */
    private const REDIS_KEY_INDEX = 'formie-rating-cache-keys';

    /**
     * Sentinel passed as `$groupByHandle` to segregate trend-chart cache cells
     * from field-stats cells. Cannot collide with a real Formie field handle
     * (handles must start with a letter).
     */
    private const TREND_CACHE_VARIANT = '__trend__';

    /**
     * Get all forms that have at least one rating field
     *
     * @return array
     */
    public function getFormsWithRatingFields(): array
    {
        // 1) Aggregate query — which forms contain rating fields, and how many?
        // Replaces the prior O(N forms) loop that called $form->getFields() per form.
        $ratingCountRows = (new Query())
            ->select(['formId' => 'fo.id', 'cnt' => new Expression('COUNT(*)')])
            ->from(['fo' => '{{%formie_forms}}'])
            ->innerJoin(['ff' => '{{%formie_fields}}'], '[[ff.layoutId]] = [[fo.layoutId]]')
            ->where(['ff.type' => Rating::class])
            ->groupBy('fo.id')
            ->all();

        if (empty($ratingCountRows)) {
            return [];
        }

        $ratingCountByForm = array_column($ratingCountRows, 'cnt', 'formId');
        $formIds = array_keys($ratingCountByForm);

        // 2) Aggregate query — live submission count per matched form (one GROUP BY,
        // not N count() calls). Joined through elements to skip trashed/draft/revision rows.
        $submissionCountRows = (new Query())
            ->select(['formId' => 's.formId', 'cnt' => new Expression('COUNT(*)')])
            ->from(['s' => '{{%formie_submissions}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[s.id]]')
            ->where(['s.formId' => $formIds])
            ->andWhere(['e.dateDeleted' => null])
            ->andWhere(['e.draftId' => null])
            ->andWhere(['e.revisionId' => null])
            ->groupBy('s.formId')
            ->all();

        $submissionCountByForm = array_column($submissionCountRows, 'cnt', 'formId');

        // 3) Hydrate the form elements in one element-query (Craft's normal eager-load).
        $forms = Form::find()->id($formIds)->all();

        $formsWithRatings = [];
        foreach ($forms as $form) {
            if (!$form instanceof Form) {
                continue;
            }

            $formsWithRatings[] = [
                'form' => $form,
                'ratingFieldCount' => (int)($ratingCountByForm[$form->id] ?? 0),
                'totalSubmissions' => (int)($submissionCountByForm[$form->id] ?? 0),
            ];
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
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array
     */
    public function getFieldStatistics(Form $form, Rating $field, string $dateRange = 'all', ?string $groupByHandle = null, int|string $siteId = 'all'): array
    {
        // Try to get from cache
        $cachedData = $this->getFromCache($form->id, $field->handle, $dateRange, $groupByHandle, $siteId);

        if ($cachedData !== null) {
            return $cachedData;
        }

        // If grouping is requested, return grouped statistics
        if ($groupByHandle) {
            $stats = $this->getGroupedStatistics($form, $field, $dateRange, $groupByHandle, $siteId);
        } else {
            $stats = $this->calculateFieldStatistics($form, $field, $dateRange, $siteId);
        }

        // Save to cache
        $this->saveToCache($form->id, $field->handle, $dateRange, $groupByHandle, $stats, $siteId);

        return $stats;
    }

    /**
     * Calculate statistics for a specific rating field (not cached)
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array
     */
    private function calculateFieldStatistics(Form $form, Rating $field, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        $submissions = $this->getSubmissions($form, $dateRange, $siteId);

        return $this->calculateStatsForSubmissions($submissions, $field);
    }

    /**
     * Calculate rating statistics for a pre-fetched set of submissions.
     *
     * Use this when another plugin (e.g. Campaign Manager) has already
     * matched submissions by its own criteria and needs NPS/rating math
     * applied to them, independent of form + date range.
     *
     * The caller is responsible for fetching the submissions. Results are
     * not cached — caching is skipped because the caller owns the matching
     * criteria and no cache key can be derived safely from here.
     *
     * @param Submission[] $submissions Pre-fetched submissions
     * @param Rating $field The rating field to analyze
     * @return array Stats in the same shape as getFieldStatistics()'s summary output
     * @since 3.16.0
     */
    public function calculateStatsForSubmissions(array $submissions, Rating $field): array
    {
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
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array
     */
    public function getGroupedStatistics(Form $form, Rating $field, string $dateRange, string $groupByHandle, int|string $siteId = 'all'): array
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
        $dateBounds = DateRangeHelper::getBounds($dateRange);
        $groupByUid = $groupByField->uid;

        // Resolve the Craft table-prefix syntax to a raw table name. DbHelper's identifier
        // validator only permits alphanumerics + underscores/dots/etc — not `{`, `}`, or `%`.
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');

        // Build the query using field UIDs with DB-agnostic helpers
        $groupByExpr = DbHelper::jsonExtract("{$submissionsTable}.content", $groupByUid);
        $ratingExpr = DbHelper::jsonExtract("{$submissionsTable}.content", $ratingFieldUid);
        $ratingCast = new Expression("CAST($ratingExpr AS DECIMAL(10,2))");

        $query = (new Query())
            ->select([
                'groupValue' => "COALESCE(NULLIF($groupByExpr, ''), '(Not Set)')",
                'count' => 'COUNT(*)',
                'ratingValues' => DbHelper::groupConcat($ratingCast),
            ])
            ->from('{{%formie_submissions}}')
            ->where([
                '{{%formie_submissions}}.formId' => $form->id,
                '{{%formie_submissions}}.isIncomplete' => false,
                '{{%formie_submissions}}.isSpam' => false,
            ])
            ->andWhere(['not', [$ratingExpr => null]])
            ->andWhere(['!=', $ratingExpr, ''])
            ->groupBy('groupValue')
            ->orderBy(['count' => SORT_DESC]);

        // Filter by site when a specific site is requested.
        // formie_submissions has no siteId column; site association lives in elements_sites.
        if ($siteId !== 'all') {
            // Use the resolved table name inside [[...]] — Yii's {{%table}} expansion
            // doesn't nest cleanly inside [[col]] brackets (corrupts the column parser).
            $query->innerJoin(
                '{{%elements_sites}} es_site_filter',
                "[[es_site_filter.elementId]] = [[{$submissionsTable}.id]] AND [[es_site_filter.siteId]] = :filterSiteId",
                [':filterSiteId' => (int)$siteId]
            );
        }

        // Add date filter if specified
        if ($dateBounds['start']) {
            $query->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($dateBounds['start'])]);
        }
        if ($dateBounds['end']) {
            $query->andWhere(['<', 'dateCreated', Db::prepareDateForDb($dateBounds['end'])]);
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
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array
     */
    public function getGroupSubmissions(Form $form, string $groupByHandle, string $groupValue, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        $submissions = $this->getSubmissions($form, $dateRange, $siteId);
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
     * Normalise a siteId value to a cache-safe string segment.
     *
     * @param int|string $siteId
     * @return string
     */
    private function normaliseSiteIdForKey(int|string $siteId): string
    {
        if ($siteId === 'all') {
            return 'all';
        }

        return (string)(int)$siteId;
    }

    /**
     * Generate cache key for Redis/database storage
     *
     * @param int $formId
     * @param string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param int|string $siteId
     * @return string
     */
    private function getCacheKey(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle = null, int|string $siteId = 'all'): string
    {
        $siteSegment = $this->normaliseSiteIdForKey($siteId);
        $key = "formie-rating-stats-{$formId}-{$fieldHandle}-{$dateRange}-{$siteSegment}";

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
     * @param int|string $siteId
     * @return string
     */
    public function getCacheFilename(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle = null, int|string $siteId = 'all'): string
    {
        $siteSegment = $this->normaliseSiteIdForKey($siteId);
        $key = "{$formId}-{$fieldHandle}-{$dateRange}-{$siteSegment}";

        if ($groupByHandle) {
            $key .= "-{$groupByHandle}";
        }

        return $formId . '-' . md5($key) . '.cache';
    }

    /**
     * Get statistics from cache
     *
     * @param int $formId
     * @param string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param int|string $siteId
     * @return array|null
     */
    private function getFromCache(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle = null, int|string $siteId = 'all'): ?array
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            $cacheKey = $this->getCacheKey($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);
            $cached = Craft::$app->cache->get($cacheKey);
            return $cached !== false ? $cached : null;
        }

        // Use file-based cache (default)
        $cachePath = $this->getCachePath();
        $filename = $this->getCacheFilename($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);
        $filepath = $cachePath . $filename;

        if (!file_exists($filepath)) {
            return null;
        }

        // Read and decode cache (JSON — never unserialize untrusted file contents)
        $data = file_get_contents($filepath);
        if ($data === false) {
            return null;
        }

        $decoded = json_decode($data, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Save statistics to cache
     *
     * @param int $formId
     * @param string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param array $stats
     * @param int|string $siteId
     * @return bool
     */
    private function saveToCache(int $formId, string $fieldHandle, string $dateRange, ?string $groupByHandle, array $stats, int|string $siteId = 'all'): bool
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            $cacheKey = $this->getCacheKey($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);
            $cache = Craft::$app->cache;

            Craft::info("Attempting to save to cache. Type: " . get_class($cache) . ", Key: {$cacheKey}", __METHOD__);

            $result = $cache->set($cacheKey, $stats);

            if ($result) {
                // Track the key in our Redis index so we can scoped-flush later
                // (Yii's $cache->flush() would wipe every other plugin's keys too)
                $this->trackRedisCacheKey($cacheKey);
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

        $filename = $this->getCacheFilename($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);
        $filepath = $cachePath . $filename;

        // Encode as JSON (avoids unsafe unserialize on read)
        $data = json_encode($stats);

        if ($data === false) {
            Craft::error("Failed to JSON-encode cache for file: {$filename}", __METHOD__);
            return false;
        }

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
        $settings = FormieRatingField::$plugin->getSettings();

        // Redis storage — filter the SADD index by form-id prefix and delete matching keys.
        // (Without this branch, Redis users got silent no-ops on submission save/delete.)
        if ($settings->cacheStorageMethod === 'redis') {
            $cache = Craft::$app->cache;
            if (!$cache instanceof \yii\redis\Cache) {
                return true;
            }

            $tracked = $cache->redis->executeCommand('SMEMBERS', [self::REDIS_KEY_INDEX]);
            if (!is_array($tracked)) {
                return true;
            }

            // All cache keys for this form start with "formie-rating-stats-{$formId}-"
            // (see getCacheKey() — formId always follows the static prefix).
            $prefix = "formie-rating-stats-{$formId}-";
            $cleared = true;
            foreach ($tracked as $key) {
                if (!str_starts_with((string)$key, $prefix)) {
                    continue;
                }
                if (!$cache->delete($key)) {
                    $cleared = false;
                }
                $cache->redis->executeCommand('SREM', [self::REDIS_KEY_INDEX, $key]);
            }

            return $cleared;
        }

        $cachePath = $this->getCachePath();

        if (!is_dir($cachePath)) {
            return true;
        }

        // Cache filenames are prefixed with "{formId}-" (see getCacheFilename)
        $files = glob($cachePath . $formId . '-*.cache');

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
     * Clear all statistics cache
     *
     * @return bool
     */
    public function clearAllCache(): bool
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Clear Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            $cache = Craft::$app->cache;

            // Delete only the keys this plugin owns — never call $cache->flush(),
            // which would wipe every other plugin's cache keys too.
            if ($cache instanceof \yii\redis\Cache) {
                $tracked = $cache->redis->executeCommand('SMEMBERS', [self::REDIS_KEY_INDEX]);
                if (is_array($tracked)) {
                    foreach ($tracked as $key) {
                        $cache->delete($key);
                    }
                }
                $cache->redis->executeCommand('DEL', [self::REDIS_KEY_INDEX]);
                // Sweep the legacy counter key (replaced by SCARD on the index set)
                $cache->redis->executeCommand('DEL', ['formie-rating-cache-count']);
            }

            return true;
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
     * Track a cache key in our Redis index so we can scope-delete it later
     * without flushing the whole shared Craft cache.
     */
    private function trackRedisCacheKey(string $cacheKey): void
    {
        $cache = Craft::$app->cache;
        if ($cache instanceof \yii\redis\Cache) {
            $cache->redis->executeCommand('SADD', [self::REDIS_KEY_INDEX, $cacheKey]);
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

        // For Redis, count members of our key-index set
        if ($settings->cacheStorageMethod === 'redis') {
            try {
                $cache = Craft::$app->cache;
                if ($cache instanceof \yii\redis\Cache) {
                    $count = $cache->redis->executeCommand('SCARD', [self::REDIS_KEY_INDEX]);
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
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array
     */
    public function getTrendData(Form $form, Rating $field, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        // Try cache. The sentinel groupByHandle '__trend__' segregates trend data from
        // field-stats and from any real groupBy (Formie field handles must start with a letter).
        $cached = $this->getFromCache($form->id, $field->handle, $dateRange, self::TREND_CACHE_VARIANT, $siteId);
        if ($cached !== null) {
            return $cached;
        }

        // SQL-aggregate per bucket — replaces the prior approach of materialising every
        // submission element into PHP and grouping in a foreach (3.2 + 3.4 cold-path cost).
        $isNps = $field->ratingType === Rating::RATING_TYPE_NPS;
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');
        $ratingExpr = DbHelper::jsonExtract("{$submissionsTable}.content", $field->uid);
        $ratingCast = "CAST({$ratingExpr} AS DECIMAL(10,2))";

        $bucketExpr = $this->buildTrendBucketExpression($dateRange, "{$submissionsTable}.dateCreated");

        $query = (new Query())
            ->select([
                'bucket' => $bucketExpr,
                'cnt' => new Expression('COUNT(*)'),
                'avgValue' => new Expression("AVG({$ratingCast})"),
                'promoters' => new Expression("SUM(CASE WHEN {$ratingCast} >= 9 THEN 1 ELSE 0 END)"),
                'detractors' => new Expression("SUM(CASE WHEN {$ratingCast} <= 6 THEN 1 ELSE 0 END)"),
            ])
            ->from('{{%formie_submissions}}')
            ->where([
                "{$submissionsTable}.formId" => $form->id,
                "{$submissionsTable}.isIncomplete" => false,
                "{$submissionsTable}.isSpam" => false,
            ])
            ->andWhere(['not', [$ratingExpr => null]])
            ->andWhere(['!=', $ratingExpr, ''])
            ->groupBy([$bucketExpr])
            ->orderBy([$bucketExpr]);

        // Date range filter (matches getSubmissions semantics)
        $bounds = DateRangeHelper::getBounds($dateRange);
        if ($bounds['start']) {
            $query->andWhere(['>=', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['start'])]);
        }
        if ($bounds['end']) {
            $query->andWhere(['<', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['end'])]);
        }

        // Site filter — uses the resolved table name inside [[...]] (Yii's {{%table}} expansion
        // doesn't nest cleanly inside [[col]] brackets; corrupts the column-reference parser).
        if ($siteId !== 'all') {
            $query->innerJoin(
                '{{%elements_sites}} es_site_filter',
                "[[es_site_filter.elementId]] = [[{$submissionsTable}.id]] AND [[es_site_filter.siteId]] = :filterSiteId",
                [':filterSiteId' => (int)$siteId]
            );
        }

        $rows = $query->all();

        // Compute the per-bucket metric in PHP — runs over O(buckets), not O(submissions)
        $chartData = [];
        foreach ($rows as $row) {
            $cnt = (int)$row['cnt'];
            if ($cnt === 0) {
                continue;
            }

            if ($isNps) {
                // NPS: 0–6 = detractors, 7–8 = passives, 9–10 = promoters
                $promoters = (int)$row['promoters'];
                $detractors = (int)$row['detractors'];
                $bucketValue = round((($promoters - $detractors) / $cnt) * 100, 1);
            } else {
                $bucketValue = round((float)$row['avgValue'], 2);
            }

            $chartData[] = [
                'date' => (string)$row['bucket'],
                'value' => $bucketValue,
                'count' => $cnt,
            ];
        }

        // Limit to max 50 data points for performance (preserves prior behaviour)
        if (count($chartData) > 50) {
            $step = (int)ceil(count($chartData) / 50);
            $sampledData = [];
            foreach ($chartData as $index => $data) {
                if ($index % $step === 0) {
                    $sampledData[] = $data;
                }
            }
            $chartData = $sampledData;
        }

        $result = [
            'labels' => array_column($chartData, 'date'),
            'values' => array_column($chartData, 'value'),
            'counts' => array_column($chartData, 'count'),
            'scaleMin' => $isNps ? -100 : 0,
            'scaleMax' => $isNps ? 100 : (int)$field->maxValue,
        ];

        $this->saveToCache($form->id, $field->handle, $dateRange, self::TREND_CACHE_VARIANT, $result, $siteId);

        return $result;
    }

    /**
     * Build a SQL Expression that buckets a UTC datetime column into the same
     * label format the prior PHP-based bucketing used (Y-m-d, Y-m-d H:00, Y-m, Y-W).
     *
     * Produces timezone-aware labels using Craft's site timezone, matching
     * `DateFormatHelper::localDateExpression()` semantics.
     */
    private function buildTrendBucketExpression(string $dateRange, string $column): Expression
    {
        $offset = DateFormatHelper::getCraftTimezoneOffset();
        $isMysql = Craft::$app->getDb()->getIsMysql();

        if ($isMysql) {
            $convertTz = "CONVERT_TZ([[{$column}]], '+00:00', :tzOffset)";
            $format = match ($dateRange) {
                'today', 'yesterday' => '%Y-%m-%d %H:00',
                'thisYear', 'lastYear' => '%Y-%m',
                'all', 'alltime' => '%x-%v',
                default => '%Y-%m-%d',
            };
            return new Expression(
                "DATE_FORMAT({$convertTz}, '{$format}')",
                [':tzOffset' => $offset],
            );
        }

        // PostgreSQL
        $convertTz = "([[{$column}]] AT TIME ZONE 'UTC' AT TIME ZONE :tzOffset)";
        $format = match ($dateRange) {
            'today', 'yesterday' => 'YYYY-MM-DD HH24":00"',
            'thisYear', 'lastYear' => 'YYYY-MM',
            'all', 'alltime' => 'IYYY-IW',
            default => 'YYYY-MM-DD',
        };
        return new Expression(
            "TO_CHAR({$convertTz}, '{$format}')",
            [':tzOffset' => $offset],
        );
    }

    /**
     * Get distribution data for chart display
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array
     */
    public function getDistributionData(Form $form, Rating $field, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        $stats = $this->getFieldStatistics($form, $field, $dateRange, null, $siteId);

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
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return int
     */
    public function getTotalSubmissions(Form $form, string $dateRange = 'all', int|string $siteId = 'all'): int
    {
        return count($this->getSubmissions($form, $dateRange, $siteId));
    }

    /**
     * Build summary export rows — one row per rating field with aggregate stats.
     *
     * Columns are the same for all field types; inapplicable cells are filled with '—'.
     * NPS fields omit Median and Most Common. Star/Emoji fields omit NPS, Promoters,
     * Passives, and Detractors columns.
     *
     * @param Form $form
     * @param string $dateRange
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array{headers: string[], rows: array[]}
     * @since 3.16.0
     */
    public function buildSummaryExportRows(Form $form, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        $ratingFields = $this->getRatingFieldsForForm($form);

        if (empty($ratingFields)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = [
            Craft::t('formie-rating-field', 'Field Label'),
            Craft::t('formie-rating-field', 'Field Type'),
            Craft::t('formie-rating-field', 'Total Responses'),
            Craft::t('formie-rating-field', 'NPS Score'),
            Craft::t('formie-rating-field', 'Promoters'),
            Craft::t('formie-rating-field', 'Promoters %'),
            Craft::t('formie-rating-field', 'Passives'),
            Craft::t('formie-rating-field', 'Passives %'),
            Craft::t('formie-rating-field', 'Detractors'),
            Craft::t('formie-rating-field', 'Detractors %'),
            Craft::t('formie-rating-field', 'Average'),
            Craft::t('formie-rating-field', 'Median'),
            Craft::t('formie-rating-field', 'Most Common'),
        ];

        $rows = [];

        foreach ($ratingFields as $field) {
            $stats = $this->getFieldStatistics($form, $field, $dateRange, null, $siteId);
            $isNps = $field->ratingType === Rating::RATING_TYPE_NPS;

            $row = [
                $field->label,
                $field->ratingType,
                $stats['totalResponses'] ?? 0,
            ];

            if ($isNps) {
                $row[] = $stats['npsScore'] ?? 0;
                $row[] = $stats['promoters'] ?? 0;
                $row[] = $stats['promotersPercentage'] ?? 0;
                $row[] = $stats['passives'] ?? 0;
                $row[] = $stats['passivesPercentage'] ?? 0;
                $row[] = $stats['detractors'] ?? 0;
                $row[] = $stats['detractorsPercentage'] ?? 0;
                $row[] = $stats['average'] ?? 0;
                $row[] = '—';
                $row[] = '—';
            } else {
                $row[] = '—';
                $row[] = '—';
                $row[] = '—';
                $row[] = '—';
                $row[] = '—';
                $row[] = '—';
                $row[] = '—';
                $row[] = $stats['average'] ?? 0;
                $row[] = $stats['median'] ?? 0;
                $row[] = $stats['mode'] ?? '—';
            }

            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Build raw responses export rows — one row per submission.
     *
     * Columns: Submission Date, Submission ID, then one column per rating field.
     *
     * @param Form $form
     * @param string $dateRange
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array{headers: string[], rows: array[]}
     * @since 3.16.0
     */
    public function buildRawResponsesExportRows(Form $form, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        $ratingFields = $this->getRatingFieldsForForm($form);

        if (empty($ratingFields)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = [
            Craft::t('formie-rating-field', 'Submission Date'),
            Craft::t('formie-rating-field', 'Submission ID'),
            Craft::t('formie-rating-field', 'Site'),
        ];
        foreach ($ratingFields as $field) {
            $headers[] = $field->label;
        }

        $submissions = $this->getSubmissions($form, $dateRange, $siteId);
        $rows = [];
        $sitesService = Craft::$app->getSites();

        foreach ($submissions as $submission) {
            $site = $submission->siteId ? $sitesService->getSiteById($submission->siteId) : null;

            $row = [
                $submission->dateCreated->format('Y-m-d H:i:s'),
                $submission->id,
                $site?->name ?? '—',
            ];

            foreach ($ratingFields as $field) {
                $value = $submission->getFieldValue($field->handle);
                $row[] = $value ?? '';
            }

            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Build group breakdown export rows — one row per group value.
     *
     * Columns: group field label, Submissions Count, then per-field metrics.
     * NPS fields get: NPS Score, Promoters (%), Passives (%), Detractors (%).
     * Star/Emoji fields get: Average, Median.
     *
     * Returns empty headers/rows when $groupByHandle is null or empty.
     *
     * @param Form $form
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array{headers: string[], rows: array[]}
     * @since 3.16.0
     */
    public function buildGroupedExportRows(Form $form, string $dateRange = 'all', ?string $groupByHandle = null, int|string $siteId = 'all'): array
    {
        if (!$groupByHandle) {
            return ['headers' => [], 'rows' => []];
        }

        $ratingFields = $this->getRatingFieldsForForm($form);

        if (empty($ratingFields)) {
            return ['headers' => [], 'rows' => []];
        }

        // Resolve group-by field label
        $groupByFieldLabel = $groupByHandle;
        foreach ($form->getFields() as $field) {
            if ($field->handle === $groupByHandle) {
                $groupByFieldLabel = $field->label;
                break;
            }
        }

        $headers = [
            $groupByFieldLabel,
            Craft::t('formie-rating-field', 'Submissions Count'),
        ];

        foreach ($ratingFields as $field) {
            if ($field->ratingType === Rating::RATING_TYPE_NPS) {
                $headers[] = $field->label . ' - ' . Craft::t('formie-rating-field', 'NPS Score');
                $headers[] = $field->label . ' - ' . Craft::t('formie-rating-field', 'Promoters (%)');
                $headers[] = $field->label . ' - ' . Craft::t('formie-rating-field', 'Passives (%)');
                $headers[] = $field->label . ' - ' . Craft::t('formie-rating-field', 'Detractors (%)');
            } else {
                $headers[] = $field->label . ' - ' . Craft::t('formie-rating-field', 'Average');
                $headers[] = $field->label . ' - ' . Craft::t('formie-rating-field', 'Median');
            }
        }

        // Use the first rating field to establish the group list
        $firstField = $ratingFields[0];
        $groupedStats = $this->getFieldStatistics($form, $firstField, $dateRange, $groupByHandle, $siteId);

        if (empty($groupedStats['groups'])) {
            return ['headers' => $headers, 'rows' => []];
        }

        $rows = [];

        foreach ($groupedStats['groups'] as $group) {
            $row = [$group['label'], $group['count']];

            foreach ($ratingFields as $field) {
                $fieldStats = $this->getFieldStatistics($form, $field, $dateRange, $groupByHandle, $siteId);

                $groupStats = null;
                foreach ($fieldStats['groups'] as $g) {
                    if ($g['label'] === $group['label']) {
                        $groupStats = $g;
                        break;
                    }
                }

                if ($groupStats) {
                    if ($field->ratingType === Rating::RATING_TYPE_NPS) {
                        $row[] = $groupStats['npsScore'] ?? 0;
                        $row[] = ($groupStats['promoters'] ?? 0) . ' (' . ($groupStats['promotersPercentage'] ?? 0) . '%)';
                        $row[] = ($groupStats['passives'] ?? 0) . ' (' . ($groupStats['passivesPercentage'] ?? 0) . '%)';
                        $row[] = ($groupStats['detractors'] ?? 0) . ' (' . ($groupStats['detractorsPercentage'] ?? 0) . '%)';
                    } else {
                        $row[] = $groupStats['average'] ?? 0;
                        $row[] = $groupStats['median'] ?? 0;
                    }
                } else {
                    if ($field->ratingType === Rating::RATING_TYPE_NPS) {
                        $row[] = '';
                        $row[] = '';
                        $row[] = '';
                        $row[] = '';
                    } else {
                        $row[] = '';
                        $row[] = '';
                    }
                }
            }

            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Get submissions for a form within a date range, optionally filtered by site.
     *
     * When $siteId is 'all', submissions are fetched cross-site via siteId('*').
     * When $siteId is an int, the query is scoped to that specific site.
     *
     * @param Form $form
     * @param string $dateRange
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @return array
     */
    private function getSubmissions(Form $form, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        $query = Submission::find()
            ->formId($form->id)
            ->orderBy(['dateCreated' => SORT_DESC]);

        if ($siteId === 'all') {
            // Explicitly request all sites to override Craft's current-site default
            $query->siteId('*');
        } else {
            $query->siteId((int)$siteId);
        }

        $bounds = DateRangeHelper::getBounds($dateRange);

        if ($bounds['start']) {
            $query->andWhere(['>=', 'dateCreated', Db::prepareDateForDb($bounds['start'])]);
        }
        if ($bounds['end']) {
            $query->andWhere(['<', 'dateCreated', Db::prepareDateForDb($bounds['end'])]);
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
