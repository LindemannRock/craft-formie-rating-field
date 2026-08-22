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
use craft\db\Query;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\tests\TestCase;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\SingleLineText;
use verbb\formie\models\FieldLayout;

/**
 * Covers statistics and export behavior for editable-site ID lists.
 *
 * @since 3.22.0
 */
final class StatisticsServiceEditableSiteScopeTest extends TestCase
{
    /** @var list<int> */
    private array $ownedElementIds = [];

    /** @var list<int> */
    private array $ownedSubmissionIds = [];

    /** @var list<int> */
    private array $ownedFormIds = [];

    /** @var list<int> */
    private array $ownedElementSiteIds = [];

    /** @var list<int> */
    private array $ownedLayoutIds = [];

    /** @var list<string> */
    private array $ownedCacheFiles = [];

    /** @var array{elementId: int, elementSiteIds: list<int>, layoutId: int, cacheFile: string}|null */
    private ?array $matchingSentinel = null;

    protected function cleanupExternalState(): void
    {
        try {
            $this->cleanupOwnedResources();
            $this->cleanupMatchingSentinel();
        } finally {
            parent::cleanupExternalState();
        }
    }

    public function testEditableSiteListsConstrainEveryStatisticsAndExportSurfaceWithoutDuplicates(): void
    {
        $sentinel = $this->seedMatchingSentinel();
        $sentinelFingerprint = $this->fingerprintMatchingSentinel($sentinel);
        $siteIds = array_map(
            static fn($site): int => (int)$site->id,
            array_slice(Craft::$app->getSites()->getAllSites(), 0, 2),
        );
        self::assertCount(2, $siteIds);

        $ownedResources = $this->snapshotOwnedResources();

        try {
            $form = $this->seedForm();
            $firstOnly = $this->seedSubmission($form, 5, '0', [$siteIds[0]]);
            $secondOnly = $this->seedSubmission($form, 1, '0', [$siteIds[1]]);
            $bothSites = $this->seedSubmission($form, 3, '1', $siteIds);
            $ratingField = $this->statistics->getRatingFieldByHandle($form, 'satisfaction');
            self::assertInstanceOf(Rating::class, $ratingField);

            $firstScope = [$siteIds[0]];
            $allEditableScope = [$siteIds[1], $siteIds[0], $siteIds[1]];

            $forms = $this->statistics->getFormsWithRatingFields($firstScope);
            $formRow = array_values(array_filter(
                $forms,
                static fn(array $row): bool => (int)$row['form']->id === (int)$form->id,
            ));
            self::assertCount(1, $formRow);
            self::assertSame(2, $formRow[0]['totalSubmissions']);

            $fieldStats = $this->statistics->getFieldStatistics($form, $ratingField, 'all', null, $firstScope);
            $groupedStats = $this->statistics->getGroupedStatistics($form, $ratingField, 'all', 'branch', $firstScope);
            $trend = $this->statistics->getTrendData($form, $ratingField, 'all', $firstScope);
            $distribution = $this->statistics->getDistributionData($form, $ratingField, 'all', $firstScope);
            $page = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', '0', 'all', $firstScope, 10, 0);
            $groupSubmissions = $this->statistics->getGroupSubmissions($form, 'branch', '0', 'all', $firstScope);
            $summary = $this->statistics->buildSummaryExportRows($form, 'all', $firstScope);
            $raw = $this->statistics->buildRawResponsesExportRows($form, 'all', $firstScope);
            $groupedExport = $this->statistics->buildGroupedExportRows($form, 'all', 'branch', $firstScope);

            self::assertSame(2, $this->statistics->getTotalSubmissions($form, 'all', $firstScope));
            self::assertSame(2, $fieldStats['totalResponses']);
            self::assertSame(4.0, $fieldStats['average']);
            self::assertSame(2, array_sum($trend['counts']));
            self::assertSame(2, array_sum($distribution['values']));
            self::assertSame(['0', '1'], array_column($groupedStats['groups'], 'label'));
            self::assertSame([1, 1], array_column($groupedStats['groups'], 'count'));
            self::assertSame(1, $page['totalCount']);
            self::assertSame([(int)$firstOnly->id], $this->submissionIds($page['submissions']));
            self::assertSame([(int)$firstOnly->id], $this->submissionIds($groupSubmissions));
            self::assertSame(2, $summary['rows'][0][2]);
            self::assertEqualsCanonicalizing(
                [(int)$firstOnly->id, (int)$bothSites->id],
                array_map('intval', array_column($raw['rows'], 1)),
            );
            self::assertSame(['0', '1'], array_column($groupedExport['rows'], 0));
            self::assertSame([1, 1], array_column($groupedExport['rows'], 1));

            self::assertSame(3, $this->statistics->getTotalSubmissions($form, 'all', $allEditableScope));
            self::assertSame(3, $this->statistics->getFieldStatistics($form, $ratingField, 'all', null, $allEditableScope)['totalResponses']);
            self::assertSame(2, $this->statistics->getPaginatedGroupSubmissions($form, 'branch', '0', 'all', $allEditableScope, 10, 0)['totalCount']);
            self::assertSame(3, $this->statistics->getTotalSubmissions($form, 'all', 'all'));

            self::assertSame([], $this->statistics->getFormsWithRatingFields([]));
            self::assertSame(0, $this->statistics->getTotalSubmissions($form, 'all', []));
            self::assertSame(0, $this->statistics->getFieldStatistics($form, $ratingField, 'all', null, [])['totalResponses']);
            self::assertSame([], $this->statistics->getTrendData($form, $ratingField, 'all', [])['counts']);
            self::assertSame([], $this->statistics->buildRawResponsesExportRows($form, 'all', [])['rows']);
            self::assertSame([], $this->statistics->buildGroupedExportRows($form, 'all', 'branch', [])['rows']);
            self::assertSame([], $this->statistics->getGroupSubmissions($form, 'branch', '0', 'all', []));

            self::assertNotSame((int)$secondOnly->id, (int)$firstOnly->id);
        } finally {
            $ownedResources = $this->snapshotOwnedResources();
            $this->cleanupOwnedResources();
        }

        self::assertSame($sentinelFingerprint, $this->fingerprintMatchingSentinel($sentinel));
        $this->assertOwnedResourcesRemoved($ownedResources);
    }

    public function testFixtureFailureCleanupPreservesPreExistingMatchingState(): void
    {
        $sentinel = $this->seedMatchingSentinel();
        $sentinelFingerprint = $this->fingerprintMatchingSentinel($sentinel);
        $siteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $ownedResources = $this->snapshotOwnedResources();

        try {
            try {
                $form = $this->seedForm();
                $this->seedSubmission($form, 4, 'failure', [$siteId]);
                $ratingField = $this->statistics->getRatingFieldByHandle($form, 'satisfaction');
                self::assertInstanceOf(Rating::class, $ratingField);
                $this->statistics->getFieldStatistics($form, $ratingField, 'all', null, [$siteId]);

                throw new \RuntimeException('Deliberate fixture failure.');
            } finally {
                $ownedResources = $this->snapshotOwnedResources();
                $this->cleanupOwnedResources();
            }
        } catch (\RuntimeException $exception) {
            self::assertSame('Deliberate fixture failure.', $exception->getMessage());
        }

        self::assertSame($sentinelFingerprint, $this->fingerprintMatchingSentinel($sentinel));
        $this->assertOwnedResourcesRemoved($ownedResources);
    }

    private function seedForm(): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Editable site statistics ', 'form');
        $form->handle = $this->nextTestMarker('editableSiteStatistics', 'form');
        $layout = new FieldLayout();
        $layout->setPages([[
            'label' => 'Page 1',
            'rows' => [[
                'fields' => [
                    [
                        'type' => Rating::class,
                        'handle' => 'satisfaction',
                        'label' => 'Satisfaction',
                        'ratingType' => Rating::RATING_TYPE_STAR,
                        'minValue' => 0,
                        'maxValue' => 5,
                    ],
                    [
                        'type' => SingleLineText::class,
                        'handle' => 'branch',
                        'label' => 'Branch',
                    ],
                ],
            ]],
        ]]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);
        $this->trackOwnedForm($form);

        return $form;
    }

    /** @param list<int> $siteIds */
    private function seedSubmission(Form $form, int $rating, string $branch, array $siteIds): Submission
    {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->title = $this->nextTestMarker('Editable site submission ', 'submission');
        $submission->setFieldValue('satisfaction', $rating);
        $submission->setFieldValue('branch', $branch);
        $this->saveTestElement($submission, false, false, false);
        $this->trackOwnedSubmission($submission);
        $this->replaceElementSites((int)$submission->id, $siteIds);
        $this->trackOwnedElementSites((int)$submission->id);

        return $submission;
    }

    /** @param list<int> $siteIds */
    private function replaceElementSites(int $elementId, array $siteIds): void
    {
        $template = (new Query())
            ->from('{{%elements_sites}}')
            ->where(['elementId' => $elementId])
            ->one();
        self::assertIsArray($template);

        Craft::$app->getDb()->createCommand()
            ->delete('{{%elements_sites}}', ['elementId' => $elementId])
            ->execute();

        foreach ($siteIds as $siteId) {
            $row = $template;
            unset($row['id']);
            $row['siteId'] = $siteId;
            $row['uid'] = StringHelper::UUID();
            Craft::$app->getDb()->createCommand()->insert('{{%elements_sites}}', $row)->execute();
        }
    }

    private function trackOwnedForm(Form $form): void
    {
        self::assertNotNull($form->id);
        $layoutId = $form->getFormLayout()->id;
        self::assertNotNull($layoutId);

        $this->ownedElementIds[] = (int)$form->id;
        $this->ownedFormIds[] = (int)$form->id;
        $this->ownedLayoutIds[] = (int)$layoutId;
        $this->trackOwnedElementSites((int)$form->id);
    }

    private function trackOwnedSubmission(Submission $submission): void
    {
        self::assertNotNull($submission->id);

        $this->ownedElementIds[] = (int)$submission->id;
        $this->ownedSubmissionIds[] = (int)$submission->id;
        $this->trackOwnedElementSites((int)$submission->id);
    }

    private function trackOwnedElementSites(int $elementId): void
    {
        $ids = (new Query())
            ->select('id')
            ->from('{{%elements_sites}}')
            ->where(['elementId' => $elementId])
            ->column();

        foreach ($ids as $id) {
            $this->ownedElementSiteIds[] = (int)$id;
        }
    }

    /**
     * @return array{
     *     elementIds: list<int>,
     *     submissionIds: list<int>,
     *     formIds: list<int>,
     *     elementSiteIds: list<int>,
     *     layoutIds: list<int>,
     *     pageIds: list<int>,
     *     rowIds: list<int>,
     *     fieldIds: list<int>,
     *     cacheFiles: list<string>
     * }
     */
    private function snapshotOwnedResources(): array
    {
        $this->captureOwnedCacheFiles();

        return [
            'elementIds' => $this->ownedElementIds,
            'submissionIds' => $this->ownedSubmissionIds,
            'formIds' => $this->ownedFormIds,
            'elementSiteIds' => $this->ownedElementSiteIds,
            'layoutIds' => $this->ownedLayoutIds,
            'pageIds' => $this->idsForLayouts('{{%formie_fieldlayout_pages}}'),
            'rowIds' => $this->idsForLayouts('{{%formie_fieldlayout_rows}}'),
            'fieldIds' => $this->idsForLayouts('{{%formie_fields}}'),
            'cacheFiles' => $this->ownedCacheFiles,
        ];
    }

    /** @return list<int> */
    private function idsForLayouts(string $table): array
    {
        if ($this->ownedLayoutIds === []) {
            return [];
        }

        return array_map('intval', (new Query())
            ->select('id')
            ->from($table)
            ->where(['layoutId' => $this->ownedLayoutIds])
            ->orderBy('id')
            ->column());
    }

    private function captureOwnedCacheFiles(): void
    {
        foreach ($this->ownedFormIds as $formId) {
            $files = glob($this->statisticsCachePath() . $formId . '-*.cache') ?: [];
            foreach ($files as $file) {
                if (!in_array($file, $this->ownedCacheFiles, true)) {
                    $this->ownedCacheFiles[] = $file;
                }
            }
        }
    }

    private function cleanupOwnedResources(): void
    {
        $this->captureOwnedCacheFiles();

        foreach ($this->ownedFormIds as $formId) {
            $this->statistics->clearCacheForForm($formId);
        }
        foreach ($this->ownedCacheFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (array_reverse($this->ownedElementIds) as $elementId) {
            Craft::$app->getDb()->createCommand()
                ->delete('{{%elements}}', ['id' => $elementId])
                ->execute();
        }
        foreach ($this->ownedLayoutIds as $layoutId) {
            Craft::$app->getDb()->createCommand()
                ->delete('{{%formie_fieldlayouts}}', ['id' => $layoutId])
                ->execute();
        }

        $this->ownedElementIds = [];
        $this->ownedSubmissionIds = [];
        $this->ownedFormIds = [];
        $this->ownedElementSiteIds = [];
        $this->ownedLayoutIds = [];
        $this->ownedCacheFiles = [];
    }

    /** @return array{elementId: int, elementSiteIds: list<int>, layoutId: int, cacheFile: string} */
    private function seedMatchingSentinel(): array
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Editable site submission sentinel ', 'form');
        $form->handle = $this->nextTestMarker('editableSiteSentinel', 'form');
        $layout = new FieldLayout();
        $layout->setPages([[
            'label' => 'Page 1',
            'rows' => [[
                'fields' => [[
                    'type' => Rating::class,
                    'handle' => 'sentinelRating',
                    'label' => 'Sentinel Rating',
                    'ratingType' => Rating::RATING_TYPE_STAR,
                    'minValue' => 1,
                    'maxValue' => 5,
                ]],
            ]],
        ]]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);
        self::assertNotNull($form->id);
        $layoutId = $form->getFormLayout()->id;
        self::assertNotNull($layoutId);

        $elementSiteIds = array_map('intval', (new Query())
            ->select('id')
            ->from('{{%elements_sites}}')
            ->where(['elementId' => $form->id])
            ->orderBy('id')
            ->column());
        FileHelper::createDirectory($this->statisticsCachePath());
        $cacheFile = $this->statisticsCachePath() . $form->id . '-editable-site-sentinel.cache';
        $written = file_put_contents($cacheFile, 'pre-existing matching cache sentinel');
        self::assertIsInt($written);

        $this->matchingSentinel = [
            'elementId' => (int)$form->id,
            'elementSiteIds' => $elementSiteIds,
            'layoutId' => (int)$layoutId,
            'cacheFile' => $cacheFile,
        ];

        return $this->matchingSentinel;
    }

    /**
     * @param array{elementId: int, elementSiteIds: list<int>, layoutId: int, cacheFile: string} $sentinel
     */
    private function fingerprintMatchingSentinel(array $sentinel): string
    {
        $state = [
            'element' => (new Query())->from('{{%elements}}')->where(['id' => $sentinel['elementId']])->one(),
            'elementSites' => (new Query())->from('{{%elements_sites}}')->where(['id' => $sentinel['elementSiteIds']])->orderBy('id')->all(),
            'form' => (new Query())->from('{{%formie_forms}}')->where(['id' => $sentinel['elementId']])->one(),
            'layout' => (new Query())->from('{{%formie_fieldlayouts}}')->where(['id' => $sentinel['layoutId']])->one(),
            'pages' => (new Query())->from('{{%formie_fieldlayout_pages}}')->where(['layoutId' => $sentinel['layoutId']])->orderBy('id')->all(),
            'rows' => (new Query())->from('{{%formie_fieldlayout_rows}}')->where(['layoutId' => $sentinel['layoutId']])->orderBy('id')->all(),
            'fields' => (new Query())->from('{{%formie_fields}}')->where(['layoutId' => $sentinel['layoutId']])->orderBy('id')->all(),
            'cache' => is_file($sentinel['cacheFile']) ? hash_file('sha256', $sentinel['cacheFile']) : false,
        ];

        return hash('sha256', serialize($state));
    }

    /**
     * @param array{
     *     elementIds: list<int>,
     *     submissionIds: list<int>,
     *     formIds: list<int>,
     *     elementSiteIds: list<int>,
     *     layoutIds: list<int>,
     *     pageIds: list<int>,
     *     rowIds: list<int>,
     *     fieldIds: list<int>,
     *     cacheFiles: list<string>
     * } $resources
     */
    private function assertOwnedResourcesRemoved(array $resources): void
    {
        self::assertSame(0, $this->countRowsByIds('{{%elements}}', $resources['elementIds']));
        self::assertSame(0, $this->countRowsByIds('{{%formie_submissions}}', $resources['submissionIds']));
        self::assertSame(0, $this->countRowsByIds('{{%formie_forms}}', $resources['formIds']));
        self::assertSame(0, $this->countRowsByIds('{{%elements_sites}}', $resources['elementSiteIds']));
        self::assertSame(0, $this->countRowsByIds('{{%formie_fieldlayouts}}', $resources['layoutIds']));
        self::assertSame(0, $this->countRowsByIds('{{%formie_fieldlayout_pages}}', $resources['pageIds']));
        self::assertSame(0, $this->countRowsByIds('{{%formie_fieldlayout_rows}}', $resources['rowIds']));
        self::assertSame(0, $this->countRowsByIds('{{%formie_fields}}', $resources['fieldIds']));
        self::assertSame([], array_values(array_filter($resources['cacheFiles'], 'is_file')));
    }

    /** @param list<int> $ids */
    private function countRowsByIds(string $table, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int)(new Query())->from($table)->where(['id' => $ids])->count();
    }

    private function cleanupMatchingSentinel(): void
    {
        if ($this->matchingSentinel === null) {
            return;
        }

        if (is_file($this->matchingSentinel['cacheFile'])) {
            unlink($this->matchingSentinel['cacheFile']);
        }
        Craft::$app->getDb()->createCommand()
            ->delete('{{%elements}}', ['id' => $this->matchingSentinel['elementId']])
            ->execute();
        Craft::$app->getDb()->createCommand()
            ->delete('{{%formie_fieldlayouts}}', ['id' => $this->matchingSentinel['layoutId']])
            ->execute();

        $this->matchingSentinel = null;
    }

    /** @param list<Submission> $submissions */
    private function submissionIds(array $submissions): array
    {
        return array_map(static fn(Submission $submission): int => (int)$submission->id, $submissions);
    }
}
