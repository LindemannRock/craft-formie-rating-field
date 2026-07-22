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
use DateTimeImmutable;
use DateTimeZone;
use lindemannrock\formieratingfield\services\StatisticsService;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Categories;
use verbb\formie\fields\Dropdown;
use verbb\formie\fields\Entries;
use verbb\formie\fields\Hidden;
use verbb\formie\fields\Radio;
use verbb\formie\fields\SingleLineText;
use verbb\formie\models\FieldLayout;

/**
 * Covers bounded database pagination for grouped submission detail pages.
 *
 * @since 3.23.0
 */
final class StatisticsServiceGroupPaginationTest extends TestCase
{
    public function testFilteringPrecedesStableBoundedPagination(): void
    {
        $form = $this->seedForm(SingleLineText::class);
        $matching = [];

        for ($index = 0; $index < 5; $index++) {
            $matching[] = $this->seedSubmission($form, 'Dubai', '2026-01-10 12:00:00');
        }

        for ($index = 0; $index < 3; $index++) {
            $this->seedSubmission($form, 'Abu Dhabi', '2026-01-11 12:00:00');
        }

        $incomplete = $this->seedSubmission($form, 'Dubai', '2026-01-12 12:00:00');
        $spam = $this->seedSubmission($form, 'Dubai', '2026-01-12 12:00:00');
        $this->updateSubmissionFlags($incomplete, true, false);
        $this->updateSubmissionFlags($spam, false, true);

        $expectedIds = array_reverse(array_map(static fn(Submission $submission): int => (int)$submission->id, $matching));
        $pageOne = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', 'Dubai', 'all', 'all', 2, 0);
        $pageTwo = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', 'Dubai', 'all', 'all', 2, 2);
        $beyond = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', 'Dubai', 'all', 'all', 2, 100);

        self::assertSame(5, $pageOne['totalCount']);
        self::assertSame(5, $pageTwo['totalCount']);
        self::assertCount(2, $pageOne['submissions']);
        self::assertCount(2, $pageTwo['submissions']);
        self::assertSame(array_slice($expectedIds, 0, 2), $this->submissionIds($pageOne));
        self::assertSame(array_slice($expectedIds, 2, 2), $this->submissionIds($pageTwo));
        self::assertSame([], array_intersect($this->submissionIds($pageOne), $this->submissionIds($pageTwo)));
        self::assertSame(5, $beyond['totalCount']);
        self::assertSame([], $beyond['submissions']);
    }

    public function testNotSetDateAndSiteFiltersApplyToCountAndPageData(): void
    {
        $form = $this->seedForm(SingleLineText::class);
        $primarySiteId = (int)Craft::$app->getSites()->getPrimarySite()->id;
        $recent = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d H:i:s');
        $old = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-40 days')->format('Y-m-d H:i:s');

        $recentSubmission = $this->seedSubmission($form, 'Dubai', $recent);
        $this->seedSubmission($form, 'Dubai', $old);
        $this->seedSubmission($form, null, $recent);
        $this->seedSubmission($form, '(Not Set)', $recent);

        $recentPage = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', 'Dubai', 'last7days', $primarySiteId, 10, 0);
        $missingSitePage = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', 'Dubai', 'last7days', 999999999, 10, 0);
        $notSetPage = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', '(Not Set)', 'all', 'all', 10, 0);

        self::assertSame(1, $recentPage['totalCount']);
        self::assertSame([(int)$recentSubmission->id], $this->submissionIds($recentPage));
        self::assertSame(0, $missingSitePage['totalCount']);
        self::assertSame([], $missingSitePage['submissions']);
        self::assertSame(2, $notSetPage['totalCount']);
        self::assertCount(2, $notSetPage['submissions']);
    }

    public function testRelationalGroupsMatchRawJsonStorageUsedByGroupLinks(): void
    {
        $form = $this->seedForm(Entries::class);
        $submission = $this->seedSubmission($form, null, '2026-01-10 12:00:00');
        $groupField = $form->getFields()[0];
        $rawRelationValue = '[123, 456]';

        Craft::$app->getDb()->createCommand()->update(
            '{{%formie_submissions}}',
            ['content' => [(string)$groupField->uid => [123, 456]]],
            ['id' => $submission->id],
        )->execute();

        $page = $this->statistics->getPaginatedGroupSubmissions($form, 'branch', $rawRelationValue, 'all', 'all', 10, 0);

        self::assertSame(1, $page['totalCount']);
        self::assertSame([(int)$submission->id], $this->submissionIds($page));
    }

    public function testCompatibilityExportQueryFiltersRelationalGroupBeforeApplyingLimit(): void
    {
        $form = $this->seedForm(Entries::class);
        $matching = $this->seedSubmission($form, null, '2026-01-10 12:00:00');
        $newerNonMatch = $this->seedSubmission($form, null, '2026-01-11 12:00:00');
        $groupField = $form->getFields()[0];

        Craft::$app->getDb()->createCommand()->update(
            '{{%formie_submissions}}',
            ['content' => [(string)$groupField->uid => [123, 456]]],
            ['id' => $matching->id],
        )->execute();
        Craft::$app->getDb()->createCommand()->update(
            '{{%formie_submissions}}',
            ['content' => [(string)$groupField->uid => [999]]],
            ['id' => $newerNonMatch->id],
        )->execute();

        $submissions = $this->statistics->getGroupSubmissions(
            $form,
            'branch',
            '[123, 456]',
            'all',
            'all',
            1,
        );

        self::assertSame([(int)$matching->id], array_map(
            static fn(Submission $submission): int => (int)$submission->id,
            $submissions,
        ));
    }

    public function testAllSupportedFormieFieldTypesRemainGroupable(): void
    {
        $form = $this->seedFormWithTypes([
            SingleLineText::class,
            Hidden::class,
            Dropdown::class,
            Radio::class,
            Entries::class,
            Categories::class,
        ]);

        self::assertSame(
            ['branch', 'group1', 'group2', 'group3', 'group4', 'group5'],
            array_column($this->statistics->getGroupableFieldsForForm($form), 'handle'),
        );
    }

    public function testServiceAppliesLimitAndOffsetBeforeElementHydration(): void
    {
        $source = $this->methodSource(StatisticsService::class, 'getPaginatedGroupSubmissions');
        $limit = strpos($source, '->limit($limit)');
        $offset = strpos($source, '->offset($offset)');
        $hydrate = strpos($source, '$this->hydrateGroupSubmissions($submissionIds, $siteId)');

        self::assertIsInt($limit);
        self::assertIsInt($offset);
        self::assertIsInt($hydrate);
        self::assertLessThan($hydrate, $limit);
        self::assertLessThan($hydrate, $offset);
        self::assertStringContainsString('$totalCount = (int)(clone $query)->count();', $source);

        $querySource = $this->methodSource(StatisticsService::class, 'buildGroupSubmissionIdQuery');
        self::assertStringContainsString("->andWhere(['=', \$normalizedGroupExpression, \$groupValue])", $querySource);
        self::assertStringContainsString("'{{%formie_submissions}}.isIncomplete' => false", $querySource);
        self::assertStringContainsString("'{{%formie_submissions}}.isSpam' => false", $querySource);
    }

    public function testExistingGroupSubmissionsApiRemainsCompatible(): void
    {
        $method = new ReflectionMethod(StatisticsService::class, 'getGroupSubmissions');
        $parameters = $method->getParameters();

        self::assertTrue($method->isPublic());
        self::assertCount(6, $parameters);
        self::assertSame('limit', $parameters[5]->getName());
        self::assertTrue($parameters[5]->isDefaultValueAvailable());
        self::assertNull($parameters[5]->getDefaultValue());
    }

    /** @param class-string $fieldType */
    private function seedForm(string $fieldType): Form
    {
        return $this->seedFormWithTypes([$fieldType]);
    }

    /** @param list<class-string> $fieldTypes */
    private function seedFormWithTypes(array $fieldTypes): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Rating pagination test ', 'form');
        $form->handle = $this->nextTestMarker('ratingPaginationTest', 'form');
        $fieldConfigs = [];

        foreach ($fieldTypes as $index => $fieldType) {
            $fieldConfigs[] = [
                'type' => $fieldType,
                'handle' => $index === 0 ? 'branch' : 'group' . $index,
                'label' => 'Group ' . $index,
            ];
        }

        $layout = new FieldLayout();
        $layout->setPages([
            ['label' => 'Page 1', 'rows' => [['fields' => $fieldConfigs]]],
        ]);
        $form->setFormLayout($layout);
        $this->saveTestElement($form);

        return $form;
    }

    private function seedSubmission(Form $form, ?string $groupValue, string $dateCreated): Submission
    {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->title = $this->nextTestMarker('ratingPaginationTest', 'submission');
        if ($groupValue !== null) {
            $submission->setFieldValue('branch', $groupValue);
        }
        $this->saveTestElement($submission);
        $this->updateSubmissionDate($submission, $dateCreated);

        return $submission;
    }

    private function updateSubmissionDate(Submission $submission, string $dateCreated): void
    {
        foreach (['{{%elements}}', '{{%formie_submissions}}'] as $table) {
            Craft::$app->getDb()->createCommand()->update(
                $table,
                ['dateCreated' => $dateCreated],
                ['id' => $submission->id],
            )->execute();
        }
    }

    private function updateSubmissionFlags(Submission $submission, bool $isIncomplete, bool $isSpam): void
    {
        Craft::$app->getDb()->createCommand()->update(
            '{{%formie_submissions}}',
            ['isIncomplete' => $isIncomplete, 'isSpam' => $isSpam],
            ['id' => $submission->id],
        )->execute();
    }

    /** @param array{submissions: list<Submission>, totalCount: int} $page */
    private function submissionIds(array $page): array
    {
        return array_map(static fn(Submission $submission): int => (int)$submission->id, $page['submissions']);
    }

    /** @param class-string $class */
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
