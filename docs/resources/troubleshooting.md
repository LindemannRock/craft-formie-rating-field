# Troubleshooting

Common issues and how to resolve them. If something here doesn't cover your case, [open an issue](https://github.com/LindemannRock/craft-formie-rating-field/issues).

## The Rating field isn't in Formie's field list

**Quick checks:**

1. Is Formie installed **and enabled** in **Settings → Plugins**?
2. Is Formie Rating Field itself enabled?
3. Hard-refresh the form builder page.

**Fix:** The Rating field type registers into Formie, so Formie must be active first. Install/enable both under **Settings → Plugins**.

**Why:** The plugin adds its field via Formie's field-registration event — with Formie disabled, there's nothing to register into.

## Emoji look plain or different across devices

**Quick checks:**

1. Open the field's **General** tab and check **Emoji Render Mode**.
2. Note that **System Emojis** look different on iOS, Android, Windows, etc. — each platform draws its own.

**Fix:** For a consistent look everywhere, switch the field (or the [default](../get-started/configuration.md#field-defaults-general-tab)) to **Noto Color Emoji** or **Noto Emoji**.

**Why:** *System Emojis* uses the visitor's native platform font, so the same rating renders differently per device. The Noto modes load a single font for everyone.

> [!IMPORTANT]
> The two Noto modes load fonts from the Google Fonts CDN on every form render, which contacts Google's servers — in EU jurisdictions this may require visitor consent. Use *System Emojis* to stay fully local.

## Statistics look out of date

**Quick checks:**

1. New submissions should clear that form's cache automatically — reload the page.
2. On a form's statistics page, click **Refresh** (needs the *Refresh statistics* permission).
3. Run `ddev craft formie-rating-field/cache/clear-form <formId>` or clear all from **Utilities → Formie Rating**.

**Fix:** Refresh or clear the cache; the next view recomputes from current submissions.

**Why:** Stats are cached for speed and invalidated when submissions change. A manual refresh forces a recompute if a cache entry is stale for any other reason.

## The statistics index redirects to the dashboard

**Quick checks:**

1. Check the Formie Rating Field logs for the complete underlying error.
2. Confirm the database is reachable and Formie's forms and submissions can be loaded normally.
3. Retry the statistics page after resolving the logged service or database failure.

**Fix:** Resolve the exception recorded in the logs, then reopen **Formie Rating Field → Statistics**. In development mode, the Control Panel flash also includes the original exception message.

**Why:** Request and access checks still return their normal HTTP responses. Once those checks pass, an unexpected loading or rendering failure is isolated so it cannot replace the entire Control Panel with a generic error page.

## A form is missing from statistics or a direct URL returns 403

**Quick checks:**

1. Confirm the user has Formie Rating Field's **View statistics** permission.
2. In the same user group, confirm Formie grants either **View all submissions** or **View submissions** for that specific form.
3. For exports or manual refreshes, also confirm **Export statistics** or **Refresh statistics** respectively.

**Fix:** Grant both the relevant Formie Rating Field permission and Formie's global or matching per-form submission permission.

**Why:** Formie Rating permissions add capabilities but do not bypass Formie's submission ACL. The index and dashboard widget hide forms the user cannot access; form-specific statistics, chart-data, export, group-detail, and refresh requests return HTTP 403.

## Scheduled cache generation appears more than once

Formie Rating Field keeps one recurring scheduled cache-generation master job in Craft's queue. Manual cache-generation jobs and per-batch jobs can appear separately while a cache rebuild is running.

During bootstrap, the plugin collapses duplicate pending scheduled-master rows automatically, including older pre-release scheduled rows that do not carry the current `scheduledMaster` marker. If duplicates keep returning after a deployment, confirm all web workers are running the same plugin version and that old queue workers have been restarted.

Craft stores queue job descriptions when rows are queued, so date/time format changes apply to newly queued rows. Existing delayed rows keep their old label until they run or are requeued. Queue labels stay compact: numeric months render numerically, while short and long month settings both render as short month names.

## "Redis Not Configured" on the Cache settings page

**Quick checks:**

1. Have you set Craft's cache component to `yii\redis\Cache`?
2. Confirm Redis is actually reachable from your environment.

**Fix:** Configure Craft to use Redis (its cache component), or set **Cache Storage Method** back to **File System**.

**Why:** The plugin's Redis mode reuses *Craft's* configured Redis cache rather than connecting on its own. If Craft isn't using Redis, the plugin warns you and falls back to recomputing on demand instead of caching incorrectly.

## A Raw Responses export is missing rows

**Quick checks:**

1. Check **Settings → Interface → Max Export Rows** (default `50,000`).
2. Look in the logs for a truncation warning.

**Fix:** Raise **Max Export Rows** (or set `0` for unlimited — only if your PHP `memory_limit` is generous).

**Why:** Raw Responses hydrate a full submission per row, which is memory-heavy. The cap protects against out-of-memory errors on high-volume forms; when hit, the export is truncated and a warning is logged.

## A single-group export is missing rows

**Quick checks:**

1. Check **Settings → Interface → Max Export Rows** (default `50,000`).
2. Look in the logs for a group-export cap warning.
3. Confirm the export page still shows the expected group, date range, and site.

**Fix:** Raise **Max Export Rows**, narrow the date range, or set the limit to `0` when the server has enough memory.

**Why:** The cap is applied after the group, date-range, and site filters, so submissions from other groups do not consume it. A group with more matching submissions than the configured limit is still truncated to protect the server from an out-of-memory failure.

## A group does not appear in the grouped dashboard search

**Quick checks:**

1. Compare the **Showing _n_ of _total_ groups** line below the table.
2. If the total is greater than the shown count, remember that the search box filters only the displayed rows.
3. Use **By Group** export when you need the complete group list.

**Fix:** Export the full grouped result, or narrow the date/site filters so the group moves into the 100 highest-volume rows shown on the dashboard.

**Why:** The interactive overview is deliberately bounded to 100 groups to prevent high-cardinality text or hidden fields from exhausting PHP memory or producing an impractically large page. The complete count remains visible, and grouped exports are not limited to those overview rows.

## A By Group export is missing a group or its count looks too low

**Quick checks:**

1. Update Formie Rating Field to the current version, then generate the export again.
2. Confirm at least one Rating field has a response in the missing group; groups with no ratings anywhere are intentionally omitted.
3. Confirm the active date-range and site filters include the submissions you expect.

**Fix:** Upgrade, keep the intended date and site filters selected, and rerun the **By Group** export.

**Why:** By Group rows come from the union of groups represented by every Rating field. **Submissions Count** is independent of any one Rating field and includes all valid submissions in that included group, even when some or all Rating fields are blank on an individual submission. Spam, incomplete, and submissions outside the selected date range or site remain excluded.

## The Google Review button doesn't appear

**Quick checks:**

1. Is the submitted rating **at or above the threshold**? Only high ratings show the button. A blank threshold is automatic: `5` for a 1–5 star/emoji scale and `9` for NPS.
2. Is **Google Place ID Field Handle** set to a real field handle on the form, and does that field have a value?
3. Is the prompt enabled on **only one** Rating field on the form?

**Fix:** Leave **Rating Threshold** blank for the scale-aware automatic value, or enter an explicit value within the field's current range. Confirm the rating meets that threshold and the Place ID field handle is correct and populated. Enable the prompt on a single field per form.

**Why:** The button shows only for the high tier and only when a Place ID is present to build the review URL. Multiple enabled fields compete to override the success message. See [Google Review prompt](../feature-tour/google-review-prompt.md).

## I can't change the min/max on an NPS field

**Fix:** This is intentional — NPS is always 0–10, so the Minimum/Maximum options are hidden for the NPS type.

**Why:** Net Promoter Score is only meaningful on the standard 0–10 scale. For a custom range, use the star or emoji type.

## "Allow Half Ratings" has no effect

**Fix:** Half ratings apply to the **star** type only. Switch the field to Star Rating.

**Why:** Half values only make sense for stars; emoji and NPS are whole-number scales.
