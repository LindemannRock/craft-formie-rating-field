<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\formieratingfield\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\DbHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Categories;
use verbb\formie\fields\Dropdown;
use verbb\formie\fields\Entries;
use verbb\formie\fields\Hidden;
use verbb\formie\fields\Radio;
use verbb\formie\fields\SingleLineText;
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
     * Maximum number of grouped rows retained in a cached dashboard payload.
     *
     * The complete group count is still returned as `totalGroups`. Explicit
     * grouped exports intentionally call the unbounded grouped-statistics API.
     */
    private const GROUPED_OVERVIEW_LIMIT = 100;

    /**
     * Sentinel passed as `$groupByHandle` to segregate trend-chart cache cells
     * from field-stats cells. Cannot collide with a real Formie field handle
     * (handles must start with a letter).
     */
    private const TREND_CACHE_VARIANT = '__trend__';

    /**
     * Get all forms that have at least one rating field
     *
     * @param int|string|array<int> $siteId Specific site ID, list of site IDs, or 'all' for cross-site aggregate
     * @return array
     */
    public function getFormsWithRatingFields(int|string|array $siteId = 'all'): array
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
        // formie_submissions has no siteId column — site association lives in elements_sites.
        $submissionCountQuery = (new Query())
            ->select(['formId' => 's.formId', 'cnt' => new Expression(is_array($siteId) ? 'COUNT(DISTINCT [[s.id]])' : 'COUNT(*)')])
            ->from(['s' => '{{%formie_submissions}}'])
            ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[s.id]]')
            ->where(['s.formId' => $formIds])
            ->andWhere(['s.isIncomplete' => false])
            ->andWhere(['s.isSpam' => false])
            ->andWhere(['e.dateDeleted' => null])
            ->andWhere(['e.draftId' => null])
            ->andWhere(['e.revisionId' => null])
            ->groupBy('s.formId');

        // Site scoping: when a specific siteId or list is selected, count only
        // submissions that exist in those sites via elements_sites.
        if (is_int($siteId)) {
            $submissionCountQuery
                ->innerJoin(['es' => '{{%elements_sites}}'], '[[es.elementId]] = [[s.id]]')
                ->andWhere(['es.siteId' => $siteId]);
        } elseif (is_array($siteId)) {
            if ($siteId === []) {
                $submissionCountQuery->andWhere('0=1');
            } else {
                $submissionCountQuery
                    ->innerJoin(['es' => '{{%elements_sites}}'], '[[es.elementId]] = [[s.id]]')
                    ->andWhere(['es.siteId' => $siteId]);
            }
        }

        $submissionCountRows = $submissionCountQuery->all();

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
                $field instanceof SingleLineText ||
                $field instanceof Hidden ||
                $field instanceof Dropdown ||
                $field instanceof Radio ||
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
        $dateRange = $this->normaliseDateRange($dateRange);

        // Try to get from cache
        $cachedData = $this->getFromCache($form->id, $field, $dateRange, $groupByHandle, $siteId);

        if ($cachedData !== null) {
            return $cachedData;
        }

        // If grouping is requested, return grouped statistics
        if ($groupByHandle) {
            $stats = $this->getGroupedStatistics(
                $form,
                $field,
                $dateRange,
                $groupByHandle,
                $siteId,
                self::GROUPED_OVERVIEW_LIMIT,
            );
        } else {
            $stats = $this->calculateFieldStatistics($form, $field, $dateRange, $siteId);
        }

        $stats['generatedAt'] = DateFormatHelper::toApiString(new \DateTime('now', new \DateTimeZone('UTC')));

        // Save to cache
        $this->saveToCache($form->id, $field, $dateRange, $groupByHandle, $stats, $siteId);

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
        // Pull just the rating values via SQL — avoids hydrating the full submission
        // element graph for what's just a list of floats. Per-form path is the hot path
        // on cache miss; this is where the slowness lived.
        $values = $this->extractFieldValuesViaSql($form, $field, $dateRange, $siteId);

        return $this->buildStatsFromValues($values, $field);
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
        return $this->buildStatsFromValues($values, $field);
    }

    /**
     * Build the stats array from a flat list of rating values.
     *
     * Shared body between `calculateStatsForSubmissions()` (public API — accepts
     * pre-fetched submission elements) and `calculateFieldStatistics()` (private
     * cache-miss path — pulls values directly via SQL).
     *
     * @param float[] $values
     * @param Rating $field
     * @return array
     */
    private function buildStatsFromValues(array $values, Rating $field): array
    {
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
     * @param int|null $limit Optional group-row cap; the complete count remains available as `totalGroups`
     * @return array
     */
    public function getGroupedStatistics(
        Form $form,
        Rating $field,
        string $dateRange,
        string $groupByHandle,
        int|string $siteId = 'all',
        ?int $limit = null,
    ): array {
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

        // Build the query using field UIDs with DB-agnostic helpers
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');
        $groupByExpr = DbHelper::jsonExtract('{{%formie_submissions}}.content', $groupByUid);
        $normalizedGroupExpr = "COALESCE(NULLIF($groupByExpr, ''), '(Not Set)')";
        $ratingExpr = DbHelper::jsonExtract('{{%formie_submissions}}.content', $ratingFieldUid);
        $ratingCast = "CAST($ratingExpr AS DECIMAL(10,2))";

        $select = [
            'groupValue' => $normalizedGroupExpr,
            'count' => new Expression('COUNT(*)'),
            'average' => new Expression("AVG($ratingCast)"),
        ];

        if ($field->ratingType === Rating::RATING_TYPE_NPS) {
            $select['promoters'] = new Expression("SUM(CASE WHEN $ratingCast >= 9 THEN 1 ELSE 0 END)");
            $select['passives'] = new Expression("SUM(CASE WHEN $ratingCast >= 7 AND $ratingCast <= 8 THEN 1 ELSE 0 END)");
            $select['detractors'] = new Expression("SUM(CASE WHEN $ratingCast <= 6 THEN 1 ELSE 0 END)");

            for ($i = 0; $i <= 10; $i++) {
                $select["score{$i}"] = new Expression("SUM(CASE WHEN FLOOR($ratingCast) = $i THEN 1 ELSE 0 END)");
            }
        }

        $query = (new Query())
            ->select($select)
            ->from('{{%formie_submissions}}');

        $this->applyGroupedStatisticsFilters($query, $form->id, $ratingExpr, $submissionsTable, $dateBounds, $siteId)
            ->groupBy('groupValue')
            ->orderBy(['count' => SORT_DESC, 'groupValue' => SORT_ASC]);

        $totalGroups = (int)$this->buildGroupedStatisticsTotalQuery(
            $form->id,
            $normalizedGroupExpr,
            $ratingExpr,
            $submissionsTable,
            $dateBounds,
            $siteId,
        )->scalar();
        if ($limit !== null) {
            $query->limit(max(1, $limit));
        }

        $results = $query->all();

        $medianValueCountsByGroup = [];

        if (
            $results !== [] && (
                $field->ratingType === Rating::RATING_TYPE_STAR ||
                $field->ratingType === Rating::RATING_TYPE_EMOJI
            )
        ) {
            $valuesQuery = (new Query())
                ->select([
                    'groupValue' => $normalizedGroupExpr,
                    'ratingValue' => new Expression($ratingCast),
                    'valueCount' => new Expression('COUNT(*)'),
                ])
                ->from('{{%formie_submissions}}')
                ->groupBy(['groupValue', 'ratingValue'])
                ->orderBy(['groupValue' => SORT_ASC, 'ratingValue' => SORT_ASC]);

            $this->applyGroupedStatisticsFilters($valuesQuery, $form->id, $ratingExpr, $submissionsTable, $dateBounds, $siteId);
            $valuesQuery->andWhere([
                'in',
                new Expression($normalizedGroupExpr),
                array_column($results, 'groupValue'),
            ]);

            foreach ($valuesQuery->all() as $valueRow) {
                $groupLabel = (string)($valueRow['groupValue'] ?? '(Not Set)');
                $ratingValue = (string)(float)$valueRow['ratingValue'];
                $medianValueCountsByGroup[$groupLabel][$ratingValue] = (int)$valueRow['valueCount'];
            }
        }

        $groupedStats = $this->buildGroupedStatsFromAggregateRows($results, $field, $medianValueCountsByGroup);

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
            'totalGroups' => $totalGroups,
            'isLimited' => count($groupedStats) < $totalGroups,
        ];
    }

    /**
     * Build the minimal exact-count query for a grouped statistics result.
     *
     * Counting the normalized expression keeps null and empty group values in
     * the shared `(Not Set)` bucket without repeating the aggregate result
     * query's averages, NPS breakdown, grouping, or ordering.
     */
    private function buildGroupedStatisticsTotalQuery(
        int $formId,
        string $normalizedGroupExpr,
        string $ratingExpr,
        string $submissionsTable,
        array $dateBounds,
        int|string $siteId,
    ): Query {
        $query = (new Query())
            ->select([
                'totalGroups' => new Expression("COUNT(DISTINCT {$normalizedGroupExpr})"),
            ])
            ->from('{{%formie_submissions}}');

        return $this->applyGroupedStatisticsFilters(
            $query,
            $formId,
            $ratingExpr,
            $submissionsTable,
            $dateBounds,
            $siteId,
        );
    }

    /**
     * Apply the shared filters for grouped statistics queries.
     *
     * @param Query $query
     * @param int $formId
     * @param string $ratingExpr
     * @param string $submissionsTable
     * @param array $dateBounds
     * @param int|string $siteId
     * @return Query
     */
    private function applyGroupedStatisticsFilters(Query $query, int $formId, string $ratingExpr, string $submissionsTable, array $dateBounds, int|string $siteId): Query
    {
        $query
            ->where([
                '{{%formie_submissions}}.formId' => $formId,
                '{{%formie_submissions}}.isIncomplete' => false,
                '{{%formie_submissions}}.isSpam' => false,
            ])
            ->andWhere(['not', [$ratingExpr => null]])
            ->andWhere(['!=', $ratingExpr, '']);

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

        // Qualify the column — when the site filter joins elements_sites
        // (which also has dateCreated), an unqualified column is ambiguous
        // and errors on PostgreSQL.
        if ($dateBounds['start']) {
            $query->andWhere(['>=', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($dateBounds['start'])]);
        }
        if ($dateBounds['end']) {
            $query->andWhere(['<', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($dateBounds['end'])]);
        }

        return $query;
    }

    /**
     * Build grouped response payloads from SQL aggregate rows.
     *
     * @param array $rows
     * @param Rating $field
     * @param array<string, array<string, int>> $medianValueCountsByGroup
     * @return array
     */
    private function buildGroupedStatsFromAggregateRows(array $rows, Rating $field, array $medianValueCountsByGroup = []): array
    {
        $groupedStats = [];

        foreach ($rows as $row) {
            $groupLabel = $row['groupValue'] ?? '(Not Set)';
            $count = (int)$row['count'];

            $stats = [
                'label' => $groupLabel,
                'count' => $count,
            ];

            switch ($field->ratingType) {
                case Rating::RATING_TYPE_NPS:
                    $stats = array_merge($stats, $this->calculateGroupedNpsStats($row));
                    break;

                case Rating::RATING_TYPE_STAR:
                case Rating::RATING_TYPE_EMOJI:
                    $stats['average'] = $count > 0 ? round((float)$row['average'], 2) : 0;
                    $stats['median'] = $this->calculateMedianFromValueCounts($medianValueCountsByGroup[$groupLabel] ?? []);
                    break;
            }

            $groupedStats[] = $stats;
        }

        return $groupedStats;
    }

    /**
     * Build grouped NPS stats from SQL aggregate counts.
     *
     * @param array $row
     * @return array
     */
    private function calculateGroupedNpsStats(array $row): array
    {
        $total = (int)$row['count'];
        $promoters = (int)($row['promoters'] ?? 0);
        $passives = (int)($row['passives'] ?? 0);
        $detractors = (int)($row['detractors'] ?? 0);

        $distribution = [];
        for ($i = 0; $i <= 10; $i++) {
            $count = (int)($row["score{$i}"] ?? 0);
            $distribution[] = [
                'value' => $i,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return [
            'npsScore' => $total > 0 ? round((($promoters - $detractors) / $total) * 100, 1) : 0,
            'promoters' => $promoters,
            'promotersPercentage' => $total > 0 ? round(($promoters / $total) * 100, 1) : 0,
            'passives' => $passives,
            'passivesPercentage' => $total > 0 ? round(($passives / $total) * 100, 1) : 0,
            'detractors' => $detractors,
            'detractorsPercentage' => $total > 0 ? round(($detractors / $total) * 100, 1) : 0,
            'average' => $total > 0 ? round((float)$row['average'], 2) : 0,
            'distribution' => $distribution,
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
     * @param int|null $limit Optional row cap applied after the group predicate. Used by
     *                       the export path to prevent OOM without dropping matching rows
     *                       behind unrelated submissions.
     * @return list<Submission>
     */
    public function getGroupSubmissions(Form $form, string $groupByHandle, string $groupValue, string $dateRange = 'all', int|string $siteId = 'all', ?int $limit = null): array
    {
        $query = $this->buildGroupSubmissionIdQuery($form, $groupByHandle, $groupValue, $dateRange, $siteId)
            ->orderBy($this->groupSubmissionOrder());

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $submissionIds = array_map('intval', $query->column());

        return $this->hydrateGroupSubmissions($submissionIds, $siteId);
    }

    /**
     * Get one bounded page of submissions for a raw grouped-statistics value.
     *
     * The group predicate mirrors getGroupedStatistics(): scalar and relational
     * values are compared against their stored JSON representation, while null
     * and empty values share the `(Not Set)` group. Only the selected page IDs
     * are hydrated as Submission elements.
     *
     * @param Form $form
     * @param string $groupByHandle
     * @param string $groupValue
     * @param string $dateRange
     * @param int|string $siteId Specific site ID (int) or 'all' for cross-site aggregate
     * @param int $limit
     * @param int $offset
     * @return array{submissions: list<Submission>, totalCount: int}
     * @since 3.22.0
     */
    public function getPaginatedGroupSubmissions(
        Form $form,
        string $groupByHandle,
        string $groupValue,
        string $dateRange = 'all',
        int|string $siteId = 'all',
        int $limit = 100,
        int $offset = 0,
    ): array {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $query = $this->buildGroupSubmissionIdQuery($form, $groupByHandle, $groupValue, $dateRange, $siteId);

        $totalCount = (int)(clone $query)->count();
        $submissionIds = array_map(
            'intval',
            $query
                ->orderBy($this->groupSubmissionOrder())
                ->limit($limit)
                ->offset($offset)
                ->column(),
        );

        return [
            'submissions' => $this->hydrateGroupSubmissions($submissionIds, $siteId),
            'totalCount' => $totalCount,
        ];
    }

    /**
     * Build the shared SQL query for one raw grouped-statistics value.
     *
     * Filtering before limiting keeps grouped exports complete up to their own cap,
     * and comparing the stored JSON representation keeps relational group links and
     * exports aligned with getGroupedStatistics().
     */
    private function buildGroupSubmissionIdQuery(
        Form $form,
        string $groupByHandle,
        string $groupValue,
        string $dateRange,
        int|string $siteId,
    ): Query {
        $groupField = null;
        foreach ($form->getFields() as $field) {
            if ($field->handle === $groupByHandle) {
                $groupField = $field;
                break;
            }
        }

        if ($groupField === null || $groupField->uid === null) {
            throw new \InvalidArgumentException("Group field '{$groupByHandle}' was not found on form {$form->id}.");
        }

        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');
        $groupByExpression = DbHelper::jsonExtract('{{%formie_submissions}}.content', $groupField->uid);
        $normalizedGroupExpression = new Expression("COALESCE(NULLIF($groupByExpression, ''), '(Not Set)')");
        $query = (new Query())
            ->select(['id' => '{{%formie_submissions}}.id'])
            ->from('{{%formie_submissions}}')
            ->where([
                '{{%formie_submissions}}.formId' => $form->id,
                '{{%formie_submissions}}.isIncomplete' => false,
                '{{%formie_submissions}}.isSpam' => false,
            ])
            ->andWhere(['=', $normalizedGroupExpression, $groupValue]);

        if ($siteId !== 'all') {
            $query->innerJoin(
                '{{%elements_sites}} es_group_submission_site',
                "[[es_group_submission_site.elementId]] = [[{$submissionsTable}.id]] AND [[es_group_submission_site.siteId]] = :groupSubmissionSiteId",
                [':groupSubmissionSiteId' => (int)$siteId],
            );
        }

        $bounds = DateRangeHelper::getBounds($dateRange);
        if ($bounds['start']) {
            $query->andWhere(['>=', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['start'])]);
        }
        if ($bounds['end']) {
            $query->andWhere(['<', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['end'])]);
        }

        return $query;
    }

    /** @return array<string, int> */
    private function groupSubmissionOrder(): array
    {
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');

        return [
            "{$submissionsTable}.dateCreated" => SORT_DESC,
            "{$submissionsTable}.id" => SORT_DESC,
        ];
    }

    /**
     * @param list<int> $submissionIds
     * @return list<Submission>
     */
    private function hydrateGroupSubmissions(array $submissionIds, int|string $siteId): array
    {
        if ($submissionIds === []) {
            return [];
        }

        $submissionQuery = Submission::find()
            ->id($submissionIds)
            ->orderBy([
                'elements.dateCreated' => SORT_DESC,
                'elements.id' => SORT_DESC,
            ]);

        if ($siteId === 'all') {
            $submissionQuery->siteId('*')->unique();
        } else {
            $submissionQuery->siteId((int)$siteId);
        }

        $submissionsById = [];
        foreach ($submissionQuery->all() as $submission) {
            if (!$submission instanceof Submission) {
                continue;
            }
            $submissionsById[(int)$submission->id] ??= $submission;
        }

        $submissions = [];
        foreach ($submissionIds as $submissionId) {
            if (isset($submissionsById[$submissionId])) {
                $submissions[] = $submissionsById[$submissionId];
            }
        }

        return $submissions;
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
     * Normalise a date range to the canonical service option set.
     */
    private function normaliseDateRange(string $dateRange): string
    {
        if ($dateRange === 'alltime') {
            return 'all';
        }

        $validDateRanges = DateRangeHelper::getOptions('assoc', false);

        return array_key_exists($dateRange, $validDateRanges) ? $dateRange : 'all';
    }

    /**
     * Build the storage-independent cache identity.
     *
     * Passing a handle string preserves the public getCacheFilename() calling
     * contract. Internal cache paths pass the Rating instance so result-shaping
     * configuration participates in the identity.
     */
    private function buildCacheIdentity(
        int $formId,
        Rating|string $fieldHandle,
        string $dateRange,
        ?string $groupByHandle = null,
        int|string $siteId = 'all',
    ): string {
        $siteSegment = $this->normaliseSiteIdForKey($siteId);
        $dateRange = $this->normaliseDateRange($dateRange);
        $fieldSegment = $fieldHandle instanceof Rating
            ? $fieldHandle->handle . '-' . $this->getFieldConfigurationFingerprint($fieldHandle)
            : $fieldHandle;
        $identity = "{$formId}-{$fieldSegment}-{$dateRange}-{$siteSegment}";

        if ($groupByHandle) {
            $identity .= "-{$groupByHandle}";
        }

        return $identity;
    }

    /**
     * Fingerprint Rating configuration represented in cached statistics.
     */
    private function getFieldConfigurationFingerprint(Rating $field): string
    {
        return hash('sha256', Json::encode([
            'uid' => (string)$field->uid,
            'handle' => (string)$field->handle,
            'label' => (string)$field->label,
            'ratingType' => (string)$field->ratingType,
            'minValue' => (int)$field->minValue,
            'maxValue' => (int)$field->maxValue,
            'allowHalfRatings' => (bool)$field->allowHalfRatings,
        ]));
    }

    /**
     * Generate cache key for Redis/database storage
     *
     * @param int $formId
     * @param Rating|string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param int|string $siteId
     * @return string
     */
    private function getCacheKey(int $formId, Rating|string $fieldHandle, string $dateRange, ?string $groupByHandle = null, int|string $siteId = 'all'): string
    {
        return 'formie-rating-stats-' . $this->buildCacheIdentity($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);
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
     * @param Rating|string $fieldHandle Rating instance for fingerprinted identities; string handle for legacy callers
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param int|string $siteId
     * @return string
     */
    public function getCacheFilename(int $formId, Rating|string $fieldHandle, string $dateRange, ?string $groupByHandle = null, int|string $siteId = 'all'): string
    {
        $identity = $this->buildCacheIdentity($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);

        return $formId . '-' . md5($identity) . '.cache';
    }

    /**
     * Get statistics from cache
     *
     * @param int $formId
     * @param Rating|string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param int|string $siteId
     * @return array|null
     */
    private function getFromCache(int $formId, Rating|string $fieldHandle, string $dateRange, ?string $groupByHandle = null, int|string $siteId = 'all'): ?array
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            // Fail-closed on misconfig (setting=redis but cache component isn't Redis):
            // treat as a miss so the caller recomputes, matching the clear paths' no-op.
            $cache = PluginHelper::getRedisCacheOrLog('formie-rating-field');
            if ($cache === null) {
                return null;
            }

            $cacheKey = $this->getCacheKey($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);
            $cached = $cache->get($cacheKey);
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
     * @param Rating|string $fieldHandle
     * @param string $dateRange
     * @param string|null $groupByHandle
     * @param array $stats
     * @param int|string $siteId
     * @return bool
     */
    private function saveToCache(int $formId, Rating|string $fieldHandle, string $dateRange, ?string $groupByHandle, array $stats, int|string $siteId = 'all'): bool
    {
        $settings = \lindemannrock\formieratingfield\FormieRatingField::$plugin->getSettings();

        // Use Redis/database cache if configured
        if ($settings->cacheStorageMethod === 'redis') {
            // Fail-closed on misconfig (setting=redis but cache component isn't Redis):
            // skip the write so nothing lands in an unclearable store, matching the clear paths' no-op.
            $cache = PluginHelper::getRedisCacheOrLog('formie-rating-field');
            if ($cache === null) {
                return false;
            }

            $cacheKey = $this->getCacheKey($formId, $fieldHandle, $dateRange, $groupByHandle, $siteId);

            $result = $cache->set($cacheKey, $stats);

            if ($result) {
                // Track the key in our Redis index so we can scoped-flush later
                // (Yii's $cache->flush() would wipe every other plugin's keys too)
                $this->trackRedisCacheKey($cacheKey);
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

        if (!$result) {
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
            $cache = PluginHelper::getRedisCacheOrLog('formie-rating-field');
            if ($cache === null) {
                return true; // misconfig already logged
            }

            $tracked = $cache->redis->executeCommand('SMEMBERS', [$this->getRedisKeyIndex()]);
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
                $cache->redis->executeCommand('SREM', [$this->getRedisKeyIndex(), $key]);
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
            // Delete only the keys this plugin owns — never call $cache->flush(),
            // which would wipe every other plugin's cache keys too.
            $cache = PluginHelper::getRedisCacheOrLog('formie-rating-field');
            if ($cache !== null) {
                $tracked = $cache->redis->executeCommand('SMEMBERS', [$this->getRedisKeyIndex()]);
                if (is_array($tracked)) {
                    foreach ($tracked as $key) {
                        $cache->delete($key);
                    }
                }
                $cache->redis->executeCommand('DEL', [$this->getRedisKeyIndex()]);
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
        $cache = PluginHelper::getRedisCacheOrLog('formie-rating-field');
        if ($cache !== null) {
            $cache->redis->executeCommand('SADD', [$this->getRedisKeyIndex(), $cacheKey]);
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
                $cache = PluginHelper::getRedisCacheOrLog('formie-rating-field');
                if ($cache === null) {
                    return 0; // misconfig already logged
                }
                $count = $cache->redis->executeCommand('SCARD', [$this->getRedisKeyIndex()]);
                return (int)($count ?: 0);
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
     * Redis SET key holding every cache key this plugin owns.
     */
    private function getRedisKeyIndex(): string
    {
        return PluginHelper::getCacheKeySet(FormieRatingField::$plugin->id, 'stats');
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

        // Per-score distribution (0–10) — NPS scale is always integer 0–10.
        // Without this, getDistributionData() silently returns empty arrays for NPS fields.
        $distribution = [];
        for ($i = 0; $i <= 10; $i++) {
            $count = count(array_filter($values, fn($v) => floor($v) == $i));
            $distribution[] = [
                'value' => $i,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return [
            'npsScore' => $npsScore,
            'promoters' => $promoters,
            'promotersPercentage' => $total > 0 ? round(($promoters / $total) * 100, 1) : 0,
            'passives' => $passives,
            'passivesPercentage' => $total > 0 ? round(($passives / $total) * 100, 1) : 0,
            'detractors' => $detractors,
            'detractorsPercentage' => $total > 0 ? round(($detractors / $total) * 100, 1) : 0,
            'average' => $total > 0 ? round(array_sum($values) / $total, 2) : 0,
            'distribution' => $distribution,
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
        $dateRange = $this->normaliseDateRange($dateRange);

        // Try cache. The sentinel groupByHandle '__trend__' segregates trend data from
        // field-stats and from any real groupBy (Formie field handles must start with a letter).
        $cached = $this->getFromCache($form->id, $field, $dateRange, self::TREND_CACHE_VARIANT, $siteId);
        if ($cached !== null) {
            return $cached;
        }

        // SQL-aggregate per bucket — replaces the prior approach of materialising every
        // submission element into PHP and grouping in a foreach (3.2 + 3.4 cold-path cost).
        $isNps = $field->ratingType === Rating::RATING_TYPE_NPS;
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');
        $ratingExpr = DbHelper::jsonExtract('{{%formie_submissions}}.content', $field->uid);
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

        // Limit to max 50 data points for performance while retaining both endpoints.
        $chartData = $this->sampleTrendData($chartData);

        $result = [
            'labels' => array_column($chartData, 'date'),
            'values' => array_column($chartData, 'value'),
            'counts' => array_column($chartData, 'count'),
            'scaleMin' => $isNps ? -100 : 0,
            'scaleMax' => $isNps ? 100 : (int)$field->maxValue,
            'generatedAt' => DateFormatHelper::toApiString(new \DateTime('now', new \DateTimeZone('UTC'))),
        ];

        $this->saveToCache($form->id, $field, $dateRange, self::TREND_CACHE_VARIANT, $result, $siteId);

        return $result;
    }

    /**
     * Evenly sample trend buckets across the full chronological range.
     *
     * @param array<int, array{date: string, value: float, count: int}> $chartData
     * @return array<int, array{date: string, value: float, count: int}>
     */
    private function sampleTrendData(array $chartData): array
    {
        $pointCount = count($chartData);
        $maxPoints = 50;

        if ($pointCount <= $maxPoints) {
            return $chartData;
        }

        $lastIndex = $pointCount - 1;
        $sampledData = [];

        for ($sampleIndex = 0; $sampleIndex < $maxPoints; $sampleIndex++) {
            $sourceIndex = (int)round($sampleIndex * $lastIndex / ($maxPoints - 1));
            $sampledData[] = $chartData[$sourceIndex];
        }

        return $sampledData;
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
        // SQL count — avoids hydrating every submission element just to call count() on the result.
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');

        $query = (new Query())
            ->from('{{%formie_submissions}}')
            ->where([
                "{$submissionsTable}.formId" => $form->id,
                "{$submissionsTable}.isIncomplete" => false,
                "{$submissionsTable}.isSpam" => false,
            ]);

        $bounds = DateRangeHelper::getBounds($dateRange);
        if ($bounds['start']) {
            $query->andWhere(['>=', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['start'])]);
        }
        if ($bounds['end']) {
            $query->andWhere(['<', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['end'])]);
        }

        if ($siteId !== 'all') {
            $query->innerJoin(
                '{{%elements_sites}} es_site_filter',
                "[[es_site_filter.elementId]] = [[{$submissionsTable}.id]] AND [[es_site_filter.siteId]] = :filterSiteId",
                [':filterSiteId' => (int)$siteId]
            );
        }

        return (int)$query->count();
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

        // Apply the configured export row cap to prevent PHP OOM on busy forms.
        // 0 = unlimited. Default (50,000) covers typical use; admins who genuinely
        // need more can raise the setting or set 0 (and accept the OOM risk).
        $maxRows = (int)(FormieRatingField::$plugin->getSettings()->maxExportRows ?? 50000);
        $limit = $maxRows > 0 ? $maxRows : null;

        $submissions = $this->getSubmissions($form, $dateRange, $siteId, $limit);

        // Surface truncation to admins via the log so they know the export was capped.
        if ($limit !== null && count($submissions) >= $limit) {
            Craft::warning(
                "Raw-responses export for form '{$form->handle}' (id {$form->id}) was truncated " .
                "to {$limit} rows by the maxExportRows setting. Increase the setting or set to 0 " .
                'for unlimited (at the cost of higher PHP memory usage).',
                __METHOD__
            );
        }

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

        // Use the first rating field to establish the group list. Explicit
        // exports retain the complete set rather than the dashboard's bounded
        // overview payload.
        $firstField = $ratingFields[0];
        $groupedStats = $this->getGroupedStatistics($form, $firstField, $dateRange, $groupByHandle, $siteId);

        if (empty($groupedStats['groups'])) {
            return ['headers' => $headers, 'rows' => []];
        }

        // Pre-fetch each field's grouped stats once, indexed by group label for O(1)
        // lookup. Replaces the prior pattern of calling getFieldStatistics() and walking
        // its groups array once per (group, field) pair (same cache hit, repeated work).
        // Shape: [fieldHandle => [groupLabel => groupStats]]
        $statsByField = [];
        foreach ($ratingFields as $field) {
            $fieldStats = $field === $firstField
                ? $groupedStats
                : $this->getGroupedStatistics($form, $field, $dateRange, $groupByHandle, $siteId);
            $byLabel = [];
            foreach (($fieldStats['groups'] ?? []) as $g) {
                $byLabel[$g['label']] = $g;
            }
            $statsByField[$field->handle] = $byLabel;
        }

        $rows = [];

        foreach ($groupedStats['groups'] as $group) {
            $row = [$group['label'], $group['count']];

            foreach ($ratingFields as $field) {
                $groupStats = $statsByField[$field->handle][$group['label']] ?? null;

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
     * @param int|null $limit Optional row cap. Applied at the SQL layer via `->limit()`.
     *                       Used by export paths to prevent OOM on huge result sets.
     * @return array
     */
    private function getSubmissions(Form $form, string $dateRange = 'all', int|string $siteId = 'all', ?int $limit = null): array
    {
        // Exclude incomplete and spam submissions to match Formie's default UI semantics
        // (those submissions live in the spam folder and aren't counted in standard views).
        $query = Submission::find()
            ->formId($form->id)
            ->isIncomplete(false)
            ->isSpam(false)
            ->orderBy(['dateCreated' => SORT_DESC]);

        if ($siteId === 'all') {
            // Explicitly request all sites to override Craft's current-site default
            $query->siteId('*');
        } else {
            $query->siteId((int)$siteId);
        }

        $bounds = DateRangeHelper::getBounds($dateRange);

        if ($bounds['start']) {
            $query->andWhere(['>=', 'elements.dateCreated', Db::prepareDateForDb($bounds['start'])]);
        }
        if ($bounds['end']) {
            $query->andWhere(['<', 'elements.dateCreated', Db::prepareDateForDb($bounds['end'])]);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

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
     * Extract a flat list of rating values directly via SQL — replaces the prior
     * `getSubmissions() + extractFieldValues()` element-walk for the cache-miss path.
     *
     * Filter semantics match the original `getSubmissions()`:
     *  - formId match
     *  - DateRangeHelper bounds
     *  - 'all' = no site filter; specific = INNER JOIN elements_sites
     *  - rating value extracted from JSON content; NULL/empty rows skipped (matches the
     *    `$value !== null && $value !== ''` filter in the PHP version).
     *
     * @param Form $form
     * @param Rating $field
     * @param string $dateRange
     * @param int|string $siteId
     * @return float[]
     */
    private function extractFieldValuesViaSql(Form $form, Rating $field, string $dateRange = 'all', int|string $siteId = 'all'): array
    {
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');
        $valueExpr = DbHelper::jsonExtract('{{%formie_submissions}}.content', $field->uid);

        $query = (new Query())
            ->select(['valueRaw' => new Expression("CAST({$valueExpr} AS DECIMAL(10,2))")])
            ->from('{{%formie_submissions}}')
            ->where([
                "{$submissionsTable}.formId" => $form->id,
                "{$submissionsTable}.isIncomplete" => false,
                "{$submissionsTable}.isSpam" => false,
            ])
            ->andWhere(['not', [$valueExpr => null]])
            ->andWhere(['!=', $valueExpr, '']);

        $bounds = DateRangeHelper::getBounds($dateRange);
        if ($bounds['start']) {
            $query->andWhere(['>=', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['start'])]);
        }
        if ($bounds['end']) {
            $query->andWhere(['<', "{$submissionsTable}.dateCreated", Db::prepareDateForDb($bounds['end'])]);
        }

        if ($siteId !== 'all') {
            $query->innerJoin(
                '{{%elements_sites}} es_site_filter',
                "[[es_site_filter.elementId]] = [[{$submissionsTable}.id]] AND [[es_site_filter.siteId]] = :filterSiteId",
                [':filterSiteId' => (int)$siteId]
            );
        }

        return array_map('floatval', $query->column());
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
     * Calculate an exact median from SQL-aggregated value frequencies.
     *
     * @param array<string, int> $valueCounts Rating value => occurrence count
     * @return float
     */
    private function calculateMedianFromValueCounts(array $valueCounts): float
    {
        $total = array_sum($valueCounts);
        if ($total === 0) {
            return 0;
        }

        uksort($valueCounts, static fn(string|int $left, string|int $right): int => (float)$left <=> (float)$right);

        $lowerPosition = intdiv($total + 1, 2);
        $upperPosition = intdiv($total + 2, 2);
        $seen = 0;
        $lowerValue = null;

        foreach ($valueCounts as $value => $count) {
            $seen += $count;
            if ($lowerValue === null && $seen >= $lowerPosition) {
                $lowerValue = (float)$value;
            }
            if ($seen >= $upperPosition) {
                return ($lowerValue + (float)$value) / 2;
            }
        }

        return 0;
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
