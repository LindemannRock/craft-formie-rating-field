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
use DOMDocument;
use DOMElement;
use DOMXPath;
use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** @param array<string, mixed> $config */
    #[DataProvider('cpRatingValueProvider')]
    public function testCpResavePreservesUnansweredAndSelectedRatings(array $config, mixed $value): void
    {
        $form = $this->createRatingForm(['handle' => 'cpRating', ...$config]);
        $submission = $this->createSubmission($form, 'cpRating', $value);
        $this->saveTestElement($submission, true, false, false);
        $submission = $this->reloadSubmission($submission);
        $expected = $value === null || $value === '' ? null : (float)$value;
        self::assertSame($expected, $submission->getFieldValue('cpRating'));

        $options = $this->cpOptions($form, $submission);
        $selected = $options->query('//select[@name="cpRating"]/option[@selected]');
        self::assertNotFalse($selected);
        self::assertCount(1, $selected, 'The CP input must explicitly select the saved value, including blank.');
        $option = $selected->item(0);
        self::assertInstanceOf(DOMElement::class, $option);
        self::assertSame($expected === null ? '' : (string)$expected, $option->getAttribute('value'));

        $submission->setScenario(Submission::SCENARIO_LIVE);
        $submission->setFieldValueFromRequest('cpRating', $option->getAttribute('value'));
        $this->saveTestElement($submission, true, false, false);
        self::assertSame($expected, $this->reloadSubmission($submission)->getFieldValue('cpRating'));
    }

    public static function cpRatingValueProvider(): array
    {
        $cases = [];
        foreach ([Rating::RATING_TYPE_STAR, Rating::RATING_TYPE_EMOJI, Rating::RATING_TYPE_NPS] as $type) {
            foreach ($type === Rating::RATING_TYPE_NPS ? [0] : [0, 1] as $min) {
                $config = ['ratingType' => $type, 'minValue' => $min, 'maxValue' => 5];
                foreach (['missing' => null, 'empty' => '', 'positive' => '3'] as $name => $value) {
                    $cases["$type minimum $min $name"] = [$config, $value];
                }
                if ($min === 0) {
                    $cases["$type numeric zero"] = [$config, 0];
                    $cases["$type string zero"] = [$config, '0'];
                    $cases["$type required zero"] = [[...$config, 'required' => true], 0];
                }
            }
        }
        $cases['half star'] = [
            ['ratingType' => Rating::RATING_TYPE_STAR, 'minValue' => 1, 'maxValue' => 5, 'allowHalfRatings' => true],
            '3.5',
        ];

        return $cases;
    }

    #[DataProvider('cpClearingProvider')]
    public function testCpBlankOptionClearsOptionalRatingButRequiredRatingRejectsIt(string $type, bool $required): void
    {
        $form = $this->createRatingForm([
            'handle' => 'cpRating', 'ratingType' => $type, 'required' => $required,
        ]);
        $submission = $this->createSubmission($form, 'cpRating', '3');
        $this->saveTestElement($submission, true, false, false);
        $submission = $this->reloadSubmission($submission);
        $options = $this->cpOptions($form, $submission);
        $blank = $options->query('//select[@name="cpRating"]/option[@value="" and not(@disabled)]');
        self::assertNotFalse($blank);
        self::assertCount(1, $blank, 'Editors must be able to choose an unanswered value.');
        $option = $blank->item(0);
        self::assertInstanceOf(DOMElement::class, $option);

        $submission->setScenario(Submission::SCENARIO_LIVE);
        $submission->setFieldValueFromRequest('cpRating', $option->getAttribute('value'));
        if ($required) {
            self::assertFalse(Craft::$app->getElements()->saveElement($submission, true, false, false));
            self::assertTrue($submission->hasErrors('cpRating'));
            self::assertSame(3.0, $this->reloadSubmission($submission)->getFieldValue('cpRating'));
        } else {
            $this->saveTestElement($submission, true, false, false);
            self::assertNull($this->reloadSubmission($submission)->getFieldValue('cpRating'));
        }
    }

    public static function cpClearingProvider(): array
    {
        $cases = [];
        foreach ([Rating::RATING_TYPE_STAR, Rating::RATING_TYPE_EMOJI, Rating::RATING_TYPE_NPS] as $type) {
            $cases["$type optional"] = [$type, false];
            $cases["$type required"] = [$type, true];
        }

        return $cases;
    }

    private function cpOptions(Form $form, Submission $submission): DOMXPath
    {
        $field = $form->getFormLayout()->getFieldByHandle('cpRating');
        self::assertInstanceOf(Rating::class, $field);
        $view = Craft::$app->getView();
        $originalTemplateMode = $view->getTemplateMode();
        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $html = (string)$field->getCpInputHtml($submission->getFieldValue('cpRating'), $submission);
        } finally {
            $view->setTemplateMode($originalTemplateMode);
        }
        $document = new DOMDocument();
        self::assertTrue($document->loadHTML($html));

        return new DOMXPath($document);
    }

    private function reloadSubmission(Submission $submission): Submission
    {
        $reloaded = Submission::find()->id($submission->id)->status(null)->isSpam(null)->isIncomplete(null)->one();
        self::assertInstanceOf(Submission::class, $reloaded);

        return $reloaded;
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
