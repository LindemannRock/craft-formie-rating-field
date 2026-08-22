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
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\DbHelper;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\SingleLineText;
use verbb\formie\models\FieldLayout;
use yii\db\Query;

/**
 * Pins grouped-statistics payload building after the GROUP_CONCAT removal.
 *
 * @since 3.22.0
 */
final class StatisticsServiceGroupedStatsTest extends TestCase
{
    public function testGroupedStarAverageUsesSqlAggregateNotFetchedMedianValues(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
        ]);

        $groups = $this->buildGroupedStatsFromAggregateRows(
            [
                [
                    'groupValue' => 'Branch A',
                    'count' => 300,
                    'average' => '4.75',
                ],
            ],
            $field,
            [
                // Deliberately does not average to 4.75. This guards the regression
                // where grouped averages were derived from transported raw values.
                'Branch A' => [1 => 200],
            ],
        );

        self::assertSame('Branch A', $groups[0]['label']);
        self::assertSame(300, $groups[0]['count']);
        self::assertSame(4.75, $groups[0]['average']);
        self::assertSame(1.0, $groups[0]['median']);
    }

    public function testGroupedNpsStatsPreserveBreakdownAndDistributionShape(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_NPS,
        ]);

        $row = [
            'groupValue' => 'Branch B',
            'count' => 10,
            'average' => '7.40',
            'promoters' => 3,
            'passives' => 4,
            'detractors' => 3,
        ];

        for ($i = 0; $i <= 10; $i++) {
            $row["score{$i}"] = $i === 10 ? 3 : 0;
        }

        $groups = $this->buildGroupedStatsFromAggregateRows([$row], $field);

        self::assertSame('Branch B', $groups[0]['label']);
        self::assertSame(10, $groups[0]['count']);
        self::assertSame(0.0, $groups[0]['npsScore']);
        self::assertSame(3, $groups[0]['promoters']);
        self::assertSame(30.0, $groups[0]['promotersPercentage']);
        self::assertSame(4, $groups[0]['passives']);
        self::assertSame(40.0, $groups[0]['passivesPercentage']);
        self::assertSame(3, $groups[0]['detractors']);
        self::assertSame(30.0, $groups[0]['detractorsPercentage']);
        self::assertSame(7.4, $groups[0]['average']);
        self::assertCount(11, $groups[0]['distribution']);
        self::assertSame(['value' => 10, 'count' => 3, 'percentage' => 30.0], $groups[0]['distribution'][10]);
    }

    public function testMedianFromValueCountsIsExactForOddEvenAndHalfRatings(): void
    {
        self::assertSame(2.0, $this->calculateMedianFromValueCounts([1 => 1, 2 => 1, 5 => 1]));
        self::assertSame(2.5, $this->calculateMedianFromValueCounts([1 => 1, 2 => 1, 3 => 1, 5 => 1]));
        self::assertSame(3.5, $this->calculateMedianFromValueCounts(['2.5' => 2, '3.5' => 3, '4.5' => 1]));
        self::assertSame(0.0, $this->calculateMedianFromValueCounts([]));
    }

    public function testGroupedDashboardPayloadIsBoundedAndRetainsCompleteCountAndCacheTime(): void
    {
        $form = $this->seedGroupedRatingForm();

        try {
            for ($index = 0; $index < 100; $index++) {
                $this->seedGroupedSubmission($form, sprintf('Branch %03d', $index), ($index % 5) + 1);
            }

            $this->seedGroupedSubmission($form, null, 4);
            $this->seedGroupedSubmission($form, '', 5);

            $incomplete = $this->seedGroupedSubmission($form, 'Incomplete Branch', 1);
            $spam = $this->seedGroupedSubmission($form, 'Spam Branch', 1);
            $old = $this->seedGroupedSubmission($form, 'Old Branch', 1);
            $this->seedGroupedSubmission($form, 'Unrated Branch', null);
            $this->updateSubmissionFlags($incomplete, true, false);
            $this->updateSubmissionFlags($spam, false, true);
            $this->updateSubmissionDate($old, '2000-01-01 00:00:00');

            $ratingField = $this->statistics->getRatingFieldByHandle($form, 'satisfaction');
            self::assertInstanceOf(Rating::class, $ratingField);
            $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;

            $first = $this->statistics->getFieldStatistics($form, $ratingField, 'last7days', 'branch', $siteId);
            $cached = $this->statistics->getFieldStatistics($form, $ratingField, 'last7days', 'branch', $siteId);
            $explicitPage = $this->statistics->getGroupedStatistics($form, $ratingField, 'last7days', 'branch', $siteId, 3);
            $unbounded = $this->statistics->getGroupedStatistics($form, $ratingField, 'last7days', 'branch', $siteId);
            $missingSite = $this->statistics->getGroupedStatistics($form, $ratingField, 'last7days', 'branch', 999999999, 3);
            $groupedExport = $this->statistics->buildGroupedExportRows($form, 'last7days', 'branch', $siteId);

            self::assertCount(100, $first['groups']);
            self::assertSame(101, $first['totalGroups']);
            self::assertTrue($first['isLimited']);
            self::assertSame('(Not Set)', $first['groups'][0]['label']);
            self::assertSame(2, $first['groups'][0]['count']);
            self::assertArrayHasKey('generatedAt', $first);
            self::assertSame($first['generatedAt'], $cached['generatedAt']);
            self::assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, $first['generatedAt']));

            self::assertCount(3, $explicitPage['groups']);
            self::assertSame(101, $explicitPage['totalGroups']);
            self::assertTrue($explicitPage['isLimited']);
            self::assertSame(['(Not Set)', 'Branch 000', 'Branch 001'], array_column($explicitPage['groups'], 'label'));
            self::assertCount(101, $unbounded['groups']);
            self::assertSame(101, $unbounded['totalGroups']);
            self::assertFalse($unbounded['isLimited']);
            self::assertSame([], $missingSite['groups']);
            self::assertSame(0, $missingSite['totalGroups']);
            self::assertFalse($missingSite['isLimited']);
            self::assertCount(101, $groupedExport['rows']);
        } finally {
            $this->statistics->clearCacheForForm((int)$form->id);
        }
    }

    public function testGroupedTotalUsesMinimalDistinctCountQuery(): void
    {
        $submissionsTable = Craft::$app->getDb()->getSchema()->getRawTableName('{{%formie_submissions}}');
        $groupExpr = DbHelper::jsonExtract('{{%formie_submissions}}.content', 'group-field-uid');
        $ratingExpr = DbHelper::jsonExtract('{{%formie_submissions}}.content', 'rating-field-uid');
        $normalizedGroupExpr = "COALESCE(NULLIF($groupExpr, ''), '(Not Set)')";
        $method = new ReflectionMethod(StatisticsService::class, 'buildGroupedStatisticsTotalQuery');
        $query = $method->invoke(
            $this->statistics,
            self::TEST_FORM_ID,
            $normalizedGroupExpr,
            $ratingExpr,
            $submissionsTable,
            DateRangeHelper::getBounds('all'),
            'all',
        );

        self::assertInstanceOf(Query::class, $query);
        self::assertIsArray($query->select);
        self::assertSame(['totalGroups'], array_keys($query->select));
        self::assertNull($query->groupBy);
        self::assertNull($query->orderBy);

        $sql = strtoupper($query->createCommand()->getRawSql());
        self::assertStringContainsString('COUNT(DISTINCT COALESCE(NULLIF(', $sql);
        self::assertStringContainsString('(NOT SET)', $sql);
        self::assertStringNotContainsString('GROUP BY', $sql);
        self::assertStringNotContainsString('AVG(', $sql);
        self::assertStringNotContainsString('SUM(CASE', $sql);
    }

    public function testGroupedExportUsesEveryRatingFieldAndFieldIndependentSubmissionCounts(): void
    {
        $form = $this->seedMultiRatingGroupedForm();

        try {
            $oldRated = $this->seedMultiRatingSubmission($form, 'First Only', 4, null);
            $oldUnrated = $this->seedMultiRatingSubmission($form, 'First Only', null, null);
            $this->updateSubmissionDate($oldRated, '2000-01-01 00:00:00');
            $this->updateSubmissionDate($oldUnrated, '2000-01-01 00:00:00');

            $this->seedMultiRatingSubmission($form, 'Second Only', null, 10);
            $this->seedMultiRatingSubmission($form, 'Second Only', null, null);
            $this->seedMultiRatingSubmission($form, 'Second Only', null, null);
            $this->seedMultiRatingSubmission($form, null, null, 9);
            $this->seedMultiRatingSubmission($form, '', null, 7);
            $this->seedMultiRatingSubmission($form, null, null, null);
            $this->seedMultiRatingSubmission($form, 'Tie B', null, 8);
            $this->seedMultiRatingSubmission($form, 'Tie A', null, 8);
            $this->seedMultiRatingSubmission($form, 'No Ratings', null, null);

            $spam = $this->seedMultiRatingSubmission($form, 'Spam', null, 10);
            $incomplete = $this->seedMultiRatingSubmission($form, 'Incomplete', null, 10);
            $this->updateSubmissionFlags($spam, false, true);
            $this->updateSubmissionFlags($incomplete, true, false);

            $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
            $recent = $this->statistics->buildGroupedExportRows($form, 'last7days', 'branch', $siteId);
            $allTime = $this->statistics->buildGroupedExportRows($form, 'all', 'branch', $siteId);
            $missingSite = $this->statistics->buildGroupedExportRows($form, 'last7days', 'branch', 999999999);

            self::assertSame(
                ['(Not Set)', 'Second Only', 'Tie A', 'Tie B'],
                array_column($recent['rows'], 0),
            );
            self::assertSame([3, 3, 1, 1], array_column($recent['rows'], 1));
            self::assertSame(
                [
                    ['(Not Set)', 3, '', '', 50.0, '1 (50%)', '1 (50%)', '0 (0%)'],
                    ['Second Only', 3, '', '', 100.0, '1 (100%)', '0 (0%)', '0 (0%)'],
                    ['Tie A', 1, '', '', 0.0, '0 (0%)', '1 (100%)', '0 (0%)'],
                    ['Tie B', 1, '', '', 0.0, '0 (0%)', '1 (100%)', '0 (0%)'],
                ],
                $recent['rows'],
            );

            self::assertSame(
                ['(Not Set)', 'Second Only', 'First Only', 'Tie A', 'Tie B'],
                array_column($allTime['rows'], 0),
            );
            self::assertSame([3, 3, 2, 1, 1], array_column($allTime['rows'], 1));
            self::assertSame(['First Only', 2, 4.0, 4.0, '', '', '', ''], $allTime['rows'][2]);
            self::assertNotContains('No Ratings', array_column($allTime['rows'], 0));
            self::assertNotContains('Spam', array_column($allTime['rows'], 0));
            self::assertNotContains('Incomplete', array_column($allTime['rows'], 0));
            self::assertSame([], $missingSite['rows']);
        } finally {
            $this->statistics->clearCacheForForm((int)$form->id);
        }
    }

    public function testGroupedExportUsesOneAggregateQueryForSubmissionCounts(): void
    {
        $exportSource = $this->methodSource('buildGroupedExportRows');
        $countQuerySource = $this->methodSource('buildGroupedSubmissionCountsQuery');
        $metricFilterSource = $this->methodSource('applyGroupedStatisticsFilters');
        $submissionFilterSource = $this->methodSource('applyGroupedSubmissionFilters');

        self::assertSame(1, substr_count($exportSource, 'buildGroupedSubmissionCountsQuery('));
        self::assertStringContainsString(')->all();', $exportSource);
        self::assertStringContainsString("COALESCE(NULLIF(\$groupByExpr, ''), '(Not Set)')", $exportSource);
        self::assertStringContainsString('->groupBy(\'groupValue\')', $countQuerySource);
        self::assertStringContainsString("'submissionCount' => new Expression('COUNT(*)')", $countQuerySource);
        self::assertStringContainsString("->orderBy(['submissionCount' => SORT_DESC, 'groupValue' => SORT_ASC])", $countQuerySource);
        self::assertStringContainsString('applyGroupedSubmissionFilters(', $countQuerySource);
        self::assertStringContainsString('applyGroupedSubmissionFilters(', $metricFilterSource);
        self::assertStringNotContainsString('$ratingExpr', $countQuerySource);
        self::assertStringContainsString("'{{%formie_submissions}}.isIncomplete' => false", $submissionFilterSource);
        self::assertStringContainsString("'{{%formie_submissions}}.isSpam' => false", $submissionFilterSource);
        self::assertStringContainsString("applySiteScope(\$query, \$submissionsTable, \$siteId, 'es_grouped_scope')", $submissionFilterSource);
        self::assertStringContainsString('$dateBounds[\'start\']', $submissionFilterSource);
        self::assertStringContainsString('$dateBounds[\'end\']', $submissionFilterSource);
    }

    /**
     * @param array $rows
     * @param Rating $field
     * @param array<string, array<int|string, int>> $medianValueCountsByGroup
     * @return array
     */
    private function buildGroupedStatsFromAggregateRows(array $rows, Rating $field, array $medianValueCountsByGroup = []): array
    {
        $method = new ReflectionMethod(StatisticsService::class, 'buildGroupedStatsFromAggregateRows');
        $result = $method->invoke($this->statistics, $rows, $field, $medianValueCountsByGroup);

        self::assertIsArray($result);

        return $result;
    }

    /** @param array<int|string, int> $valueCounts */
    private function calculateMedianFromValueCounts(array $valueCounts): float
    {
        $method = new ReflectionMethod(StatisticsService::class, 'calculateMedianFromValueCounts');
        $result = $method->invoke($this->statistics, $valueCounts);

        self::assertIsFloat($result);

        return $result;
    }

    private function seedGroupedRatingForm(): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Rating grouped stats test ', 'form');
        $form->handle = $this->nextTestMarker('ratingGroupedStatsTest', 'form');

        $layout = new FieldLayout();
        $layout->setPages([
            [
                'label' => 'Page 1',
                'rows' => [
                    [
                        'fields' => [
                            [
                                'type' => Rating::class,
                                'handle' => 'satisfaction',
                                'label' => 'Satisfaction',
                                'ratingType' => Rating::RATING_TYPE_STAR,
                                'minValue' => 1,
                                'maxValue' => 5,
                            ],
                            [
                                'type' => SingleLineText::class,
                                'handle' => 'branch',
                                'label' => 'Branch',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);

        return $form;
    }

    private function seedMultiRatingGroupedForm(): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Rating grouped export test ', 'form');
        $form->handle = $this->nextTestMarker('ratingGroupedExportTest', 'form');

        $layout = new FieldLayout();
        $layout->setPages([
            [
                'label' => 'Page 1',
                'rows' => [
                    [
                        'fields' => [
                            [
                                'type' => Rating::class,
                                'handle' => 'primaryRating',
                                'label' => 'Primary Rating',
                                'ratingType' => Rating::RATING_TYPE_STAR,
                                'minValue' => 1,
                                'maxValue' => 5,
                            ],
                            [
                                'type' => Rating::class,
                                'handle' => 'secondaryRating',
                                'label' => 'Secondary Rating',
                                'ratingType' => Rating::RATING_TYPE_NPS,
                                'minValue' => 0,
                                'maxValue' => 10,
                            ],
                            [
                                'type' => SingleLineText::class,
                                'handle' => 'branch',
                                'label' => 'Branch',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);

        return $form;
    }

    private function seedGroupedSubmission(Form $form, ?string $branch, ?int $rating): Submission
    {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->title = $this->nextTestMarker('ratingGroupedStatsTest', 'submission');
        $submission->setFieldValue('satisfaction', $rating);
        $submission->setFieldValue('branch', $branch);
        $this->saveTestElement($submission, false, false, false);

        return $submission;
    }

    private function seedMultiRatingSubmission(Form $form, ?string $branch, ?int $primaryRating, ?int $secondaryRating): Submission
    {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->title = $this->nextTestMarker('ratingGroupedExportTest', 'submission');
        $submission->setFieldValue('primaryRating', $primaryRating);
        $submission->setFieldValue('secondaryRating', $secondaryRating);
        $submission->setFieldValue('branch', $branch);
        $this->saveTestElement($submission, false, false, false);

        return $submission;
    }

    private function updateSubmissionFlags(Submission $submission, bool $isIncomplete, bool $isSpam): void
    {
        Craft::$app->getDb()->createCommand()->update(
            '{{%formie_submissions}}',
            ['isIncomplete' => $isIncomplete, 'isSpam' => $isSpam],
            ['id' => $submission->id],
        )->execute();
    }

    private function updateSubmissionDate(Submission $submission, string $dateCreated): void
    {
        Craft::$app->getDb()->createCommand()->update(
            '{{%formie_submissions}}',
            ['dateCreated' => $dateCreated],
            ['id' => $submission->id],
        )->execute();
    }

    private function methodSource(string $method): string
    {
        $reflection = new ReflectionMethod(StatisticsService::class, $method);
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
