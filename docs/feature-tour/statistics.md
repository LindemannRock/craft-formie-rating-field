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

The list follows Formie's submission access controls. **View statistics** is required to open the section, and each form appears only when the user also has Formie's global **View all submissions** permission or **View submissions** permission for that form. Forms the user cannot access are removed before search, sorting, and pagination, so their names, handles, counts, and links are not exposed.

## A form's statistics

Click a form to open its dashboard. If the form has more than one Rating field, each gets its own tab.

![A form's statistics page with the metric cards and charts](../images/statistics-form.webp)

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
- **Site** — one editable site or **All Sites**, which aggregates only the sites the signed-in user can currently edit (multi-site only; defaults to all editable sites)
- **Field** — when the form has more than one Rating field
- **Group By** — break the numbers down by another field (see below)

The editable-site boundary applies consistently to the form list, cards, charts, grouped rows and drill-downs, raw responses, and exports. A direct request for a site the user cannot edit is rejected; if the user has no editable sites, **All Sites** returns an empty result instead of widening access.

## Group by another field

Pick a **Group By** field to split the ratings by something meaningful — a product code, a category, a branch. Groupable fields include plain text, hidden, dropdown, radio, Entries, and Categories fields on the same form.

The grouped view adds summary cards (total groups, average or NPS across the shown groups, top performer, needs attention) and a sortable table. To keep high-cardinality text fields responsive, the dashboard loads the 100 highest-volume groups while retaining the complete group count; the search box searches those shown rows. Each row has its own count, score, distribution, and a **reliability** marker (groups with fewer than five responses are flagged as low-data).

Click a row to drill into the individual submissions behind that group. The drill-down is paginated newest-first; its page size uses **Settings → Interface → Items Per Page** and keeps the active date, group, field, and site filters while you move between pages. A **By Group** export still includes the complete grouped result rather than only the dashboard overview.

![The grouped statistics view with per-group rows](../images/statistics-grouped.webp)

## On the Craft dashboard

Prefer to keep an eye on ratings without opening the plugin section? Add the **Formie Rating - Statistics** widget to your Craft **Dashboard** (**Dashboard → New Widget**). It lists the forms that have Rating fields, ranked by total submissions, so the busiest forms rise to the top — and each row links straight into that form's statistics.

![The Rating Statistics dashboard widget listing forms by submission volume](../images/statistics-widget.webp)

Two settings control it:

- **Number of forms** — show the top 3, 5, 10, 15, or 20 forms (default 5)
- **Site** — all editable sites, or a single site (multi-site installs)

The footer's **View all statistics** link opens the full dashboard. The widget needs the **View statistics** permission: it's hidden in the widget picker for users without it, and shows an empty state if the permission is ever removed. It also applies Formie's global/per-form submission access to every row, so a user with access to only some forms sees only those forms in the widget. Site access is checked again whenever the widget renders; an unavailable saved selection (including a deleted site, malformed old value, or no editable sites) shows a site-selection/access message instead of masquerading as no rating data or falling back to another scope.

## Keeping numbers current

The dashboard reads from a cache so it stays fast. New, edited, or deleted submissions invalidate that form's cached statistics automatically, so the numbers refresh on the next load. If you ever need to force it, the **Refresh** button on a form's page clears its cache on demand (requires the *Refresh statistics* permission and Formie submission access to that form). See [Caching](caching.md) for the full picture.

## Permissions

| To… | You need |
|-----|----------|
| Open the dashboard | `View statistics` plus Formie's global or matching per-form submission permission |
| Use **Refresh** | `Refresh statistics` plus Formie submission access to that form |
| Use **Export** | `Export statistics` plus Formie submission access to that form |

The index and dashboard widget hide forms the user cannot access. A direct form-specific URL returns HTTP 403 when the Formie permission layer fails. See [Permissions](../developers/permissions.md).

## Next steps

- [Exporting data](exporting-data.md) — download summaries, raw responses, or by-group breakdowns
- [Caching](caching.md) — how the cache is built and pre-generated
