<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\formieratingfield\console\controllers;

use lindemannrock\base\console\controllers\AbstractHelpController;

/**
 * Console help for Formie Rating Field commands.
 *
 * @since 3.20.0
 */
final class HelpController extends AbstractHelpController
{
    /**
     * @inheritdoc
     */
    protected function helpManifest(): array
    {
        return [
            'title' => 'Formie Rating Field',
            'pluginHandle' => 'formie-rating-field',
            'commandPrefixes' => [
                'php craft',
                'ddev craft',
            ],
            'summary' => 'Use these commands to inspect, clear, and regenerate cached rating-field statistics.',
            'common' => [
                'cache/info',
                'cache/generate',
                'cache/clear-form',
                'cache/clear',
            ],
            'groups' => [
                [
                    'name' => 'cache',
                    'label' => 'Statistics Cache',
                    'description' => 'Inspect, clear, and queue cache generation for rating statistics.',
                    'commands' => [
                        [
                            'path' => 'cache/info',
                            'summary' => 'Show cache status.',
                            'description' => 'Print the statistics cache path, file count, and effective cache generation schedule.',
                            'examples' => [
                                'formie-rating-field/cache/info',
                            ],
                        ],
                        [
                            'path' => 'cache/generate',
                            'summary' => 'Queue statistics cache generation.',
                            'description' => 'Queue a cache generation job for every Formie form with rating fields, or one specific form when --form-id is provided.',
                            'usageOptions' => '[--form-id=<form-id>]',
                            'options' => [
                                [
                                    'name' => '--form-id',
                                    'description' => 'Optional Formie form ID. Omit to generate cache for all forms with rating fields.',
                                ],
                            ],
                            'examples' => [
                                'formie-rating-field/cache/generate',
                                'formie-rating-field/cache/generate --form-id=34',
                            ],
                            'notes' => [
                                'This queues a Craft queue job instead of generating the cache inline.',
                                'Make sure the Craft queue is running when regenerating large statistics caches.',
                            ],
                        ],
                        [
                            'path' => 'cache/clear-form',
                            'summary' => 'Clear cache for one form.',
                            'description' => 'Delete cached rating statistics for one Formie form.',
                            'usageOptions' => '<formId>',
                            'options' => [
                                [
                                    'name' => 'formId',
                                    'description' => 'Formie form ID.',
                                    'required' => true,
                                ],
                            ],
                            'examples' => [
                                'formie-rating-field/cache/clear-form 34',
                            ],
                            'notes' => [
                                'Use this when one form was changed and a full cache clear is unnecessary.',
                            ],
                        ],
                        [
                            'path' => 'cache/clear',
                            'summary' => 'Clear all statistics cache.',
                            'description' => 'Delete all Formie Rating Field statistics cache files.',
                            'examples' => [
                                'formie-rating-field/cache/clear',
                            ],
                            'notes' => [
                                'This is destructive for cached statistics only. Statistics are regenerated on demand or by queue generation.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
