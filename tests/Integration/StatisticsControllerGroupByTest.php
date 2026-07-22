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
use craft\fields\PlainText;
use lindemannrock\formieratingfield\controllers\StatisticsController;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;

/**
 * Pins controller-side statistics parameter normalization and wiring.
 *
 * @since 3.22.0
 */
final class StatisticsControllerGroupByTest extends TestCase
{
    public function testNormalizeGroupByHandleKeepsValidGroupableField(): void
    {
        self::assertSame('branch', $this->normalizeGroupByHandle('branch'));
    }

    public function testNormalizeGroupByHandleDropsStaleOrInvalidGroupBy(): void
    {
        self::assertNull($this->normalizeGroupByHandle(null));
        self::assertNull($this->normalizeGroupByHandle(''));
        self::assertNull($this->normalizeGroupByHandle('removedField'));
        self::assertNull($this->normalizeGroupByHandle(['branch']));
    }

    public function testNormalizeGroupByHandleSupportsSharedControllerEntrypoints(): void
    {
        self::assertSame('region', $this->normalizeGroupByHandle('region'));
        self::assertNull($this->normalizeGroupByHandle('staleRegion'));
    }

    public function testActionExportNormalizesGroupByBeforeBuildingOptionalSection(): void
    {
        $body = $this->methodSource('actionExport');
        $try = strpos($body, 'try {');
        $catch = strpos($body, '} catch (\\Exception $e) {');
        $normalize = strpos($body, '$groupBy = $this->_normalizeGroupByHandle(');
        $summary = strpos($body, 'buildSummaryExportRows(');
        $raw = strpos($body, 'buildRawResponsesExportRows(');
        $grouped = strpos($body, '$groupBy ? $statisticsService->buildGroupedExportRows(');
        $summarySection = strpos($body, "'key' => 'summary'");
        $rawSection = strpos($body, "'key' => 'rawResponses'");
        $optionalSection = strpos($body, 'if ($byGroup)');

        self::assertIsInt($try);
        self::assertIsInt($catch);
        self::assertIsInt($normalize);
        self::assertIsInt($summary);
        self::assertIsInt($raw);
        self::assertIsInt($grouped);
        self::assertIsInt($summarySection);
        self::assertIsInt($rawSection);
        self::assertIsInt($optionalSection);
        self::assertLessThan($normalize, $try);
        self::assertLessThan($catch, $normalize);
        self::assertLessThan($summary, $normalize);
        self::assertLessThan($raw, $normalize);
        self::assertLessThan($grouped, $normalize);
        self::assertLessThan($optionalSection, $summary);
        self::assertLessThan($optionalSection, $raw);
        self::assertLessThan($optionalSection, $summarySection);
        self::assertLessThan($optionalSection, $rawSection);
        self::assertNull($this->normalizeGroupByHandle('removedField'));
    }

    public function testNormalizeRatingFieldHandleKeepsOnlyRealRatingField(): void
    {
        $ratingField = new Rating();
        $ratingField->handle = 'satisfaction';
        $unrelatedField = new PlainText();
        $unrelatedField->handle = 'comments';

        self::assertSame('satisfaction', $this->normalizeRatingFieldHandle('satisfaction', [$ratingField, $unrelatedField]));
        self::assertNull($this->normalizeRatingFieldHandle('comments', [$ratingField, $unrelatedField]));
        self::assertNull($this->normalizeRatingFieldHandle('removedRating', [$ratingField, $unrelatedField]));
        self::assertNull($this->normalizeRatingFieldHandle(null, [$ratingField]));
        self::assertNull($this->normalizeRatingFieldHandle(['satisfaction'], [$ratingField]));
    }

    public function testGroupDetailReadsAndPassesNormalizedFieldHandle(): void
    {
        $body = $this->methodSource('actionGroupDetail');

        self::assertStringContainsString("getQueryParam('fieldHandle')", $body);
        self::assertStringContainsString('$fieldHandle = $this->_normalizeRatingFieldHandle($fieldHandle, $ratingFields);', $body);
        self::assertStringContainsString("'fieldHandle' => \$fieldHandle", $body);
    }

    public function testGroupValuesAreNotDecodedTwice(): void
    {
        $detailBody = $this->methodSource('actionGroupDetail');
        $exportBody = $this->methodSource('actionExportGroup');

        self::assertSame('50%20OFF', $this->normalizeGroupValue('50%20OFF'));
        self::assertSame('', $this->normalizeGroupValue(['50%20OFF']));
        self::assertStringNotContainsString('urldecode', $detailBody);
        self::assertStringNotContainsString('urldecode', $exportBody);
        self::assertStringContainsString('getPaginatedGroupSubmissions(', $detailBody);
        self::assertStringContainsString('$groupValue = $this->_normalizeGroupValue($rawGroupValue);', $exportBody);
        self::assertStringContainsString('getGroupSubmissions($form, $groupBy, $groupValue,', $exportBody);
    }

    public function testStatisticsLinksUseStructuredQueryParameters(): void
    {
        $formTemplate = $this->templateSource('form.twig');
        $detailTemplate = $this->templateSource('group-detail.twig');

        self::assertStringNotContainsString("'?dateRange='", $formTemplate);
        self::assertStringNotContainsString("'?dateRange='", $detailTemplate);
        self::assertStringNotContainsString('craft.app.request', $detailTemplate);
        self::assertStringContainsString("(group.label|url_encode), {", $formTemplate);
        self::assertStringContainsString('dateRange: dateRange', $formTemplate);
        self::assertStringContainsString('groupBy: groupBy', $formTemplate);
        self::assertStringContainsString('fieldHandle: field.handle', $formTemplate);
        $groupLinkStart = strpos($formTemplate, "(group.label|url_encode), {");
        $groupLinkEnd = strpos($formTemplate, '}) }}" class="go">', $groupLinkStart ?: 0);
        self::assertIsInt($groupLinkStart);
        self::assertIsInt($groupLinkEnd);
        $groupLinkSource = substr($formTemplate, $groupLinkStart, $groupLinkEnd - $groupLinkStart);
        self::assertStringContainsString('siteId: siteId', $groupLinkSource);
        self::assertStringContainsString("(groupValue|url_encode), detailParams", $detailTemplate);
        self::assertStringContainsString('detailParams|merge({fieldHandle: fieldHandle})', $detailTemplate);
        self::assertStringContainsString("{% extends 'lindemannrock-base/_layouts/cp-table' %}", $detailTemplate);
        self::assertStringContainsString('preserveParams: detailParams', $detailTemplate);
        self::assertStringContainsString('siteId: siteId', $detailTemplate);
        self::assertStringContainsString('page: page', $detailTemplate);
        self::assertStringContainsString('limit: limit', $detailTemplate);
        self::assertStringContainsString('totalCount: totalSubmissions', $detailTemplate);
        self::assertStringContainsString("{% block actionButton %}", $detailTemplate);
        self::assertStringContainsString("action: 'formie-rating-field/statistics/export-group'", $detailTemplate);

        $pageTitleStart = strpos($detailTemplate, '{% block pageTitle %}');
        $pageTitleEnd = strpos($detailTemplate, '{% endblock %}', $pageTitleStart ?: 0);
        self::assertIsInt($pageTitleStart);
        self::assertIsInt($pageTitleEnd);
        $pageTitleSource = substr($detailTemplate, $pageTitleStart, $pageTitleEnd - $pageTitleStart);
        self::assertStringContainsString('id="page-heading"', $pageTitleSource);
        self::assertStringContainsString('{{ detailHeading }}', $pageTitleSource);
        self::assertStringContainsString('{{ detailSummary }}', $pageTitleSource);
        self::assertStringContainsString('class="light', $pageTitleSource);
        self::assertStringContainsString("detailHeading = 'Individual Submissions for {value}'|t", $detailTemplate);
        self::assertStringContainsString("detailSummary = 'Showing {count} submission(s) for this {groupBy}'|t", $detailTemplate);

        self::assertStringNotContainsString('{% block beforeTable %}', $detailTemplate);
        self::assertStringNotContainsString('{% block extraFooter %}', $detailTemplate);
        self::assertStringNotContainsString('plugin-credit', $detailTemplate);
        self::assertStringContainsString("{key: 'dateCreated', label: 'Date'|t('formie-rating-field'), nowrap: true}", $detailTemplate);
        self::assertStringContainsString('<td class="nowrap">{{ item.dateCreated|lrDatetime }}</td>', $detailTemplate);
        self::assertStringNotContainsString('white-space: nowrap', $detailTemplate);
    }

    public function testOriginatingGroupUrlRendersAllStructuredParameters(): void
    {
        $rendered = Craft::$app->getView()->renderString(
            "{{ url('formie-rating-field/statistics/form/' ~ formId ~ '/group/' ~ (groupValue|url_encode), {dateRange: dateRange, groupBy: groupBy, fieldHandle: fieldHandle, siteId: siteId}) }}",
            [
                'formId' => 42,
                'groupValue' => '50% OFF',
                'dateRange' => 'last7days',
                'groupBy' => 'branch',
                'fieldHandle' => 'satisfaction',
                'siteId' => 7,
            ],
        );
        $url = html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        self::assertStringContainsString('/group/50%25%20OFF', $url);
        self::assertSame('last7days', $query['dateRange'] ?? null);
        self::assertSame('branch', $query['groupBy'] ?? null);
        self::assertSame('satisfaction', $query['fieldHandle'] ?? null);
        self::assertSame('7', $query['siteId'] ?? null);
    }

    public function testGroupDetailNormalizesPageAndUsesConfiguredPageSize(): void
    {
        $body = $this->methodSource('actionGroupDetail');

        self::assertStringContainsString("max(1, (int)\$request->getQueryParam('page', 1))", $body);
        self::assertStringContainsString('FormieRatingField::$plugin->getSettings()->itemsPerPage', $body);
        self::assertStringContainsString('$offset = ($page - 1) * $limit;', $body);
        self::assertStringContainsString('getPaginatedGroupSubmissions(', $body);
        self::assertStringContainsString("'totalSubmissions' => \$pageResult['totalCount']", $body);
        self::assertStringNotContainsString("'totalSubmissions' => count(\$submissions)", $body);
    }

    public function testGroupExportKeepsCompatibilityServiceAndConfiguredCap(): void
    {
        $body = $this->methodSource('actionExportGroup');

        self::assertStringContainsString('$settings = FormieRatingField::$plugin->getSettings();', $body);
        self::assertStringContainsString('$maxRows = (int)$settings->maxExportRows;', $body);
        self::assertStringContainsString('$limit = $maxRows > 0 ? $maxRows : null;', $body);
        self::assertStringContainsString('getGroupSubmissions($form, $groupBy, $groupValue, $dateRange, $siteId, $limit)', $body);
        self::assertStringNotContainsString('getPaginatedGroupSubmissions(', $body);
    }

    public function testGroupExportUsesFormieRepresentationsAndThrowableBoundary(): void
    {
        $body = $this->methodSource('actionExportGroup');
        $permission = strpos($body, "requirePermission('formieRatingField:exportStatistics')");
        $access = strpos($body, '$this->requireFormieSubmissionAccess($form);');
        $try = strpos($body, 'try {');
        $catch = strpos($body, '} catch (\\Throwable $e) {');
        $dispatch = strpos($body, 'ExportHelper::dispatchTable(');

        self::assertIsInt($permission);
        self::assertIsInt($access);
        self::assertIsInt($try);
        self::assertIsInt($catch);
        self::assertIsInt($dispatch);
        self::assertLessThan($try, $permission);
        self::assertLessThan($try, $access);
        self::assertLessThan($dispatch, $try);
        self::assertLessThan($catch, $dispatch);
        self::assertStringContainsString('$submission->getValueForExport($field->handle)', $body);
        self::assertStringContainsString('$submission->getValueAsJson($field->handle)', $body);
        self::assertStringNotContainsString('$submission->getFieldValue($field->handle)', $body);
        self::assertStringContainsString("return \$this->redirect(\$request->getReferrer() ?? 'formie-rating-field/statistics');", $body);
    }

    private function normalizeGroupByHandle(mixed $groupBy): ?string
    {
        $controller = new StatisticsController('statistics', Craft::$app);
        $method = new ReflectionMethod($controller, '_normalizeGroupByHandle');
        $result = $method->invoke($controller, $groupBy, [
            [
                'handle' => 'branch',
                'label' => 'Branch',
                'type' => 'verbb\\formie\\fields\\PlainText',
            ],
            [
                'handle' => 'region',
                'label' => 'Region',
                'type' => 'verbb\\formie\\fields\\Dropdown',
            ],
        ]);

        self::assertTrue(is_string($result) || $result === null);

        return $result;
    }

    /**
     * @param array $ratingFields
     */
    private function normalizeRatingFieldHandle(mixed $fieldHandle, array $ratingFields): ?string
    {
        $controller = new StatisticsController('statistics', Craft::$app);
        $method = new ReflectionMethod($controller, '_normalizeRatingFieldHandle');
        $result = $method->invoke($controller, $fieldHandle, $ratingFields);

        self::assertTrue(is_string($result) || $result === null);

        return $result;
    }

    private function normalizeGroupValue(mixed $groupValue): string
    {
        $controller = new StatisticsController('statistics', Craft::$app);
        $method = new ReflectionMethod($controller, '_normalizeGroupValue');
        $result = $method->invoke($controller, $groupValue);
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

    private function templateSource(string $filename): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/src/templates/statistics/' . $filename);
        self::assertIsString($contents);

        return $contents;
    }
}
