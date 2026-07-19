# Permissions

Formie Rating Field registers granular permissions you assign to user groups via **Settings → Users → User Groups → [Group Name] → Formie Rating**. These permissions are an additional layer on top of Formie's submission permissions; they do not replace Formie's form-level access controls. (Admins continue to have access through Craft's normal permission handling.)

## Permission structure

| Permission | Grants |
|------------|--------|
| **`formieRatingField:viewStatistics`** | Open the Statistics dashboard and view a form's stats |
| └─ `formieRatingField:exportStatistics` | Export statistics (Summary / Raw Responses / By Group) |
| └─ `formieRatingField:refreshStatistics` | Use the **Refresh** button to clear a form's cached stats |
| **`formieRatingField:manageCache`** | Generate and clear the statistics cache (Utilities page + cache console actions) |
| **`formieRatingField:manageSettings`** | View and change the plugin's settings |

`exportStatistics` and `refreshStatistics` are nested under `viewStatistics` — a user needs to view statistics before exporting or refreshing them.

## Formie submission access is also required

For any particular form, the user must pass both permission layers:

1. The relevant Formie Rating Field permission from the table above.
2. Either Formie's global `formie-viewSubmissions` permission or the form-specific `formie-viewSubmissions:{formUid}` permission.

This keeps the statistics surface aligned with the forms a user can open in Formie's own Submissions section. A user with submission access to only some forms sees only those forms on the statistics index and in the Rating Statistics dashboard widget. Unauthorized forms are not listed, searched, sorted, counted, or linked there. Opening a form-specific statistics, group, chart-data, export, or refresh URL without the matching Formie permission returns HTTP 403.

## What each unlocks

- **View statistics** — the **Formie Rating → Statistics** nav item and dashboards for forms the user may access in Formie. Without it, the section is hidden.
- **Export statistics** — the **Export** menu on an accessible form's statistics page and on a group's detail page.
- **Refresh statistics** — the **Refresh** button that clears an accessible form's cache on demand.
- **Manage cache** — the cache actions on the **Utilities → Formie Rating** page, the *Formie Rating caches* entry in **Utilities → Caches**, and the `cache/*` console commands' Control-Panel counterparts.
- **Manage settings** — the **Settings** subnav and the ability to save changes. The CP nav hides entirely if a user has none of these permissions.

## Checking permissions

In Twig:

```twig
{% if currentUser.can('formieRatingField:viewStatistics') %}
    {# user can view statistics #}
{% endif %}
```

In PHP:

```php
if (Craft::$app->getUser()->checkPermission('formieRatingField:exportStatistics')) {
    // ...
}

// In a controller action
$this->requirePermission('formieRatingField:manageSettings');
```

## Read-only access

To give someone read access to the dashboards without letting them export, refresh, or change anything, grant **`formieRatingField:viewStatistics`** plus Formie's global or relevant per-form submission permission. Add `exportStatistics` and/or `refreshStatistics` for those specific actions; the Formie submission requirement still applies.
