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
use lindemannrock\formieratingfield\tests\TestCase;
use verbb\formie\base\Field;
use verbb\formie\elements\Submission;
use verbb\formie\fields\Phone;
use verbb\formie\fields\SingleLineText;

/**
 * Covers native Formie presentation of group-detail field values.
 *
 * @since 3.22.0
 */
final class GroupDetailValueRenderingTest extends TestCase
{
    public function testCountryPhoneUsesInternationalPresentation(): void
    {
        $field = new Phone(['countryEnabled' => true]);
        $submission = new Submission();
        $value = $field->normalizeValue([
            'number' => '503369256',
            'country' => 'SA',
        ], $submission);

        $rendered = $this->renderTableValue($field, $value, $submission);

        self::assertSame('+966 50 336 9256', $rendered);
        self::assertStringNotContainsString('503369256, SA, 1', $rendered);
    }

    public function testSimplePhoneKeepsPlainNumberPresentation(): void
    {
        $field = new Phone(['countryEnabled' => false]);
        $submission = new Submission();
        $value = $field->normalizeValue('503369256', $submission);

        self::assertSame('503369256', $this->renderTableValue($field, $value, $submission));
    }

    public function testEmptyNativePresentationUsesEmDash(): void
    {
        $field = new Phone(['countryEnabled' => true]);
        $submission = new Submission();
        $value = $field->normalizeValue(null, $submission);

        self::assertSame('—', $this->renderTableValue($field, $value, $submission));
    }

    public function testNativePresentationIsTruncatedAfterConversion(): void
    {
        $field = new SingleLineText();
        $submission = new Submission();

        self::assertSame(
            str_repeat('a', 100) . '...',
            $this->renderTableValue($field, str_repeat('a', 101), $submission),
        );
    }

    public function testGroupDetailTemplateUsesNativeFormiePresentation(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/src/templates/statistics/group-detail.twig');
        self::assertIsString($template);

        self::assertStringContainsString('formField.getValueAsString(value, item)', $template);
        self::assertStringNotContainsString("value|join(', ')", $template);
        self::assertStringContainsString('displayValue|length > 100', $template);
        self::assertStringNotContainsString('displayValue|raw', $template);
    }

    private function renderTableValue(Field $field, mixed $value, Submission $submission): string
    {
        return trim(Craft::$app->getView()->renderString(
            <<<'TWIG'
                {% set displayValue = formField.getValueAsString(value, submission) %}
                {% if displayValue is not null and displayValue is not same as('') %}
                    {{ displayValue|length > 100 ? displayValue|slice(0, 100) ~ '...' : displayValue }}
                {% else %}
                    —
                {% endif %}
                TWIG,
            [
                'formField' => $field,
                'value' => $value,
                'submission' => $submission,
            ],
        ));
    }
}
