# Shared features

Formie Rating Field builds on [LindemannRock Plugin Base](https://github.com/LindemannRock/craft-plugin-base) for behavior shared across LindemannRock plugins. This keeps familiar Control Panel screens, settings, cache reporting, exports, and queue scheduling consistent. The Base package is installed automatically through Composer.

## Settings and navigation

| Shared feature | How Formie Rating Field uses it |
|----------------|---------------------------------|
| `PluginHelper` | Boots the Base module and registers the [`ratingHelper`](twig-globals.md) Twig global. |
| `CpNavHelper` | Builds the Statistics and Settings subnavigation while respecting the current user's access. |
| Settings traits | Provide plugin-name, date/time, date-range, export-format, items-per-page, config-override, and display-name behavior. |
| `SettingsPostHelper` | Applies allowed Control Panel settings without accepting unrelated request properties. |

Control Panel settings use Craft's native plugin-settings storage. Values in `config/formie-rating-field.php` override those selections and lock the matching fields; see [Configuration](../get-started/configuration.md).

## Statistics, caching, and scheduling

| Shared feature | How Formie Rating Field uses it |
|----------------|---------------------------------|
| `DateRangeHelper`, `DateFormatHelper`, and `DbHelper` | Keep date filtering, display formatting, and database-specific statistics queries consistent across supported databases. |
| `ExportHelper` | Produces the Excel, CSV, and JSON downloads described in [Exporting data](../feature-tour/exporting-data.md). |
| Disposable cache storage | Resolves a configured file/application-cache preference against the current host and presents its effective backend consistently. |
| `ScopedCache` | Isolates application-cache entries by plugin, statistics family, and form so targeted clearing does not flush unrelated Craft data. |
| `ScheduleHelper` and `RecurringQueueHelper` | Calculate cache-generation cadences and maintain one owned recurring queue chain. |
| `QueueTtrTrait` | Gives cache-generation jobs the shared queue timeout behavior. |

For the practical effects of host detection, safe recomputation, invalidation, and pre-generation, see [Caching](../feature-tour/caching.md).

## Control Panel building blocks

The statistics listings use Base's table layout and export menu. The cache utility uses its utilities layout, action sections, Ajax buttons, and unified cards. The Craft dashboard widget uses the shared widget-list and empty-state components, and the console help command extends Base's standard help controller.

These are presentation and infrastructure dependencies; they do not add a separate public API to the Rating field itself. For supported integration points, continue with the [Statistics service](statistics-service.md), [Twig globals](twig-globals.md), and [Front-end CSS](front-end-css.md).
