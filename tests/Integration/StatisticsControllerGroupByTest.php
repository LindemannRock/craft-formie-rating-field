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
        self::assertStringContainsString('getGroupSubmissions($form, $groupBy, $groupValue,', $detailBody);
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
        self::assertStringContainsString("(groupValue|url_encode), detailParams", $detailTemplate);
        self::assertStringContainsString('detailParams|merge({fieldHandle: fieldHandle})', $detailTemplate);
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
