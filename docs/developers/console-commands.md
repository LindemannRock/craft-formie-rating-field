# Console commands

Formie Rating Field ships console commands for inspecting and managing the [statistics cache](../feature-tour/caching.md) — handy for cron jobs, deploys, or debugging — plus a help command. Examples use `ddev craft`; drop the `ddev` prefix for a non-DDEV environment (`php craft …`).

## `formie-rating-field/help`

Lists the available commands with examples and notes.

```bash title="DDEV"
ddev craft formie-rating-field/help
```

For one command: `ddev craft formie-rating-field/help cache/generate`. Craft's native signature help also works: `ddev craft help formie-rating-field/cache/generate`.

## `cache/info`

Print the cache location, the number of cached files, and the effective generation schedule. A safe, read-only first stop when stats look stale.

```bash title="DDEV"
ddev craft formie-rating-field/cache/info
```

## `cache/generate`

Queue a job that rebuilds the statistics cache. With no argument it rebuilds every form with rating fields; pass `--form-id` to rebuild just one form.

```bash title="DDEV"
ddev craft formie-rating-field/cache/generate
ddev craft formie-rating-field/cache/generate --form-id=34
```

| Option | Type | Description |
|--------|------|-------------|
| `--form-id` | `int` | Optional. Rebuild the cache for a single form only. |

> The actual work runs through Craft's queue, so make sure your queue is running (or run `ddev craft queue/run`) to see the cache populate.

## `cache/clear-form`

Clear the cached statistics for one form. The next dashboard view recomputes them.

```bash title="DDEV"
ddev craft formie-rating-field/cache/clear-form 34
```

| Argument | Type | Description |
|----------|------|-------------|
| `formId` | `int` | Required. The form's ID. |

## `cache/clear`

Clear **all** cached statistics for every form. Reports how many entries were removed.

```bash title="DDEV"
ddev craft formie-rating-field/cache/clear
```

## See also

- [Caching](../feature-tour/caching.md) — when and why to use these
- [Configuration](../get-started/configuration.md#cache-cache-tab) — the cache settings these act on
