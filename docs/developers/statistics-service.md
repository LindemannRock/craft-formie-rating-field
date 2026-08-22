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
// Pass a site ID, a list of site IDs, or 'all' (default) for the cross-site rollup.
$forms = $statistics->getFormsWithRatingFields($siteId = 'all');

// The Rating fields on a form.
$fields = $statistics->getRatingFieldsForForm($form);

// One Rating field by handle (or null).
$field = $statistics->getRatingFieldByHandle($form, 'satisfaction');

// Non-rating fields you can group by (plain text, dropdown, radio, Entries, Categories…).
$groupable = $statistics->getGroupableFieldsForForm($form);
```

## Computing statistics

`getFieldStatistics()` is the cached entry point used by the dashboard. It returns a type-aware structure (average/median/most-common for star/emoji, or NPS score with promoter/passive/detractor breakdown for NPS) plus a UTC ISO 8601 `generatedAt` value. Grouped dashboard payloads contain at most 100 rows, with `totalGroups` and `isLimited` describing the complete result.

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

Every public statistics, grouped-submission, trend, distribution, total, and export-row method accepts a specific site ID, an array of site IDs, or the literal `'all'`. Site-ID arrays are normalized for deterministic cache identity, an empty array returns no matching submissions, and a submission present in more than one selected site is counted once. The literal `'all'` retains its trusted-caller cross-site meaning; Control Panel requests convert their **All Sites** selection to the current user's live editable-site ID list before calling the service.

`getTrendData()` returns `labels`, `values`, `counts`, `scaleMin`, `scaleMax`, and the UTC ISO 8601 `generatedAt` timestamp. Both field-statistics and trend payloads are stamped before they are cached. Cache hits preserve the original timestamp instead of replacing it with the current request time.

## Grouped statistics

```php
// Stats per group value (ordered by count). Pass a final limit for a bounded
// result; totalGroups remains the complete count and isLimited reports truncation.
$grouped = $statistics->getGroupedStatistics(
    $form,
    $field,
    $dateRange,
    $groupByHandle,
    $siteId = 'all',
    $limit = null,
);

// The submissions behind one group value.
$submissions = $statistics->getGroupSubmissions($form, $groupByHandle, $groupValue, $dateRange = 'all', $siteId = 'all', $limit = null);

// @since 3.22.0 — one database-filtered page plus the complete matching count.
$page = $statistics->getPaginatedGroupSubmissions(
    $form,
    $groupByHandle,
    $groupValue,
    $dateRange = 'all',
    $siteId = 'all',
    $limit = 100,
    $offset = 0,
);
// $page['submissions'] contains only the hydrated page; $page['totalCount']
// is the count before limit/offset.
```

`getGroupedStatistics()` calculates star/emoji medians from database-aggregated value frequencies, so it does not transport every raw rating row into PHP. `getGroupSubmissions()` remains the compatibility/export-oriented API. Its optional limit is applied after the form, spam/incomplete, date, site, and raw group-value predicates. Use `getPaginatedGroupSubmissions()` for an interactive listing that also needs a complete matching count and offset. Both submission methods compare relational groups with the same stored JSON value emitted by the grouped-statistics links.

## Export rows

These build the rows the [export](../feature-tour/exporting-data.md) feature writes — useful if you assemble your own files.

```php
// @since 3.16.0 — all three
$summary = $statistics->buildSummaryExportRows($form, $dateRange = 'all', $siteId = 'all');
$raw     = $statistics->buildRawResponsesExportRows($form, $dateRange = 'all', $siteId = 'all'); // honors maxExportRows
$byGroup = $statistics->buildGroupedExportRows($form, $dateRange = 'all', $groupByHandle = null, $siteId = 'all');
```

`buildGroupedExportRows()` emits every group represented by at least one Rating field. Its `Submissions Count` is field-independent and counts all valid submissions in that emitted group; metrics stay blank for Rating fields without a response in that group.

## Cache control

```php
$statistics->clearCacheForForm($formId); // clear one form's cached stats
$statistics->clearAllCache();            // clear everything
$count = $statistics->getCacheFileCount(); // file cache only
```

See [Caching](../feature-tour/caching.md) for how the cache behaves.

> [!NOTE]
> The service is registered as the plugin's `statistics` component (`@since 3.3.0`). Method signatures here are verified against the current source; methods marked `@since 3.16.0` were added after the service.
