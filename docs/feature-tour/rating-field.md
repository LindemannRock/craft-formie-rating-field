# The Rating field

Add a rating question to any Formie form — stars, emoji, or a 0–10 NPS scale — and let the plugin handle the interactive widget, validation, and how the value shows up in submissions and emails. The Rating field behaves like a native Formie field: drag it on, set its options, and it's live.

## What you'll use it for

- A 1–5 star "rate your experience" question
- An emoji reaction scale that feels lighter than numbers
- A Net Promoter Score question with the standard 0–10 scale
- A satisfaction scale with worded ends ("Poor" → "Excellent")
- A single-emoji "pick the face that fits" question with a label under each face

## Add the field

1. In the Control Panel, open **Formie → Forms** and edit a form.
2. Drag **Rating** from the field list onto a page.
3. Give it a **Label** and pick a **Rating Type** in the field's **General** tab.
4. Tune the rest in the field's **Settings** tab (below), then save.

New fields start from the defaults you set in [Settings → General](../get-started/configuration.md#field-defaults-general-tab) — so if most of your forms use 5-star ratings, set that once and every new field inherits it.

![The Rating field settings inside the Formie form builder](images/rating-field-settings.webp)

## The three rating types

Choose the type in the field's **General** tab under **Rating Type**.

| Type | Looks like | Best for |
|------|-----------|----------|
| **Star** | ★ ★ ★ ☆ ☆ | Classic satisfaction / quality ratings |
| **Emoji** | 😢 😕 😐 😊 😍 | Friendly, expressive reactions |
| **NPS** | numbered 0–10 boxes | Net Promoter Score surveys |

![Star, emoji, and NPS rating types on a live form](images/rating-field-types.webp)

**NPS is always 0–10.** When you pick NPS, the minimum and maximum are fixed at 0 and 10 — the Minimum/Maximum options hide, because the score only means anything on the standard scale.

**Emoji sets scale to the range.** The emoji faces are chosen to fit the scale: a range of 5 or fewer uses five faces (😢 😕 😐 😊 😍), wider ranges add more expressive faces. You don't pick individual emoji — the set adapts to your min/max.

## Field options

All of these live in the field's **Settings** tab. Options appear only when they apply to the chosen type.

| Option | Type | What it does |
|--------|------|--------------|
| **Required** | all | Make the rating mandatory before the form submits. |
| **Error Message** | all | Custom message when a required rating is missing. |
| **Placeholder** | all | Placeholder text shown before a value is picked. (Set in the **General** tab.) |
| **Size** | all | Element size: Small, Medium, Large, Extra Large. |
| **Minimum Value** | star, emoji | Lowest value: `0` or `1`. (Hidden for NPS.) |
| **Maximum Value** | star, emoji | Highest value: `3`–`10`. (Hidden for NPS.) |
| **Allow Half Ratings** | star only | Let visitors pick half stars (e.g. 3.5). |
| **Show Selected Label** | all | Display the chosen value as a text label. |
| **Show Endpoint Labels** | all | Show **Start Label** / **End Label** text at the ends of the scale. |
| **Start Label** / **End Label** | all | Worded ends, e.g. *Poor* → *Excellent*. Shown when endpoint labels are on. |
| **Single Emoji Selection** | emoji only | Highlight only the selected emoji instead of cumulative selection. |
| **Custom Labels** | emoji + single selection | A value→label table; the label shows beneath the selected emoji. Available when Single Emoji Selection is on. |
| **Include in Email** | all | Include the rating in Formie notification emails. |

> [!NOTE]
> **Emoji Render Mode** (in the **General** tab, shown only for emoji) controls how the emoji are drawn: **System Emojis** (native, fully local), **Noto Color Emoji**, or **Noto Emoji**. The two Noto modes load fonts from the Google Fonts CDN at render time — see the GDPR note in [Configuration](../get-started/configuration.md#field-defaults-general-tab).

## How a rating shows up afterwards

- **In submissions (Control Panel):** stars render as gold/grey stars with `(value/max)`; emoji show the matching face with `(value/max)`; NPS shows the number in a coloured box.
- **In notification emails:** star → `value / max stars`; emoji → the custom label and value (or just the value); NPS → `value / max`. Unanswered ratings render as *Not rated*. Requires **Include in Email**.
- **In your own templates:** see [Displaying ratings](../template-guides/displaying-ratings.md).

## Defaults vs. per-field settings

The plugin's [Settings → General](../get-started/configuration.md) page sets the *starting point* for new fields — type, size, range, half-ratings, labels, emoji mode. Once a field is created it keeps its own copy of those values, so changing the defaults later doesn't rewrite existing fields. Override anything per field in the form builder.

## Next steps

- [Google Review prompt](google-review-prompt.md) — turn a high rating into a review
- [Statistics](statistics.md) — read the ratings back as charts and scores
- [Displaying ratings in templates](../template-guides/displaying-ratings.md)
- [Front-end CSS](../developers/front-end-css.md) — restyle the widget with CSS variables
