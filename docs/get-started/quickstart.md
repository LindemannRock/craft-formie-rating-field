# Quickstart

Get Formie Rating Field running in a few minutes. By the end you'll have a working rating question on a live form and see its first result in the Statistics dashboard.

## 1. Install the plugin

> See [Installation](installation.md) for full details including DDEV and config options. Make sure [Formie](https://verbb.io/craft-plugins/formie) is installed and enabled first.

## 2. Add a Rating field to a form

In the Control Panel, open **Formie → Forms** and edit (or create) a form. From the field list on the right, drag **Rating** onto the form.

In the field's settings:

1. Set a **Label** (e.g. *How would you rate us?*).
2. Choose a **Rating Type** — **Star Rating**, **Emoji Rating**, or **NPS (Number) Rating**.
3. For star/emoji, set the **Maximum Value** (e.g. 5). NPS is always 0–10.

Save the form.

## 3. Submit a rating

Open the form on your site front-end (or use Formie's preview). Pick a rating and submit. The widget renders as interactive stars, emoji, or NPS number boxes depending on the type you chose.

## 4. Verify it works

In the Control Panel, open **Formie Rating → Statistics**. Your form appears in the list with a rating-field count and submission count. Click the form title to see its average (or NPS score), the value distribution, and a trend chart.

> New submissions invalidate that form's cached statistics automatically, so the numbers stay current between page loads.

## What's next

- [Configuration](configuration.md) — set defaults for new fields and tune caching
- [The Rating field](../feature-tour/rating-field.md) — every field option, type by type
- [Statistics](../feature-tour/statistics.md) — grouping, date ranges, and per-site views
- [Feature tour](../feature-tour/overview.md) — explore everything the plugin can do
