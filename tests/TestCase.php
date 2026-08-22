<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\formieratingfield\FormieRatingField;
use lindemannrock\formieratingfield\services\StatisticsService;
use RuntimeException;
use verbb\formie\base\NestedField;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;

/**
 * Base test case for formie-rating-field integration tests.
 *
 * Layers plugin-specific shorthand on top of the shared {@see IntegrationTestCase}:
 *  - direct accessor for the statistics service
 *  - sentinel test formId (`TEST_FORM_ID`) used as the filename prefix when
 *    writing test cache files, so {@see cleanupExternalState()} can purge them
 *    via a single glob without touching real-form cache entries
 *  - exact ownership and ordered cleanup for persisted Formie form fixtures
 *  - {@see statisticsCachePath()} shorthand
 *
 * @since 3.19.0
 */
abstract class TestCase extends IntegrationTestCase
{
    /**
     * Sentinel formId used as the prefix for any cache files this suite writes.
     *
     * `StatisticsService::getCacheFilename()` prefixes every file with the
     * literal `{$formId}-`, so using a value no real Formie form will ever
     * have lets `cleanupExternalState()` purge test-written files via a single
     * `{TEST_FORM_ID}-*.cache` glob without scanning real cache contents.
     */
    protected const TEST_FORM_ID = 9999999;

    protected StatisticsService $statistics;

    /**
     * Exact identities owned by each persisted Formie form fixture.
     *
     * @var array<int, array{
     *     formId: int,
     *     elementIds: list<int>,
     *     elementSiteIds: list<int>,
     *     submissionIds: list<int>,
     *     layoutId: int,
     *     layoutIds: list<int>,
     *     pageIds: list<int>,
     *     rowIds: list<int>,
     *     fieldIds: list<int>,
     *     cacheFiles: list<string>
     * }>
     */
    private array $formieFormFixtures = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->statistics = FormieRatingField::$plugin->statistics;
    }

    /**
     * Wipe any cache files this suite wrote. Runs from
     * {@see IntegrationTestCase::tearDown()} BEFORE component restoration.
     */
    protected function cleanupExternalState(): void
    {
        $files = glob($this->statisticsCachePath() . self::TEST_FORM_ID . '-*.cache') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }

        $this->cleanupTrackedFormieFormFixtures();
    }

    /**
     * Save a real Formie form and immediately own its exact persisted graph.
     */
    protected function saveTestForm(
        Form $form,
        bool $runValidation = false,
        bool $propagate = true,
        bool $updateSearchIndex = true,
    ): Form {
        $this->saveTestElement($form, $runValidation, $propagate, $updateSearchIndex);

        return $form;
    }

    protected function saveTestElement(
        ElementInterface $element,
        bool $runValidation = false,
        bool $propagate = true,
        bool $updateSearchIndex = true,
    ): ElementInterface {
        $saved = parent::saveTestElement($element, $runValidation, $propagate, $updateSearchIndex);

        if ($element instanceof Form) {
            $this->trackSavedForm($element);
        } elseif ($element instanceof Submission) {
            $this->trackSavedSubmission($element);
        }

        return $saved;
    }

    /**
     * Register an exact cache path created for a tracked form fixture.
     */
    protected function trackFormieFixtureCacheFile(Form|int $form, string $path): void
    {
        $formId = $form instanceof Form ? (int)$form->id : $form;
        if (!isset($this->formieFormFixtures[$formId])) {
            throw new RuntimeException("Formie form fixture {$formId} is not tracked.");
        }

        if (!in_array($path, $this->formieFormFixtures[$formId]['cacheFiles'], true)) {
            $this->formieFormFixtures[$formId]['cacheFiles'][] = $path;
        }
    }

    /**
     * Return the current exact identity ledger for one tracked form.
     *
     * @return array{
     *     formId: int,
     *     elementIds: list<int>,
     *     elementSiteIds: list<int>,
     *     submissionIds: list<int>,
     *     layoutId: int,
     *     layoutIds: list<int>,
     *     pageIds: list<int>,
     *     rowIds: list<int>,
     *     fieldIds: list<int>,
     *     cacheFiles: list<string>
     * }
     */
    protected function formieFormFixtureIdentity(Form|int $form): array
    {
        $formId = $form instanceof Form ? (int)$form->id : $form;
        if (!isset($this->formieFormFixtures[$formId])) {
            throw new RuntimeException("Formie form fixture {$formId} is not tracked.");
        }

        return $this->formieFormFixtures[$formId];
    }

    /**
     * Delete one exact form fixture. Safe to call repeatedly.
     */
    protected function cleanupFormieFormFixture(Form|int $form): void
    {
        $formId = $form instanceof Form ? (int)$form->id : $form;
        $fixture = $this->formieFormFixtures[$formId] ?? null;
        if ($fixture === null) {
            return;
        }

        foreach ($fixture['cacheFiles'] as $cacheFile) {
            if (is_file($cacheFile) && !unlink($cacheFile)) {
                throw new RuntimeException("Unable to delete tracked Formie cache file: {$cacheFile}");
            }
        }

        foreach (array_reverse($fixture['elementIds']) as $elementId) {
            $element = Craft::$app->getElements()->getElementById($elementId, null, null, ['status' => null]);
            if ($element !== null && !Craft::$app->getElements()->deleteElement($element, true)) {
                throw new RuntimeException("Unable to delete tracked Formie element {$elementId}.");
            }
        }

        if ($this->countRowsByIds('{{%elements}}', $fixture['elementIds']) !== 0
            || $this->countRowsByIds('{{%elements_sites}}', $fixture['elementSiteIds']) !== 0
            || $this->countRowsByIds('{{%formie_forms}}', [$fixture['formId']]) !== 0
            || $this->countRowsByIds('{{%formie_submissions}}', $fixture['submissionIds']) !== 0
        ) {
            throw new RuntimeException("Tracked Formie form {$formId} still has element residue.");
        }

        foreach (array_reverse($fixture['layoutIds']) as $layoutId) {
            Craft::$app->getDb()->createCommand()
                ->delete('{{%formie_fieldlayouts}}', ['id' => $layoutId])
                ->execute();
        }

        if ($this->countRowsByIds('{{%formie_fieldlayouts}}', $fixture['layoutIds']) !== 0
            || $this->countRowsByIds('{{%formie_fieldlayout_pages}}', $fixture['pageIds']) !== 0
            || $this->countRowsByIds('{{%formie_fieldlayout_rows}}', $fixture['rowIds']) !== 0
            || $this->countRowsByIds('{{%formie_fields}}', $fixture['fieldIds']) !== 0
            || array_filter($fixture['cacheFiles'], 'is_file') !== []
        ) {
            throw new RuntimeException("Tracked Formie form {$formId} still has layout or cache residue.");
        }

        unset($this->formieFormFixtures[$formId]);
    }

    private function trackSavedForm(Form $form): void
    {
        if ($form->id === null) {
            throw new RuntimeException('Saved Formie form has no element ID.');
        }

        $formId = (int)$form->id;
        $layoutId = (int)(new Query())
            ->select('layoutId')
            ->from('{{%formie_forms}}')
            ->where(['id' => $formId])
            ->scalar();
        if ($layoutId <= 0) {
            throw new RuntimeException("Saved Formie form {$formId} has no persisted layout ID.");
        }

        $layoutIds = [$layoutId];
        foreach ($form->getFields() as $field) {
            $this->collectNestedLayoutIds($field, $layoutIds);
        }

        $this->formieFormFixtures[$formId] = [
            'formId' => $formId,
            'elementIds' => [$formId],
            'elementSiteIds' => $this->ids('{{%elements_sites}}', ['elementId' => $formId]),
            'submissionIds' => [],
            'layoutId' => $layoutId,
            'layoutIds' => $layoutIds,
            'pageIds' => $this->ids('{{%formie_fieldlayout_pages}}', ['layoutId' => $layoutIds]),
            'rowIds' => $this->ids('{{%formie_fieldlayout_rows}}', ['layoutId' => $layoutIds]),
            'fieldIds' => $this->ids('{{%formie_fields}}', ['layoutId' => $layoutIds]),
            'cacheFiles' => [],
        ];
    }

    /** @param list<int> $layoutIds */
    private function collectNestedLayoutIds(mixed $field, array &$layoutIds): void
    {
        if (!$field instanceof NestedField || $field->nestedLayoutId === null) {
            return;
        }

        $nestedLayoutId = (int)$field->nestedLayoutId;
        if ($nestedLayoutId > 0 && !in_array($nestedLayoutId, $layoutIds, true)) {
            $layoutIds[] = $nestedLayoutId;
        }

        foreach ($field->getFields() as $nestedField) {
            $this->collectNestedLayoutIds($nestedField, $layoutIds);
        }
    }

    private function trackSavedSubmission(Submission $submission): void
    {
        if ($submission->id === null || $submission->formId === null) {
            return;
        }

        $formId = (int)$submission->formId;
        if (!isset($this->formieFormFixtures[$formId])) {
            return;
        }

        $submissionId = (int)$submission->id;
        $this->formieFormFixtures[$formId]['elementIds'][] = $submissionId;
        $this->formieFormFixtures[$formId]['submissionIds'][] = $submissionId;
        foreach ($this->ids('{{%elements_sites}}', ['elementId' => $submissionId]) as $elementSiteId) {
            $this->formieFormFixtures[$formId]['elementSiteIds'][] = $elementSiteId;
        }
    }

    private function cleanupTrackedFormieFormFixtures(): void
    {
        foreach (array_reverse(array_keys($this->formieFormFixtures)) as $formId) {
            $this->cleanupFormieFormFixture($formId);
        }
    }

    /** @return list<int> */
    private function ids(string $table, array $where): array
    {
        return array_map('intval', (new Query())
            ->select('id')
            ->from($table)
            ->where($where)
            ->orderBy('id')
            ->column());
    }

    /** @param list<int> $ids */
    private function countRowsByIds(string $table, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int)(new Query())->from($table)->where(['id' => $ids])->count();
    }

    /**
     * Absolute filesystem path to the plugin's statistics cache directory,
     * matching `StatisticsService::getCachePath()`.
     */
    protected function statisticsCachePath(): string
    {
        return PluginHelper::getCachePath(FormieRatingField::$plugin, 'statistics');
    }

    /**
     * Run file-cache assertions independently of the owner's saved storage token.
     *
     * @param callable(StatisticsService): void $assertions
     */
    protected function withForcedDurableFileCache(string $savedStorageMethod, callable $assertions): void
    {
        $settings = FormieRatingField::$plugin->getSettings();
        $ownerStorageMethod = $settings->cacheStorageMethod;

        try {
            $settings->cacheStorageMethod = $savedStorageMethod;
            $originalSavedStorageMethod = $settings->cacheStorageMethod;

            try {
                $settings->cacheStorageMethod = 'file';
                $statistics = new class() extends StatisticsService {
                    protected function isEphemeralHost(): bool
                    {
                        return false;
                    }
                };
                $decision = $statistics->getCacheStorageDecision();

                self::assertSame('file', $decision->configuredStorageToken);
                self::assertFalse($decision->ephemeralHost);
                self::assertTrue($decision->usesFileCache());

                $assertions($statistics);
            } finally {
                $settings->cacheStorageMethod = $originalSavedStorageMethod;
            }

            self::assertSame($savedStorageMethod, $settings->cacheStorageMethod);
        } finally {
            $settings->cacheStorageMethod = $ownerStorageMethod;
        }

        self::assertSame($ownerStorageMethod, $settings->cacheStorageMethod);
    }
}
