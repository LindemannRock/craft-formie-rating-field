<?php
/**
 * LindemannRock Formie Rating Field
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\formieratingfield\tests\Integration;

use lindemannrock\formieratingfield\fields\Rating;
use lindemannrock\formieratingfield\tests\TestCase;

/**
 * Covers ownership and result scoping for generated Google Review behavior.
 *
 * @since 3.22.0
 */
final class GoogleReviewFormOwnershipTest extends TestCase
{
    public function testOnlyTheOwningFormCanReplaceItsOwnSuccessAlert(): void
    {
        $firstField = new Rating([
            'uid' => '3c4a8d56-51bf-41aa-8bbf-6876eb69547c',
            'handle' => 'satisfaction',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 0,
            'maxValue' => 5,
            'enableGoogleReview' => true,
            'googlePlaceIdField' => 'googlePlaceId',
            'googleReviewThreshold' => 5,
            'googleReviewMessageHigh' => 'HIGH <strong>text</strong>',
            'googleReviewMessageMedium' => 'MEDIUM',
            'googleReviewMessageLow' => 'LOW',
            'googleReviewButtonLabel' => 'REVIEW',
            'googleReviewUrl' => 'javascript:alert(1)',
        ]);
        $secondField = new Rating([
            'uid' => 'b8e3c949-ae80-4fc2-97b6-8acb2db7f302',
            'handle' => 'satisfaction',
            'ratingType' => Rating::RATING_TYPE_STAR,
            'minValue' => 1,
            'maxValue' => 5,
            'enableGoogleReview' => true,
            'googlePlaceIdField' => 'secondPlaceId',
            'googleReviewThreshold' => 4,
            'googleReviewMessageHigh' => 'SECOND HIGH',
            'googleReviewMessageMedium' => 'SECOND MEDIUM',
            'googleReviewMessageLow' => 'SECOND LOW',
            'googleReviewButtonLabel' => 'SECOND REVIEW',
            'googleReviewUrl' => 'https://example.test/review/{googlePlaceId}',
            'googleReviewButtonAlign' => 'end',
        ]);

        $runner = dirname(__DIR__, 2) . '/src/web/assets/field/tests/google-review-runtime.js';
        $pipes = [];
        $process = proc_open(
            ['node', $runner],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );
        self::assertIsResource($process);

        try {
            fwrite($pipes[0], $firstField->getGoogleReviewJs() . "\n" . $secondField->getGoogleReviewJs());
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertIsString($output);
            self::assertIsString($error);
            self::assertSame(0, proc_close($process), $error);
            $process = null;

            $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(9, $result['scenarios'] ?? null);
            self::assertSame(33, $result['assertions'] ?? null);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
}
