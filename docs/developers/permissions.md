# Permissions

Formie Rating Field registers granular permissions you assign to user groups via **Settings → Users → User Groups → [Group Name] → Formie Rating**. (Admins always have every permission.)

## Permission structure

| Permission | Grants |
|------------|--------|
| **`formieRatingField:viewStatistics`** | Open the Statistics dashboard and view a form's stats |
| └─ `formieRatingField:exportStatistics` | Export statistics (Summary / Raw Responses / By Group) |
| └─ `formieRatingField:refreshStatistics` | Use the **Refresh** button to clear a form's cached stats |
| **`formieRatingField:manageCache`** | Generate and clear the statistics cache (Utilities page + cache console actions) |
| **`formieRatingField:manageSettings`** | View and change the plugin's settings |

`exportStatistics` and `refreshStatistics` are nested under `viewStatistics` — a user needs to view statistics before exporting or refreshing them.

## What each unlocks

- **View statistics** — the **Formie Rating → Statistics** nav item and every form's dashboard. Without it, the section is hidden.
- **Export statistics** — the **Export** menu on a form's statistics page and on a group's detail page.
- **Refresh statistics** — the **Refresh** button that clears a form's cache on demand.
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

To give someone read access to the dashboards without letting them export, refresh, or change anything, grant **`formieRatingField:viewStatistics`** alone. Add `exportStatistics` and/or `refreshStatistics` for those specific actions.
