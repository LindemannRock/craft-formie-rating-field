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
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\tests\TestCase;
use ReflectionMethod;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\models\FieldLayout;

/**
 * Covers Rating values through Formie's submission-validation pipeline.
 *
 * @since 3.22.0
 */
final class RatingSubmissionValidationTest extends TestCase
{
    public function testWholeStarSubmissionValuesRespectRangeAndWholeSteps(): void
    {
        $form = $this->createRatingForm([
            'handle' => 'wholeRating',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 2,
            'maxValue' => 6,
            'allowHalfRatings' => false,
        ]);

        foreach ([2, '4', 6] as $value) {
            $this->assertSubmissionValueValid($form, 'wholeRating', $value, (float)$value);
        }

        foreach ([1, 7, 3.5, 47.3, 'abc', ['3'], false, true] as $value) {
            $this->assertSubmissionValueInvalid($form, 'wholeRating', $value);
        }
    }

    public function testHalfStarSubmissionValuesAllowOnlyConfiguredHalfSteps(): void
    {
        $form = $this->createRatingForm([
            'handle' => 'halfRating',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'allowHalfRatings' => true,
        ]);

        foreach ([1, 3, 3.5, '4.5', 5] as $value) {
            $this->assertSubmissionValueValid($form, 'halfRating', $value, (float)$value);
        }

        foreach ([3.25, 0.5, 5.5] as $value) {
            $this->assertSubmissionValueInvalid($form, 'halfRating', $value);
        }
    }

    public function testEmojiSubmissionValuesAllowOnlyConfiguredIntegers(): void
    {
        $form = $this->createRatingForm([
            'handle' => 'emojiRating',
            'ratingType' => Rating::RATING_TYPE_EMOJI,
            'minValue' => 0,
            'maxValue' => 4,
            'allowHalfRatings' => true,
        ]);

        foreach ([0, 2, 4] as $value) {
            $this->assertSubmissionValueValid($form, 'emojiRating', $value, (float)$value);
        }

        $this->assertSubmissionValueInvalid($form, 'emojiRating', 2.5);
    }

    public function testNpsSubmissionValuesRejectTamperedNumbersAndTypes(): void
    {
        $form = $this->createRatingForm([
            'handle' => 'npsRating',
            'ratingType' => Rating::RATING_TYPE_NPS,
            'minValue' => 4,
            'maxValue' => 8,
        ]);

        foreach ([0, 10] as $value) {
            $this->assertSubmissionValueValid($form, 'npsRating', $value, (float)$value);
        }

        foreach ([-1, 11, 7.5, 'abc', ['0'], false, true] as $value) {
            $this->assertSubmissionValueInvalid($form, 'npsRating', $value);
        }
    }

    public function testOptionalAndRequiredEmptyValuesRemainFormieOwned(): void
    {
        $optionalForm = $this->createRatingForm([
            'handle' => 'optionalRating',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'required' => false,
        ]);
        $optionalSubmission = $this->createSubmission($optionalForm, 'optionalRating', '');

        self::assertTrue($optionalSubmission->validate(), json_encode($optionalSubmission->getErrors()));
        self::assertNull($optionalSubmission->getFieldValue('optionalRating'));

        $requiredForm = $this->createRatingForm([
            'handle' => 'requiredRating',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'required' => true,
        ]);
        $requiredSubmission = $this->createSubmission($requiredForm, 'requiredRating', '');

        self::assertFalse($requiredSubmission->validate());
        self::assertTrue($requiredSubmission->hasErrors('requiredRating'));
    }

    public function testElementSavingPersistsValidValueAndRejectsInvalidValue(): void
    {
        $form = $this->createRatingForm([
            'handle' => 'savedRating',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'allowHalfRatings' => false,
        ]);

        $validSubmission = $this->createSubmission($form, 'savedRating', '5');
        $this->saveTestElement($validSubmission, true, false, false);

        self::assertNotNull($validSubmission->id);
        self::assertSame(5.0, $validSubmission->getFieldValue('savedRating'));

        $invalidSubmission = $this->createSubmission($form, 'savedRating', 3.5);
        $saved = Craft::$app->getElements()->saveElement($invalidSubmission, true, false, false);

        self::assertFalse($saved);
        self::assertNull($invalidSubmission->id);
        self::assertTrue($invalidSubmission->hasErrors('savedRating'));
        self::assertSame(
            1,
            (int)Submission::find()
                ->formId($form->id)
                ->status(null)
                ->isSpam(null)
                ->isIncomplete(null)
                ->count(),
        );
    }

    public function testValidationRulesPreserveParentRangeAndBuiltInMessages(): void
    {
        $field = new Rating([
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 3,
            'allowHalfRatings' => false,
            'matchField' => '{field:confirmationRating}',
        ]);
        $rules = $field->getElementValidationRules();
        $source = $this->methodSource('getElementValidationRules');

        self::assertStringContainsString('$rules = parent::getElementValidationRules();', $source);
        self::assertSame(['validateMatchField', 'skipOnEmpty' => false], $rules[0]);
        self::assertSame(['number', 'min' => 1, 'max' => 3], $rules[1]);
        self::assertSame(['in', 'range' => [1, 2, 3]], $rules[2]);
        self::assertStringNotContainsString("'message'", $source);
        self::assertStringNotContainsString('"message"', $source);
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function createRatingForm(array $fieldConfig): Form
    {
        $form = new Form();
        $form->title = $this->nextTestMarker('Rating validation test ', 'form');
        $form->handle = $this->nextTestMarker('ratingValidationTest', 'form');

        $layout = new FieldLayout();
        $layout->setPages([
            [
                'label' => 'Page 1',
                'rows' => [
                    [
                        'fields' => [
                            array_merge([
                                'type' => Rating::class,
                                'label' => 'Rating',
                            ], $fieldConfig),
                        ],
                    ],
                ],
            ],
        ]);
        $form->setFormLayout($layout);
        $this->saveTestForm($form);

        return $form;
    }

    private function createSubmission(Form $form, string $fieldHandle, mixed $value): Submission
    {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->setScenario(Submission::SCENARIO_LIVE);
        $submission->title = $this->nextTestMarker('ratingValidationTest', 'submission');
        $submission->setFieldValueFromRequest($fieldHandle, $value);

        return $submission;
    }

    private function assertSubmissionValueValid(
        Form $form,
        string $fieldHandle,
        mixed $value,
        float $expected,
    ): void {
        $submission = $this->createSubmission($form, $fieldHandle, $value);

        self::assertTrue($submission->validate(), json_encode($submission->getErrors()));
        self::assertFalse($submission->hasErrors($fieldHandle));
        self::assertSame($expected, $submission->getFieldValue($fieldHandle));
    }

    private function assertSubmissionValueInvalid(Form $form, string $fieldHandle, mixed $value): void
    {
        $submission = $this->createSubmission($form, $fieldHandle, $value);

        self::assertFalse($submission->validate(), sprintf(
            'Expected %s to fail validation.',
            json_encode($value),
        ));
        self::assertTrue($submission->hasErrors($fieldHandle), json_encode($submission->getErrors()));
    }

    private function methodSource(string $methodName): string
    {
        $reflection = new ReflectionMethod(Rating::class, $methodName);
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
