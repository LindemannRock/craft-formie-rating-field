<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use DateTime;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\formieratingfield\tests\TestCase;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Date;
use verbb\formie\fields\Entries;
use verbb\formie\fields\subfields\DateDate;
use verbb\formie\fields\subfields\DateTime as DateTimeField;
use verbb\formie\models\FieldLayout;

/**
 * Covers Formie-aware value normalization for grouped export formats.
 *
 * @since 3.22.0
 */
final class GroupedExportValueNormalizationTest extends TestCase
{
    public function testDateAndRelationalValuesAreSafeAcrossCsvExcelAndJson(): void
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Grouped export normalization ', 'form');
        $form->handle = $this->nextTestMarker('groupedExportNormalization', 'form');
        $layout = new FieldLayout();
        $layout->setPages([
            [
                'label' => 'Page 1',
                'rows' => [[
                    'fields' => [
                        [
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
                                        'type' => DateTimeField::class,
                                        'handle' => 'time',
                                        'label' => 'Time',
                                        'enabled' => false,
                                    ],
                                ],
                            ]],
                        ],
                        [
                            'type' => Entries::class,
                            'handle' => 'relatedEntries',
                            'label' => 'Related Entries',
                        ],
                    ],
                ]],
            ],
        ]);
        $form->setFormLayout($layout);
        $this->saveTestElement($form);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setFieldValue('appointment', new DateTime('2026-07-22 15:30:00'));
        $submission->setFieldValue('relatedEntries', [999999999]);

        $dateExport = $submission->getValueForExport('appointment');
        $relationExport = $submission->getValueForExport('relatedEntries');
        $rows = [[$dateExport, $relationExport]];
        $headers = ['Appointment', 'Related Entries'];

        self::assertIsString($dateExport);
        self::assertIsString($relationExport);
        self::assertNotSame(DateTime::class, $dateExport);
        self::assertNotSame('craft\\elements\\db\\EntryQuery', $relationExport);
        self::assertStringContainsString('2026', ExportHelper::csvContent($rows, $headers));
        self::assertNotSame('', ExportHelper::excelContent($rows, $headers));

        $json = ExportHelper::jsonContent([
            'appointment' => $submission->getValueAsJson('appointment'),
            'relatedEntries' => $submission->getValueAsJson('relatedEntries'),
        ]);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsString($decoded['appointment']);
        self::assertIsArray($decoded['relatedEntries']);
    }
}
