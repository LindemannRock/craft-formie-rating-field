# Features overview

Ask people how they feel — with stars, emoji, or a 0–10 Net Promoter Score — and get the answers back as charts and exportable reports, all inside the form builder you already use. Formie Rating Field adds a **Rating** field type to [Formie](https://verbb.io/craft-plugins/formie) and a **Statistics** dashboard that turns submissions into averages, NPS scores, distributions, and trends.

> [!TIP]
> New to the plugin? Start with [Installation](../get-started/installation.md) and the [Quickstart](../get-started/quickstart.md), then come back here for the tour.

## What it does

There are two halves to the plugin. The **field** is what your visitors interact with: a star, emoji, or NPS rating that drops into any Formie form like a native field, with its own appearance and label options. The **statistics dashboard** is what you read afterwards: it reads the submissions Formie already stores and computes the right metric for each rating type — an average for stars and emoji, a proper NPS score for NPS — then groups, filters, charts, and exports them.

Nothing about your submission data changes. The plugin reads Formie's submissions and caches the computed numbers so the dashboard stays fast even on busy forms.

## What you'll use it for

- A star "rate your experience" question on a feedback or checkout form
- An emoji reaction scale that reads friendlier than numbers
- A proper NPS survey ("how likely are you to recommend us?") with promoter/passive/detractor breakdown
- Breaking ratings down by product, branch, or category to see what's working
- Exporting a summary or every raw response for a report or a spreadsheet
- Nudging happy customers toward a Google review the moment they submit

## Core capabilities

- **[The Rating field](rating-field.md)** — Star, emoji, and NPS types, each with size, range, half-ratings, endpoint labels, custom emoji labels, and a selected-value label. Set defaults once; override per field.

- **[Google Review prompt](google-review-prompt.md)** — When a visitor gives a high rating, swap the form's success message for a tiered thank-you and a button that links straight to your Google review page.

- **[Statistics](statistics.md)** — A per-form dashboard with type-aware metrics (average / median / most common, or NPS score with promoters, passives, detractors), value distribution, trend charts, grouping by another form field, date-range filtering, and per-site views.

- **[Exporting data](exporting-data.md)** — Download a summary, every raw response, or a by-group breakdown as Excel (multi-sheet), CSV (zipped per section), or JSON.

- **[Caching](caching.md)** — Computed statistics are cached to the file system or Redis, invalidated automatically when submissions change, and optionally pre-generated on a schedule.

- **[Feed Me import](../integrations/feed-me.md)** — Import Rating values from a feed when Feed Me is installed.

## Where things live

| You configure… | In… |
|----------------|-----|
| A form's rating question (type, size, labels, Google review) | The **Rating** field on each Formie form |
| Defaults for new rating fields | Settings → Formie Rating → **General** |
| List size, export row cap, date/export formats | Settings → Formie Rating → **Interface** |
| Cache store and generation schedule | Settings → Formie Rating → **Cache** |
| Charts, averages, NPS, exports | Formie Rating → **Statistics** |
| Cache status and manual actions | Utilities → Formie Rating |

![The Statistics dashboard listing forms with rating fields](images/overview-statistics.webp)

The plugin also provides an optional Craft dashboard widget that lists forms with rating fields by submission volume. Editors can choose all editable sites or a single site for the widget, then jump into busy rating forms without opening the plugin section first.

## Next steps

1. [Install the plugin](../get-started/installation.md)
2. [Add your first rating field](../get-started/quickstart.md)
3. [Tour every field option](rating-field.md)
