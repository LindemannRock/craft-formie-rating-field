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
use craft\web\View;
use lindemannrock\formieratingfield\controllers\SettingsController;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Covers the template, localization, and routing audit regressions.
 *
 * @since 3.22.0
 */
final class TemplateLocalizationRegressionTest extends TestCase
{
    public function testRatingUsesSchemaAndDefaultsWithoutDeadSettingsHtmlOverride(): void
    {
        $field = new Rating();

        $settingsHtml = (new ReflectionClass($field))->getMethod('getSettingsHtml');

        self::assertNotSame(Rating::class, $settingsHtml->getDeclaringClass()->getName());
        self::assertNotEmpty($field->defineSettingsSchema());
        self::assertArrayHasKey('ratingType', $field->getFieldDefaults());
        self::assertArrayHasKey('singleEmojiSelection', $field->getFieldDefaults());
    }

    public function testCustomEmojiLabelsDoNotDependOnSingleSelectionMode(): void
    {
        $schemaByName = [];
        foreach ((new Rating())->defineSettingsSchema() as $setting) {
            if (isset($setting['name'])) {
                $schemaByName[$setting['name']] = $setting;
            }
        }

        self::assertSame('$get(ratingType).value == emoji', $schemaByName['singleEmojiSelection']['if']);
        self::assertSame('$get(ratingType).value == emoji', $schemaByName['customLabels']['if']);
    }

    public function testLocaleAwareNumberRenderingPreservesCountAndNpsPrecision(): void
    {
        $formatter = Craft::$app->getFormatter();
        $originalLanguage = Craft::$app->language;
        $originalFormatterLocale = $formatter->locale;

        try {
            $formatter->locale = 'en';
            $rendered = $this->renderStringInLanguage(
                "{{ count|number }}|{{ whole|number(decimals=0) }}|{{ fractional|number(decimals=1) }}",
                'de',
                [
                    'count' => 12345,
                    'whole' => 42,
                    'fractional' => 12.5,
                ],
            );

            self::assertSame('12.345|42|12,5', $rendered);
            self::assertSame($originalLanguage, Craft::$app->language);
            self::assertSame('en', $formatter->locale);
        } finally {
            Craft::$app->language = $originalLanguage;
            $formatter->locale = $originalFormatterLocale;
        }
    }

    public function testGroupPaginationLabelsRenderInANonEnglishLocale(): void
    {
        $rendered = $this->renderStringInLanguage(
            "{{ 'Submission'|t('formie-rating-field') }}|{{ 'Submissions'|t('formie-rating-field') }}",
            'de',
            [],
        );

        self::assertSame('Einsendung|Einsendungen', $rendered);
    }

    public function testGroupedSummarySelectsHighestReliableShownGroupByRatingScore(): void
    {
        $rendered = $this->renderGroupedSummary([
            ['label' => 'Volume Leader', 'count' => 200, 'average' => 3.1],
            ['label' => 'Score Leader', 'count' => 50, 'average' => 4.9],
            ['label' => 'Small Perfect', 'count' => 4, 'average' => 5.0],
            ['label' => '(Not Set)', 'count' => 100, 'average' => 5.0],
        ]);
        $text = $this->normalizedText($rendered);

        self::assertMatchesRegularExpression('/Top Performer.*Score Leader.*Avg: 4\.9.*50 reviews/s', $text);
        self::assertStringNotContainsString('Small Perfect', $text);
        self::assertStringNotContainsString('(Not Set)', $text);
    }

    public function testGroupedSummaryUsesNpsScoreAndOmitsCardsWithoutReliableGroups(): void
    {
        $rendered = $this->renderGroupedSummary([
            ['label' => 'Volume Leader', 'count' => 200, 'npsScore' => 10],
            ['label' => 'Score Leader', 'count' => 8, 'npsScore' => 70],
            ['label' => 'Small Leader', 'count' => 4, 'npsScore' => 90],
        ], true);
        $text = $this->normalizedText($rendered);

        self::assertMatchesRegularExpression('/Top Performer.*Score Leader.*NPS: 70.*8 reviews/s', $text);
        self::assertStringNotContainsString('Small Leader', $text);

        $unreliable = $this->renderGroupedSummary([
            ['label' => 'Small Group', 'count' => 4, 'npsScore' => 90],
            ['label' => '(Not Set)', 'count' => 100, 'npsScore' => 100],
        ], true);

        self::assertStringNotContainsString('Top Performer', $this->normalizedText($unreliable));
        self::assertStringNotContainsString('Needs Attention', $this->normalizedText($unreliable));
    }

    public function testWidgetUsesSingularAndPluralRatingFieldKeys(): void
    {
        $rendered = $this->renderWidgetRowsInLanguage('de', [
            ['form' => ['id' => 1, 'title' => 'One'], 'ratingFieldCount' => 1, 'totalSubmissions' => 10],
            ['form' => ['id' => 2, 'title' => 'Two'], 'ratingFieldCount' => 2, 'totalSubmissions' => 20],
        ]);

        self::assertSame('1 Bewertungsfeld|2 Bewertungsfelder|', ltrim($rendered));
    }

    public function testPlaceholderTranslationsCanReorderAndEscapeDynamicValues(): void
    {
        $rendered = $this->renderStringInLanguage(
            "{{ '{title} - Rating Statistics'|t('formie-rating-field', {title: title})|e }}\n" .
            "{{ 'Group by: {field}'|t('formie-rating-field', {field: field})|e }}\n" .
            "{{ 'Individual Submissions for {value}'|t('formie-rating-field', {value: value})|e }}\n" .
            "{{ '{count} Rating Fields'|t('formie-rating-field', {count: count|number})|e }}",
            'ja',
            [
                'title' => 'Feedback & Follow-up',
                'field' => 'Branch & Region',
                'value' => 'Dubai & North',
                'count' => 12,
            ],
        );

        self::assertSame(
            "Feedback &amp; Follow-up - 評価統計\n" .
            "Branch &amp; Region でグループ化\n" .
            "Dubai &amp; North の個別送信\n" .
            '12 件の評価フィールド',
            $rendered,
        );
    }

    public function testStarEmailUsesPluginTranslationWithCombinedValue(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
        ]);

        self::assertSame('4 / 5 Sterne', $this->emailText($field, 4, 'de'));
    }

    public function testEmojiEmailPreservesAdministratorAuthoredLabel(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_EMOJI,
            'minValue' => 1,
            'maxValue' => 5,
            'customLabels' => [
                ['value' => 3, 'label' => 'Great & bright'],
            ],
        ]);

        $html = $this->renderEmail($field, 3, 'fr');

        self::assertStringContainsString('Great &amp; bright (3)', $html);
        self::assertSame('Great & bright (3)', $this->normalizedText($html));
    }

    public function testNpsEmailPreservesScoreAndMaximum(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_NPS,
        ]);

        self::assertSame('7 / 10', $this->emailText($field, 7, 'ja'));
    }

    public function testUnratedEmailUsesPluginOwnedTranslation(): void
    {
        $field = new Rating();
        $html = $this->renderEmail($field, null, 'de');

        self::assertStringContainsString('<em>Nicht bewertet</em>', $html);
        self::assertSame('Nicht bewertet', $this->normalizedText($html));
    }

    public function testTemplateSourcesUseCompleteKeysAndLocaleAwareNumbers(): void
    {
        $statistics = $this->templateSource('statistics/form.twig');
        $index = $this->templateSource('statistics/index.twig');
        $detail = $this->templateSource('statistics/group-detail.twig');
        $widget = $this->templateSource('widgets/rating-statistics/body.twig');
        $email = $this->templateSource('fields/rating/email.twig');

        self::assertStringNotContainsString('number_format', $statistics . $index);
        self::assertStringContainsString('number(decimals=0)', $statistics);
        self::assertStringContainsString('number(decimals=1)', $statistics);
        self::assertStringContainsString("'{title} - Rating Statistics'|t", $statistics);
        self::assertStringContainsString("'Group by: {field}'|t", $statistics);
        self::assertStringContainsString("'{value} - Individual Submissions'|t", $detail);
        self::assertStringContainsString("'Individual Submissions for {value}'|t", $detail);
        self::assertStringContainsString("'{count} Rating Field'|t", $widget);
        self::assertStringContainsString("'{count} Rating Fields'|t", $widget);
        self::assertStringNotContainsString("t('formie')", $email);
        self::assertStringContainsString("'{value} stars'|t('formie-rating-field'", $email);
        self::assertStringContainsString("'Not rated'|t('formie-rating-field')", $email);
    }

    public function testTranslationFilesDropFragmentsAndKeepPlaceholderIntegrity(): void
    {
        $required = [
            '{title} - Rating Statistics' => ['title'],
            '{value} - Individual Submissions' => ['value'],
            'Group by: {field}' => ['field'],
            '{count} Rating Field' => ['count'],
            '{count} Rating Fields' => ['count'],
            'Individual Submissions for {value}' => ['value'],
            '{value} stars' => ['value'],
        ];
        $obsolete = [
            'Group by: ',
            ' - Individual Submissions',
            'Individual Submissions for',
        ];

        foreach ($this->translationFiles() as $language => $filename) {
            $translations = require $filename;

            foreach ($obsolete as $key) {
                self::assertArrayNotHasKey($key, $translations, "Obsolete fragment remains in {$language}.");
            }

            foreach ($required as $key => $placeholders) {
                self::assertArrayHasKey($key, $translations, "Missing {$key} in {$language}.");

                foreach ($placeholders as $placeholder) {
                    self::assertSame(
                        1,
                        substr_count($translations[$key], '{' . $placeholder . '}'),
                        "Placeholder {{$placeholder}} is invalid for {$key} in {$language}.",
                    );
                }
            }

            self::assertArrayHasKey('Not rated', $translations, "Missing Not rated in {$language}.");
            self::assertArrayHasKey('Submissions', $translations, "Missing Submissions in {$language}.");
        }
    }

    public function testStatisticsTemplateDropsComparisonChartAndKeepsSupportedCharts(): void
    {
        $statistics = $this->templateSource('statistics/form.twig');

        self::assertStringNotContainsString('comparison' . 'Data', $statistics);
        self::assertStringNotContainsString('comparison' . '-chart', $statistics);
        self::assertStringContainsString('nps-chart-', $statistics);
        self::assertStringContainsString('distribution-chart-', $statistics);
        self::assertStringContainsString('trend-chart-', $statistics);
    }

    public function testControllerRoutesMakeDeletedRootTemplatesUnnecessary(): void
    {
        $templates = dirname(__DIR__, 2) . '/src/templates';
        $pluginInit = $this->methodSource(FormieRatingField::class, 'init');
        $settingsResponse = $this->methodSource(FormieRatingField::class, 'getSettingsResponse');
        $settingsIndex = $this->methodSource(SettingsController::class, 'actionIndex');

        self::assertFileDoesNotExist($templates . '/index.twig');
        self::assertFileDoesNotExist($templates . '/settings.twig');
        self::assertFileExists($templates . '/statistics/index.twig');
        self::assertDirectoryExists($templates . '/settings');
        self::assertFileExists($templates . '/_layouts/settings.twig');

        self::assertStringContainsString("['formie-rating-field'] = 'formie-rating-field/statistics/index'", $pluginInit);
        self::assertStringContainsString("['formie-rating-field/settings'] = 'formie-rating-field/settings/index'", $pluginInit);
        self::assertStringContainsString("redirect('formie-rating-field/settings')", $settingsResponse);
        self::assertStringContainsString("redirect('formie-rating-field/settings/general')", $settingsIndex);
    }

    private function emailText(Rating $field, mixed $value, string $language): string
    {
        return $this->normalizedText($this->renderEmail($field, $value, $language));
    }

    private function renderEmail(Rating $field, mixed $value, string $language): string
    {
        return $this->renderTemplateInLanguage(
            'formie-rating-field/fields/rating/email',
            $language,
            [
                'field' => $field,
                'value' => $value,
            ],
        );
    }

    /** @param list<array<string, int|float|string>> $groups */
    private function renderGroupedSummary(array $groups, bool $isNps = false): string
    {
        $source = $this->templateSource('statistics/form.twig');
        self::assertSame(
            1,
            preg_match(
                '/\{# Calculate summary metrics #\}(.*?)\{# Detailed Table with Performance Indicators #\}/s',
                $source,
                $matches,
            ),
        );

        return $this->renderStringInLanguage(
            $matches[1],
            'en',
            [
                'isNps' => $isNps,
                'stats' => [
                    'groupByLabel' => 'Branch',
                    'groups' => $groups,
                    'totalGroups' => count($groups),
                ],
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $forms
     */
    private function renderWidgetRowsInLanguage(string $language, array $forms): string
    {
        $source = $this->templateSource('widgets/rating-statistics/body.twig');
        self::assertSame(
            1,
            preg_match('/(\{% set rows = \[\] %\}.*?)(?=\{% include)/s', $source, $matches),
        );

        return $this->renderStringInLanguage(
            $matches[1] . '{% for row in rows %}{{ row.meta }}|{% endfor %}',
            $language,
            ['forms' => $forms, 'siteId' => 'all'],
        );
    }

    private function renderStringInLanguage(string $template, string $language, array $variables): string
    {
        return $this->withLanguage($language, fn(): string => Craft::$app->getView()->renderString($template, $variables));
    }

    private function renderTemplateInLanguage(string $template, string $language, array $variables): string
    {
        return $this->withLanguage($language, function() use ($template, $variables): string {
            $view = Craft::$app->getView();
            $originalTemplateMode = $view->getTemplateMode();

            try {
                $view->setTemplateMode(View::TEMPLATE_MODE_CP);
                return $view->renderTemplate($template, $variables);
            } finally {
                $view->setTemplateMode($originalTemplateMode);
            }
        });
    }

    private function withLanguage(string $language, callable $callback): string
    {
        $formatter = Craft::$app->getFormatter();
        $originalLanguage = Craft::$app->language;
        $originalFormatterLocale = $formatter->locale;

        try {
            Craft::$app->language = $language;
            $formatter->locale = $language;
            return $callback();
        } finally {
            Craft::$app->language = $originalLanguage;
            $formatter->locale = $originalFormatterLocale;
        }
    }

    private function normalizedText(string $html): string
    {
        return preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5))) ?? '';
    }

    private function templateSource(string $filename): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/' . $filename);
        self::assertIsString($source);

        return $source;
    }

    /**
     * @return array<string, string>
     */
    private function translationFiles(): array
    {
        $files = [];
        foreach (glob(dirname(__DIR__, 2) . '/src/translations/*/formie-rating-field.php') ?: [] as $filename) {
            $files[basename(dirname($filename))] = $filename;
        }

        self::assertCount(12, $files);

        return $files;
    }

    /**
     * @param class-string $class
     */
    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
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
