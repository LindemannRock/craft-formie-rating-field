# Caching

Computing averages, NPS scores, and distributions across thousands of submissions is work you don't want to redo on every page load — so the plugin caches the results. The dashboard reads the cache; the cache refreshes itself when submissions change; and you can pre-build it on a schedule for big forms.

## What you'll use it for

- Keeping the Statistics dashboard fast on high-volume forms
- Pre-generating stats overnight so the first morning view is instant
- Using Craft's application cache across load-balanced or ephemeral hosts
- Clearing stale numbers by hand when you need to

## How it stays current

You rarely have to think about this. When a submission is **saved or deleted**, the plugin clears the cached statistics **for that form only** — so the next dashboard load recomputes fresh numbers, and other forms' caches are untouched. Between recomputes, the dashboard serves the cached result. Field-statistics and trend payloads receive a UTC ISO 8601 generation timestamp before they are saved, and cache hits preserve that original time. When scheduled generation is enabled, **Last updated** comes from the cached field-statistics payload's actual generation time; legacy payloads without that metadata omit the label rather than showing the page-load time.

## Where the cache lives

Set your storage preference in **Settings → Formie Rating → Cache** under **Cache Storage Method**. The status shown below the field is the effective storage for the current host, which can differ from the saved preference:

| Preference | Effective behavior |
|------------|--------------------|
| **File cache** (default) | On a durable host, statistics use plugin-owned runtime files. On an ephemeral host, those files are bypassed automatically and the plugin tries Craft's application cache instead. |
| **Application cache** | Statistics use Craft's configured application cache when it is suitable for reuse across requests. This does not require Redis when Craft already provides another suitable backend. |

The Cache settings page, **Utilities → Formie Rating**, and `cache/info` describe the effective result without guessing through managed cache layers:

| Status | What it means |
|--------|---------------|
| **Managed cache**, **Redis cache**, or **Database cache** | Craft exposed a suitable application-cache backend. Statistics caching is active. |
| **Filesystem cache** | Craft's application cache is filesystem-backed and is suitable for the current host. It is still application-cache storage, not the plugin's file store. |
| **Application cache — Best effort** | The backend is available, but cross-request persistence could not be confirmed. |
| **Caching disabled — Recomputed as needed** | The application cache is unavailable or unsuitable. Statistics continue to work and are recomputed when requested. |

> [!NOTE]
> An ephemeral host never writes statistics to the plugin's runtime cache directory. If its application cache is also unsuitable, caching is disabled for that host and requests recompute safely.

![The Cache settings tab](../images/caching-settings.webp)

## Pre-generating on a schedule

By default the cache is built **on demand** — the first view of a form's stats after a change does the computing. On large forms that first view can be slow. Set a **Cache Generation Schedule** to pre-build the cache in the background instead:

`disabled`, `every3hours`, `every6hours`, `every12hours`, `daily`, `daily2am`, `weekly`.

`daily2am` or `every6hours` are good production choices. A scheduled run queues a job that walks every form's rating fields across common date ranges and groupings and warms the cache, then reschedules itself for the next run.

Craft stores queue job descriptions when rows are queued, so date/time format changes apply to newly queued rows. Existing delayed rows keep their old label until they run or are requeued. Queue labels stay compact: numeric months render numerically, while short and long month settings both render as short month names.

> [!NOTE]
> Scheduled generation pre-warms the **cross-site aggregate** (all sites). Per-site views compute live on their first load and are cached from then on.

## Manual control

### Utilities page

**Utilities → Formie Rating** shows the effective cache status and gives you two buttons (with the *Manage cache* permission). When effective storage is the plugin's file cache, the card includes its entry count. Application-cache entries are scoped and invalidated through Craft; they are not enumerated as plugin files.

- **Generate Cache Now** — queue a full rebuild
- **Clear All Cache** — invalidate every Formie Rating statistics entry without flushing unrelated Craft cache data

### Console commands

For automation or cron, the same actions exist on the command line: inspect the effective storage with `formie-rating-field/cache/info`, queue a rebuild with `cache/generate`, invalidate one form with `cache/clear-form`, or invalidate the whole statistics-cache family with `cache/clear`. See [Console commands](../developers/console-commands.md) for copy-ready PHP and DDEV examples.

## Next steps

- [Statistics](statistics.md) — what the cache powers
- [Configuration](../get-started/configuration.md#cache-cache-tab) — the cache settings reference
- [Console commands](../developers/console-commands.md)
