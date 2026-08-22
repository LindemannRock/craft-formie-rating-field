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
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;
use RuntimeException;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Date;
use verbb\formie\fields\SingleLineText;
use verbb\formie\fields\subfields\DateDate;
use verbb\formie\fields\subfields\DateTime;
use verbb\formie\models\FieldLayout;

/**
 * Covers exact ownership and cleanup of persisted Formie form fixtures.
 *
 * @since 3.22.0
 */
final class FormieFormFixtureLifecycleTest extends TestCase
{
    public function testMultipleFormsAndRepeatedCleanupPreserveAnUnownedOrphanGraph(): void
    {
        [$sentinel, $sentinelFingerprint] = $this->seedOrphanSentinel();
        $first = $this->seedForm('first');
        $second = $this->seedForm('second', true);
        $this->seedSubmission($first, 'First');
        $this->seedSubmission($second, 'Second');
        $this->seedTrackedCacheFile($first);
        $this->seedTrackedCacheFile($second);
        $firstIdentity = $this->formieFormFixtureIdentity($first);
        $secondIdentity = $this->formieFormFixtureIdentity($second);
        self::assertCount(1, $firstIdentity['layoutIds']);
        self::assertCount(2, $secondIdentity['layoutIds']);

        $this->cleanupFormieFormFixture($second);
        $this->cleanupFormieFormFixture($first);
        $this->cleanupFormieFormFixture($second);
        $this->cleanupFormieFormFixture($first);

        $this->assertFixtureRemoved($firstIdentity);
        $this->assertFixtureRemoved($secondIdentity);
        self::assertSame($sentinelFingerprint, $this->fingerprintLayoutGraph($sentinel));
    }

    public function testDeliberateFailureCleanupPreservesAnUnownedOrphanGraph(): void
    {
        [$sentinel, $sentinelFingerprint] = $this->seedOrphanSentinel();
        $identity = null;

        try {
            try {
                $form = $this->seedForm('failure');
                $this->seedSubmission($form, 'Failure');
                $this->seedTrackedCacheFile($form);
                $identity = $this->formieFormFixtureIdentity($form);

                throw new RuntimeException('Deliberate fixture failure.');
            } finally {
                if (isset($form)) {
                    $this->cleanupFormieFormFixture($form);
                }
            }
        } catch (RuntimeException $exception) {
            self::assertSame('Deliberate fixture failure.', $exception->getMessage());
        }

        self::assertIsArray($identity);
        $this->assertFixtureRemoved($identity);
        self::assertSame($sentinelFingerprint, $this->fingerprintLayoutGraph($sentinel));
    }

    public function testCleanupAuthorityIsLimitedToCapturedIdentities(): void
    {
        $source = $this->methodSource(TestCase::class, 'cleanupFormieFormFixture');

        self::assertStringContainsString("['id' => \$layoutId]", $source);
        self::assertStringContainsString("\$fixture['elementIds']", $source);
        self::assertStringContainsString("\$fixture['layoutIds']", $source);
        self::assertStringContainsString("\$fixture['cacheFiles']", $source);
        self::assertStringNotContainsString('truncate', strtolower($source));
        self::assertStringNotContainsString('glob(', $source);
        self::assertStringNotContainsString("['like'", $source);
        self::assertStringNotContainsString('title', strtolower($source));
        self::assertStringNotContainsString('handle', strtolower($source));
    }

    /** @return array{array{layoutId: int, pageIds: list<int>, rowIds: list<int>, fieldIds: list<int>}, string} */
    private function seedOrphanSentinel(): array
    {
        $form = $this->seedForm('sentinel');
        $identity = $this->formieFormFixtureIdentity($form);
        $element = Craft::$app->getElements()->getElementById($identity['formId'], null, null, ['status' => null]);
        self::assertNotNull($element);
        self::assertTrue(Craft::$app->getElements()->deleteElement($element, true));

        $sentinel = [
            'layoutId' => $identity['layoutId'],
            'pageIds' => $identity['pageIds'],
            'rowIds' => $identity['rowIds'],
            'fieldIds' => $identity['fieldIds'],
        ];

        return [$sentinel, $this->fingerprintLayoutGraph($sentinel)];
    }

    private function seedForm(string $kind, bool $withNestedLayout = false): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Form fixture lifecycle ', $kind);
        $form->handle = $this->nextTestMarker('formFixtureLifecycle', $kind);
        $fields = [[
            'type' => SingleLineText::class,
            'handle' => 'branch',
            'label' => 'Branch',
        ]];
        if ($withNestedLayout) {
            $fields[] = [
                'type' => Date::class,
                'handle' => 'appointment',
                'label' => 'Appointment',
                'rows' => [[
                    'fields' => [
                        [
                            'type' => DateDate::class,
                            'handle' => 'date',
                            'label' => 'Date',
                            'enabled' => true,
                        ],
                        [
                            'type' => DateTime::class,
                            'handle' => 'time',
                            'label' => 'Time',
                            'enabled' => false,
                        ],
                    ],
                ]],
            ];
        }

        $layout = new FieldLayout();
        $layout->setPages([[
            'label' => 'Page 1',
            'rows' => [[
                'fields' => $fields,
            ]],
        ]]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);

        return $form;
    }

    private function seedSubmission(Form $form, string $branch): void
    {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->title = $this->nextTestMarker('Form fixture lifecycle ', 'submission');
        $submission->setFieldValue('branch', $branch);
        $this->saveTestElement($submission, false, false, false);
    }

    private function seedTrackedCacheFile(Form $form): void
    {
        FileHelper::createDirectory($this->statisticsCachePath());
        $path = $this->statisticsCachePath() . $form->id . '-' . bin2hex(random_bytes(4)) . '.cache';
        self::assertNotFalse(file_put_contents($path, 'tracked Formie fixture cache'));
        $this->trackFormieFixtureCacheFile($form, $path);
    }

    /**
     * @param array{layoutId: int, pageIds: list<int>, rowIds: list<int>, fieldIds: list<int>} $sentinel
     */
    private function fingerprintLayoutGraph(array $sentinel): string
    {
        $state = [
            'layout' => (new Query())->from('{{%formie_fieldlayouts}}')->where(['id' => $sentinel['layoutId']])->one(),
            'pages' => (new Query())->from('{{%formie_fieldlayout_pages}}')->where(['id' => $sentinel['pageIds']])->orderBy('id')->all(),
            'rows' => (new Query())->from('{{%formie_fieldlayout_rows}}')->where(['id' => $sentinel['rowIds']])->orderBy('id')->all(),
            'fields' => (new Query())->from('{{%formie_fields}}')->where(['id' => $sentinel['fieldIds']])->orderBy('id')->all(),
        ];

        return hash('sha256', serialize($state));
    }

    /**
     * @param array{
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
     * } $identity
     */
    private function assertFixtureRemoved(array $identity): void
    {
        self::assertSame(0, $this->countIds('{{%elements}}', $identity['elementIds']));
        self::assertSame(0, $this->countIds('{{%elements_sites}}', $identity['elementSiteIds']));
        self::assertSame(0, $this->countIds('{{%formie_forms}}', [$identity['formId']]));
        self::assertSame(0, $this->countIds('{{%formie_submissions}}', $identity['submissionIds']));
        self::assertSame(0, $this->countIds('{{%formie_fieldlayouts}}', $identity['layoutIds']));
        self::assertSame(0, $this->countIds('{{%formie_fieldlayout_pages}}', $identity['pageIds']));
        self::assertSame(0, $this->countIds('{{%formie_fieldlayout_rows}}', $identity['rowIds']));
        self::assertSame(0, $this->countIds('{{%formie_fields}}', $identity['fieldIds']));
        self::assertSame([], array_values(array_filter($identity['cacheFiles'], 'is_file')));
    }

    /** @param list<int> $ids */
    private function countIds(string $table, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int)(new Query())->from($table)->where(['id' => $ids])->count();
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
