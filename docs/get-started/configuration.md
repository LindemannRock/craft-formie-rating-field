# Configuration

Every setting on this page has a home in the Control Panel under **Formie Rating → Settings**, split across three tabs — **General**, **Interface**, and **Cache**. You only need a config file when you want to lock values per environment (dev vs. production) or keep them in version control.

The field defaults below answer one question: *what should a brand-new Rating field look like before anyone touches it?* They don't change fields that already exist — each Rating field stores its own copy of these settings once created.

> Copy the sample config to start: `cp vendor/lindemannrock/craft-formie-rating-field/src/config.php config/formie-rating-field.php`. Anything set in `config/formie-rating-field.php` overrides the Control Panel value and locks that field in the UI.

## Field defaults (General tab)

Defaults applied to each new Rating field.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `defaultRatingType` | `string` | `'star'` | Rating type for new fields: `star`, `emoji`, or `nps`. |
| `defaultRatingSize` | `string` | `'medium'` | Element size: `small`, `medium`, `large`, `xlarge`. |
| `defaultMinRating` | `int` | `1` | Minimum value: `0` or `1`. (NPS is always 0–10.) |
| `defaultMaxRating` | `int` | `5` | Maximum value: `3`–`10`. (NPS is always 0–10.) |
| `defaultAllowHalfRatings` | `bool` | `false` | Allow half-star selections — star type only. |
| `defaultSingleEmojiSelection` | `bool` | `false` | Highlight only the chosen emoji instead of cumulative — emoji type only. |
| `defaultEmojiRenderMode` | `string` | `'system'` | How emoji render: `system`, `noto-color`, `noto-simple`. See the note below. |
| `defaultShowSelectedLabel` | `bool` | `false` | Show the selected value as a text label. |
| `defaultShowEndpointLabels` | `bool` | `false` | Show descriptive labels at the ends of the scale. |
| `defaultStartLabel` | `string` | `''` | Text for the low end (e.g. `Poor`). Used when endpoint labels are on. |
| `defaultEndLabel` | `string` | `''` | Text for the high end (e.g. `Excellent`). Used when endpoint labels are on. |
| `pluginName` | `string` | `'Formie Rating'` | Name shown in the Control Panel menu. Usually set in the UI, not config. |

> [!NOTE]
> **Emoji render modes and GDPR.** `system` uses the visitor's native platform emoji and stays fully local. The two `noto-*` modes load fonts from the Google Fonts CDN on every form render, which contacts Google's servers — in EU jurisdictions this may require visitor consent. The deprecated value `webfont` maps to `noto-color` for backward compatibility.

## Interface (Interface tab)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `itemsPerPage` | `int` | `100` | Rows per page on the Statistics list. Range 10–500. |
| `maxExportRows` | `int` | `50000` | Hard cap on rows in a **Raw Responses** export. Range 0–1,000,000; `0` = unlimited. Protects against PHP out-of-memory on high-volume forms — each row hydrates a full submission element. Truncation is logged. |

## Cache (Cache tab)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `cacheStorageMethod` | `string` | `'file'` | Where computed statistics are cached: `file` (single server) or `redis` (load-balanced / multi-server). |
| `cacheGenerationSchedule` | `string` | `'disabled'` | Pre-generate statistics on a schedule: `disabled`, `every3hours`, `every6hours`, `every12hours`, `daily`, `daily2am`, `weekly`. |

See [Caching](../feature-tour/caching.md) for how the cache is built, invalidated, and pre-warmed.

## Shared base settings

These cascade from the shared base plugin. Set them in **Settings → Interface**, or override per-plugin in config. When the UI value is *"Use global default"*, the value comes from `config/lindemannrock-base.php`.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `defaultDateRange` | `string` | `'last30days'` | Default range on the Statistics page. Cascades: plugin config → plugin UI value → base config → `last30days`. |
| `exports` | `array` | all enabled | Toggle export formats: `['csv' => true, 'json' => true, 'excel' => true]`. |
| `timeFormat` | `string` | base | `'12'` (AM/PM) or `'24'`. |
| `monthFormat` | `string` | base | `'numeric'`, `'short'`, or `'long'`. |
| `dateOrder` | `string` | base | `'dmy'`, `'mdy'`, or `'ymd'`. |
| `dateSeparator` | `string` | base | `'/'`, `'-'`, or `'.'`. |
| `showSeconds` | `bool` | base | Show seconds in time display. |

## Example: full config file

`config/formie-rating-field.php` is multi-environment aware, exactly like Craft's `general.php`:

```php
<?php

return [
    // Applies to every environment
    '*' => [
        // Field defaults for new Rating fields
        'defaultRatingType' => 'star',
        'defaultEmojiRenderMode' => 'system',
        'defaultRatingSize' => 'medium',
        'defaultMinRating' => 1,
        'defaultMaxRating' => 5,
        'defaultAllowHalfRatings' => false,
        'defaultSingleEmojiSelection' => false,
        'defaultShowSelectedLabel' => false,
        'defaultShowEndpointLabels' => false,
        'defaultStartLabel' => '',
        'defaultEndLabel' => '',

        // Interface
        'itemsPerPage' => 100,
        'maxExportRows' => 50000,

        // Cache
        'cacheStorageMethod' => 'file',
        'cacheGenerationSchedule' => 'disabled',
    ],

    // Production: pre-generate stats overnight and use Redis on a load-balanced host
    'production' => [
        'cacheStorageMethod' => 'redis',
        'cacheGenerationSchedule' => 'daily2am',
    ],
];
```

## Environment variables

Any string value can be driven by an environment variable using Craft's `App::env()` syntax in your config:

```php
'cacheStorageMethod' => craft\helpers\App::env('RATING_CACHE_METHOD') ?: 'file',
```
