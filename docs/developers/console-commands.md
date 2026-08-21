# Console commands

Formie Rating Field ships console commands for inspecting and managing the [statistics cache](../feature-tour/caching.md) — handy for cron jobs, deploys, or debugging — plus a help command. Each example includes DDEV and direct PHP variants.

## `formie-rating-field/help`

Lists the available commands with examples and notes.

```bash title="DDEV"
ddev craft formie-rating-field/help
```

```bash title="PHP"
php craft formie-rating-field/help
```

Pass a command path for focused notes:

```bash title="DDEV"
ddev craft formie-rating-field/help [command]
```

```bash title="PHP"
php craft formie-rating-field/help [command]
```

For example, use `cache/generate`. Craft's native `help formie-rating-field/cache/generate` command also shows the action signature.

## `cache/info`

Print the configured storage preference, the effective runtime status and explanation, and the effective generation schedule. When the plugin is effectively using its own file cache, the output also includes the file path and entry count. Craft application-cache entries are not enumerated as plugin files.

```bash title="DDEV"
ddev craft formie-rating-field/cache/info
```

```bash title="PHP"
php craft formie-rating-field/cache/info
```

## `cache/generate`

Queue a job that rebuilds the statistics cache. With no argument it rebuilds every form with rating fields; pass `--form-id` to rebuild just one form.

```bash title="DDEV"
ddev craft formie-rating-field/cache/generate
```

```bash title="PHP"
php craft formie-rating-field/cache/generate
```

To target one form:

```bash title="DDEV"
ddev craft formie-rating-field/cache/generate --form-id=34
```

```bash title="PHP"
php craft formie-rating-field/cache/generate --form-id=34
```

| Option | Type | Description |
|--------|------|-------------|
| `--form-id` | `int` | Optional. Rebuild the cache for a single form only. |

> [!NOTE]
> The actual work runs through Craft's queue, so make sure your queue runner is active to see the cache populate.

## `cache/clear-form`

Clear the cached statistics for one form. The next dashboard view recomputes them.

```bash title="DDEV"
ddev craft formie-rating-field/cache/clear-form 34
```

```bash title="PHP"
php craft formie-rating-field/cache/clear-form 34
```

| Argument | Type | Description |
|----------|------|-------------|
| `formId` | `int` | Required. The form's ID. |

## `cache/clear`

Invalidate **all** cached statistics for every form. This clears the plugin's statistics-cache family without flushing unrelated Craft application-cache data.

```bash title="DDEV"
ddev craft formie-rating-field/cache/clear
```

```bash title="PHP"
php craft formie-rating-field/cache/clear
```

## See also

- [Caching](../feature-tour/caching.md) — when and why to use these
- [Configuration](../get-started/configuration.md#cache-cache-tab) — the cache settings these act on
