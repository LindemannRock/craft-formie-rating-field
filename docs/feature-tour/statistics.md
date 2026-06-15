# Statistics

See what your ratings actually say. The Statistics dashboard reads the submissions Formie already stores and turns them into the right numbers for each rating type — an average for stars and emoji, a proper NPS score for NPS — with distributions, trends, and the ability to slice by another form field, date range, or site.

> [!NOTE]
> Statistics are **read-only**. The dashboard never changes submissions; it computes from them and caches the result.

## What you'll use it for

- Tracking a form's average rating or NPS score over time
- Seeing the full distribution — how many 1s, 3s, 5s
- Comparing ratings by product, branch, or category
- Reviewing the individual submissions behind one group
- Pulling per-site numbers on a multi-site install

## Find your forms

Open **Formie Rating → Statistics**. The list shows every form that contains at least one Rating field:

| Column | Meaning |
|--------|---------|
| **Form Title** | Links to that form's statistics |
| **Handle** | The form handle |
| **Rating Fields** | How many Rating fields the form has |
| **Total Submissions** | Live submissions (excludes spam, incomplete, drafts) |

Search by title or handle, sort any column, and — on a multi-site install — filter by site.

## A form's statistics

Click a form to open its dashboard. If the form has more than one Rating field, each gets its own tab.

![A form's statistics page with the metric cards and charts](images/statistics-form.webp)

**Star and emoji fields** show:

- **Average Rating**, **Median**, **Most Common**, and total **Responses**
- A **distribution** bar chart — count per value
- A **trend** line chart — average over time

**NPS fields** show:

- **NPS Score** (−100 to 100), with **Promoters %**, **Passives %**, **Detractors %**, and **Responses**
- A promoter/passive/detractor **doughnut** and the 0–10 distribution
- A **trend** of the NPS score over time

NPS uses the standard bands: **promoters** score 9–10, **passives** 7–8, **detractors** 0–6, and the score is `(% promoters − % detractors)`.

### Filter the view

Across the top of a form's page:

- **Date Range** — today, last 7/30/90 days, this month, this year, all time, and more
- **Site** — per-site or aggregated across all sites (multi-site only; defaults to all sites)
- **Field** — when the form has more than one Rating field
- **Group By** — break the numbers down by another field (see below)

## Group by another field

Pick a **Group By** field to split the ratings by something meaningful — a product code, a category, a branch. Groupable fields include plain text, hidden, dropdown, radio, Entries, and Categories fields on the same form.

The grouped view adds summary cards (total groups, overall average or NPS, top performer, needs attention) and a sortable, searchable table — one row per group value, each with its own count, score, distribution, and a **reliability** marker (groups with fewer than five responses are flagged as low-data). Click a row to drill into the individual submissions behind that group.

![The grouped statistics view with per-group rows](images/statistics-grouped.webp)

## Keeping numbers current

The dashboard reads from a cache so it stays fast. New, edited, or deleted submissions invalidate that form's cached statistics automatically, so the numbers refresh on the next load. If you ever need to force it, the **Refresh** button on a form's page clears its cache on demand (requires the *Refresh statistics* permission). See [Caching](caching.md) for the full picture.

## Permissions

| To… | You need |
|-----|----------|
| Open the dashboard | `View statistics` |
| Use **Refresh** | `Refresh statistics` |
| Use **Export** | `Export statistics` |

See [Permissions](../developers/permissions.md).

## Next steps

- [Exporting data](exporting-data.md) — download summaries, raw responses, or by-group breakdowns
- [Caching](caching.md) — how the cache is built and pre-generated
