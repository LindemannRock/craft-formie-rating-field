# Statistics service

Need rating numbers in your own code — a custom dashboard widget, a report, an integration? The same service that powers the Statistics dashboard is a public component you can call directly. It computes type-aware metrics, reads cached results, and exposes the cache controls.

Access it from the plugin instance:

```php
use lindemannrock\formieratingfield\FormieRatingField;

$statistics = FormieRatingField::getInstance()->statistics;
```

## Discovering forms and fields

```php
// Forms that contain at least one Rating field (+ field count, submission count).
// Pass a site ID to scope, or 'all' (default) for the cross-site rollup.
$forms = $statistics->getFormsWithRatingFields($siteId = 'all');

// The Rating fields on a form.
$fields = $statistics->getRatingFieldsForForm($form);

// One Rating field by handle (or null).
$field = $statistics->getRatingFieldByHandle($form, 'satisfaction');

// Non-rating fields you can group by (plain text, dropdown, radio, Entries, Categories…).
$groupable = $statistics->getGroupableFieldsForForm($form);
```

## Computing statistics

`getFieldStatistics()` is the cached entry point used by the dashboard. It returns a type-aware structure (average/median/most-common for star/emoji, or NPS score with promoter/passive/detractor breakdown for NPS).

```php
$stats = $statistics->getFieldStatistics(
    $form,
    $field,
    $dateRange = 'all',     // 'last7days', 'last30days', 'last90days', 'all', …
    $groupByHandle = null,  // group by another field's handle
    $siteId = 'all',
);
```

If you already have a set of submissions in hand and want stats without touching the cache, use `calculateStatsForSubmissions()`:

```php
// @since 3.16.0
$stats = $statistics->calculateStatsForSubmissions($submissions, $field);
```

Supporting reads:

```php
$trend        = $statistics->getTrendData($form, $field, $dateRange = 'all', $siteId = 'all');
$distribution = $statistics->getDistributionData($form, $field, $dateRange = 'all', $siteId = 'all');
$total        = $statistics->getTotalSubmissions($form, $dateRange = 'all', $siteId = 'all');
```

## Grouped statistics

```php
// Stats per group value (ordered by count).
$grouped = $statistics->getGroupedStatistics($form, $field, $dateRange, $groupByHandle, $siteId = 'all');

// The submissions behind one group value.
$submissions = $statistics->getGroupSubmissions($form, $groupByHandle, $groupValue, $dateRange = 'all', $siteId = 'all', $limit = null);
```

## Export rows

These build the rows the [export](../feature-tour/exporting-data.md) feature writes — useful if you assemble your own files.

```php
// @since 3.16.0 — all three
$summary = $statistics->buildSummaryExportRows($form, $dateRange = 'all', $siteId = 'all');
$raw     = $statistics->buildRawResponsesExportRows($form, $dateRange = 'all', $siteId = 'all'); // honors maxExportRows
$byGroup = $statistics->buildGroupedExportRows($form, $dateRange = 'all', $groupByHandle = null, $siteId = 'all');
```

## Cache control

```php
$statistics->clearCacheForForm($formId); // clear one form's cached stats
$statistics->clearAllCache();            // clear everything
$count = $statistics->getCacheFileCount(); // file cache only
```

See [Caching](../feature-tour/caching.md) for how the cache behaves.

> [!NOTE]
> The service is registered as the plugin's `statistics` component (`@since 3.3.0`). Method signatures here are verified against the current source; methods marked `@since 3.16.0` were added after the service.
