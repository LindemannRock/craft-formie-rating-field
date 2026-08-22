# Google Review prompt

Catch happy customers at the moment they're happiest. When someone gives a high rating, the Google Review prompt replaces the form's success message with a tailored thank-you and a button that opens your Google review page — so a five-star feeling turns into a public five-star review.

## What you'll use it for

- Sending only your most satisfied customers to leave a Google review
- Showing a softer "thanks for the feedback" to middling and low ratings instead
- Routing different branches or locations to their own Google listing using a Place ID field

## How it works

The prompt is tiered by the rating value, so each visitor sees a message that matches how they rated:

| Tier | When | What shows |
|------|------|-----------|
| **High** | Rating at or above your threshold (and a Place ID is present) | The high message **and the review button** |
| **Medium** | Rating within two of the threshold | The medium message, no button |
| **Low** | Any lower rating | The low message, no button |

Only the high tier shows the review button — the idea is to invite a review when the sentiment is strong, and simply acknowledge the rest.

> [!IMPORTANT]
> The prompt **overrides the form's success message**. Enable it on **only one** Rating field per form, or the prompts will compete.

Pages may contain several Formie forms. The prompt listens only to the form that owns the configured Rating field and replaces only that submitted form's adjacent success message; unrelated forms and their alerts are left untouched.

## Turn it on

Open the Rating field's **Settings** tab in the form builder and enable **Enable Google Review Prompt**. The rest of the options appear once it's on:

| Setting | Default | What it's for |
|---------|---------|---------------|
| **Rating Threshold** | Automatic | Minimum rating that counts as "high". Leave blank to calculate it from the field's scale, or enter a value to override the automatic threshold. |
| **Google Place ID Field Handle** | — (required) | Handle of another form field that holds the Google Place ID. Lets each submission target the right listing. |
| **High Rating Message** | — | Shown at or above the threshold. |
| **Medium Rating Message** | — | Shown for ratings near the threshold. |
| **Low Rating Message** | — | Shown for low ratings. |
| **Google Review URL Template** | `https://search.google.com/local/writereview?placeid={googlePlaceId}` | The review link. `{googlePlaceId}` is replaced with the value from your Place ID field. |
| **Review Button Label** | — | Text on the button (e.g. *Review on Google*). |
| **Button Alignment** | `start` | `start`, `center`, or `end`. |

The automatic threshold is 90% of the scale maximum, rounded and kept within the field's range. A 1–5 star or emoji field therefore uses `5`, while an NPS field uses `9` on its fixed 0–10 scale. An explicit threshold inside the current field range takes precedence; if a later scale or rating-type change puts it outside the range, the prompt falls back to the automatic value.

![The Google Review prompt settings on a Rating field](../images/google-review-settings.webp)

## The Place ID field

The prompt needs a [Google Place ID](https://developers.google.com/maps/documentation/places/web-service/place-id) to know which listing to send reviewers to. Add a field to the form (often a hidden field pre-filled per page or location), then put its **handle** in **Google Place ID Field Handle**. On submit, the plugin reads that field's value and substitutes it into the URL template.

## Next steps

- [The Rating field](rating-field.md) — all the other field options
- [Statistics](statistics.md) — see how those ratings score over time
