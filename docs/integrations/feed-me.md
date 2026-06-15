# Feed Me

Importing existing submissions — a migration from another system, a backfill of historical ratings — is easier when you can map a column straight onto a Rating field. When [Feed Me](https://github.com/craftcms/feed-me) is installed, the plugin registers Rating as a mappable field type so it shows up in Feed Me's field mapping like any other.

> [!NOTE]
> This integration only appears when the Feed Me plugin is installed and enabled. Feed Me is an optional, free Craft plugin — see [Requirements](../get-started/requirements.md).

## What you'll use it for

- Migrating ratings from another forms or survey tool
- Backfilling historical submissions so the [Statistics](../feature-tour/statistics.md) dashboard reflects them
- Bulk-loading test data

## How it maps

A Rating field stores a single number, so Feed Me imports it exactly like a numeric field — map your feed's column (a value like `4`, `4.5`, or an NPS `9`) onto the Rating field and Feed Me writes the number. The value is then read by the statistics dashboard the same way a live submission would be.

Set up the import in **Utilities → Feed Me** as you would for any Formie submission feed; the Rating field appears in the field-mapping step.

## Next steps

- [Statistics](../feature-tour/statistics.md) — imported ratings flow straight into the dashboard
- [Feed Me documentation](https://docs.craftcms.com/feed-me/) — full import setup
