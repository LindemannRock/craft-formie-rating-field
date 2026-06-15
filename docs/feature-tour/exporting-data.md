# Exporting data

Take the ratings out of the Control Panel and into a report or spreadsheet. From any form's statistics page you can export a high-level summary, every raw response, or a by-group breakdown — as Excel, CSV, or JSON.

## What you'll use it for

- A summary of every rating field for a monthly report
- The raw responses for your own analysis in a spreadsheet
- A per-group breakdown to compare products or locations
- Feeding rating data into another system as JSON

## Export from a form

On a form's [statistics page](statistics.md), open the **Export** menu (you'll need the *Export statistics* permission). The export honors your current **date range**, **site**, and **group by** selections, and includes these sections:

| Section | One row per… | Contains |
|---------|--------------|----------|
| **Summary** | rating field | Total responses, NPS score with promoter/passive/detractor counts and percentages, average, median, most common — whichever apply to the field's type (inapplicable cells show `—`). |
| **Raw Responses** | submission | Submission date, submission ID, site, then a column for each rating field. |
| **By Group** | group value | Submission count and the per-field metrics for that group. Only included when a **Group By** field is selected. |

![The Export menu on a form's statistics page](images/exporting-export-menu.webp)

## Formats

| Format | What you get |
|--------|--------------|
| **Excel** | One workbook with a sheet per section (Summary / Raw Responses / By Group). |
| **CSV** | A ZIP containing one CSV per section. |
| **JSON** | A single nested file with all sections. |

Which formats appear in the menu is controlled by the `exports` config key — see [Configuration](../get-started/configuration.md#shared-base-settings).

## Export a single group

When you drill into one group's submissions (from the grouped view), that page has its own **Export** that downloads just those submissions.

## The Raw Responses row cap

Raw Responses hydrate a full submission for every row, which is memory-intensive on high-volume forms. The **Max Export Rows** setting (default `50,000`) caps how many rows a Raw Responses export includes; when the cap is hit, the export is truncated and a warning is logged. Set it to `0` for unlimited — only if your PHP `memory_limit` is generous. See [Configuration](../get-started/configuration.md#interface-interface-tab).

## Next steps

- [Statistics](statistics.md) — the dashboard the exports come from
- [Caching](caching.md) — keep the underlying numbers fast and current
