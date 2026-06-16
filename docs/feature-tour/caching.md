# Caching

Computing averages, NPS scores, and distributions across thousands of submissions is work you don't want to redo on every page load — so the plugin caches the results. The dashboard reads the cache; the cache refreshes itself when submissions change; and you can pre-build it on a schedule for big forms.

## What you'll use it for

- Keeping the Statistics dashboard fast on high-volume forms
- Pre-generating stats overnight so the first morning view is instant
- Sharing a cache across load-balanced servers with Redis
- Clearing stale numbers by hand when you need to

## How it stays current

You rarely have to think about this. When a submission is **saved or deleted**, the plugin clears the cached statistics **for that form only** — so the next dashboard load recomputes fresh numbers, and other forms' caches are untouched. Between recomputes, the dashboard serves the cached result.

## Where the cache lives

Set the store in **Settings → Formie Rating → Cache** under **Cache Storage Method**:

| Method | When to use |
|--------|-------------|
| **File System** (default) | Single-server setups. Needs nothing extra. |
| **Redis** | Load-balanced or multi-server hosting (Servd, AWS, Platform.sh). Uses Craft's configured Redis cache. |

> [!NOTE]
> Redis mode uses **Craft's existing Redis cache component**. If you select Redis but Craft isn't configured to use it, the Cache settings page shows a warning and the plugin falls back to recomputing on demand rather than caching incorrectly. The cache page links to setup instructions.

![The Cache settings tab](images/caching-settings.webp)

## Pre-generating on a schedule

By default the cache is built **on demand** — the first view of a form's stats after a change does the computing. On large forms that first view can be slow. Set a **Cache Generation Schedule** to pre-build the cache in the background instead:

`disabled`, `every3hours`, `every6hours`, `every12hours`, `daily`, `daily2am`, `weekly`.

`daily2am` or `every6hours` are good production choices. A scheduled run queues a job that walks every form's rating fields across common date ranges and groupings and warms the cache, then reschedules itself for the next run.

Craft stores queue job descriptions when rows are queued, so date/time format changes apply to newly queued rows. Existing delayed rows keep their old label until they run or are requeued. Queue labels stay compact: numeric months render numerically, while short and long month settings both render as short month names.

> [!NOTE]
> Scheduled generation pre-warms the **cross-site aggregate** (all sites). Per-site views compute live on their first load and are cached from then on.

## Manual control

### Utilities page

**Utilities → Formie Rating** shows the current cache status (file count, or *Active* for Redis) and gives you two buttons (with the *Manage cache* permission):

- **Generate Cache Now** — queue a full rebuild
- **Clear All Cache** — drop every cached statistic

### Console commands

For automation or cron, the same actions exist on the command line — see [Console commands](../developers/console-commands.md):

```bash
ddev craft formie-rating-field/cache/info          # path, file count, schedule
ddev craft formie-rating-field/cache/generate      # rebuild all (queues a job)
ddev craft formie-rating-field/cache/clear-form 34  # clear one form
ddev craft formie-rating-field/cache/clear         # clear everything
```

## Next steps

- [Statistics](statistics.md) — what the cache powers
- [Configuration](../get-started/configuration.md#cache-cache-tab) — the cache settings reference
- [Console commands](../developers/console-commands.md)
