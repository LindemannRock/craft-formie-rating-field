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

## The Google Review button doesn't appear

**Quick checks:**

1. Is the submitted rating **at or above the threshold**? Only high ratings show the button.
2. Is **Google Place ID Field Handle** set to a real field handle on the form, and does that field have a value?
3. Is the prompt enabled on **only one** Rating field on the form?

**Fix:** Confirm the rating meets the threshold and the Place ID field handle is correct and populated. Enable the prompt on a single field per form.

**Why:** The button shows only for the high tier and only when a Place ID is present to build the review URL. Multiple enabled fields compete to override the success message. See [Google Review prompt](../feature-tour/google-review-prompt.md).

## I can't change the min/max on an NPS field

**Fix:** This is intentional — NPS is always 0–10, so the Minimum/Maximum options are hidden for the NPS type.

**Why:** Net Promoter Score is only meaningful on the standard 0–10 scale. For a custom range, use the star or emoji type.

## "Allow Half Ratings" has no effect

**Fix:** Half ratings apply to the **star** type only. Switch the field to Star Rating.

**Why:** Half values only make sense for stars; emoji and NPS are whole-number scales.
