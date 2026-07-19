<?php
/**
 * Formie Rating Field plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\formieratingfield\traits;

use Craft;
use verbb\formie\elements\Form;
use yii\web\ForbiddenHttpException;

/**
 * Centralizes Formie's global and per-form submission access checks.
 *
 * @since 3.22.0
 */
trait FormieSubmissionPermissionTrait
{
    /**
     * Whether the current user can view submissions for the given form.
     */
    protected function canViewFormieSubmissions(Form $form): bool
    {
        $user = Craft::$app->getUser();

        return $user->checkPermission('formie-viewSubmissions')
            || $user->checkPermission("formie-viewSubmissions:{$form->uid}");
    }

    /**
     * Require Formie submission access for the given form.
     *
     * @throws ForbiddenHttpException
     */
    protected function requireFormieSubmissionAccess(Form $form): void
    {
        if (!$this->canViewFormieSubmissions($form)) {
            throw new ForbiddenHttpException();
        }
    }

    /**
     * Remove forms the current user cannot access through Formie's submission ACL.
     *
     * @param array<int, array{form: Form, ratingFieldCount: int, totalSubmissions: int|null}> $forms
     * @return array<int, array{form: Form, ratingFieldCount: int, totalSubmissions: int|null}>
     */
    protected function filterFormsByFormieSubmissionAccess(array $forms): array
    {
        return array_values(array_filter(
            $forms,
            fn(array $item): bool => $this->canViewFormieSubmissions($item['form']),
        ));
    }
}
