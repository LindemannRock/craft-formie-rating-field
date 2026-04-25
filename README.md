# Formie Rating Field Plugin

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-formie-rating-field.svg)](https://packagist.org/packages/lindemannrock/craft-formie-rating-field)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![Formie](https://img.shields.io/badge/Formie-3.0+-purple.svg)](https://verbb.io/craft-plugins/formie)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-formie-rating-field.svg)](LICENSE)

A Craft CMS plugin that provides advanced rating field types for Verbb's Formie form builder, including star, emoji, and NPS (Net Promoter Score) rating types.

## License

This is a commercial plugin licensed under the [Craft License](https://craftcms.github.io/license/). It will be available on the [Craft Plugin Store](https://plugins.craftcms.com) soon. See [LICENSE.md](LICENSE.md) for details.

## ⚠️ Pre-Release

This plugin is in active development and not yet available on the Craft Plugin Store. Features and APIs may change before the initial public release.

## Requirements

- Craft CMS 5.0 or greater
- PHP 8.2 or greater
- Formie 3.0 or greater

## Features

### Three Rating Types
- **Star Rating**: Classic star ratings with optional half-star support
- **Emoji Rating**: Expressive emotion faces (😭 😢 😕 😐 😊 😍 🤩 🥰 😎 🤗 🥳) - supports 0-10 scale
- **NPS Rating**: Net Promoter Score numeric boxes (0-10 scale)

### Customizable Settings
- **Size Options**: Small, medium, large, extra large
- **Flexible Range**: Configurable min/max values
- **Half Ratings**: Enable half-star selections (star type only)
- **Single Emoji Selection**: Highlight only the selected emoji instead of cumulative (emoji type only)
- **Custom Labels**: Define text labels for each rating value that display beneath selected emoji
- **Endpoint Labels**: Add descriptive labels at scale endpoints (e.g., "Poor" / "Excellent")
- **Emoji Render Modes**: Choose between system emojis, Noto Color Emoji (detailed), or Noto Emoji (simple)
- **Plugin Settings**: Configure defaults for all new rating fields

### Seamless Integration
- Native Formie field with full validation support
- GraphQL support for headless implementations
- RTL support for Arabic sites
- Backward compatible with existing forms

### Statistics & Analytics
- **Comprehensive Analytics Dashboard**: View rating statistics for all forms
- **Smart Grouping**: Group ratings by product code, name, category, or any form field
- **Type-Aware Calculations**: Automatic NPS scoring, star/emoji averages, and distributions
- **Performance Indicators**: Scale-aware insights (Excellent/Good/Fair/Poor) that adapt to any min/max range
- **Date Range Filtering**: Analyze trends across today, yesterday, last 7/30/90 days, this month (MTD), last month, this year, last year, or all time
- **Multi-Format Export**: Excel (multi-sheet: Summary / Raw Responses / By Group when grouping), CSV (ZIP of per-section CSVs), or JSON (single nested file); format availability gated via `exports` config key
- **NPS Visual Indicators**: Stat boxes use traffic-light color coding (NPS Score: green ≥ 50, amber 0–49, red < 0; Promoters: green; Passives: amber; Detractors: red)
- **Trend Charts**: NPS trend plots NPS Score (−100 to 100); Star/Emoji trend plots average rating
- **File-Based or Redis Caching**: Fast performance with automatic cache invalidation on new submissions; configurable storage method and generation schedule
- **CLI Cache Management**: Clear, inspect, and pre-generate cache via command line tools

## Installation

### Via Composer

```bash
cd /path/to/project
```

```bash
composer require lindemannrock/craft-formie-rating-field
```

```bash
./craft plugin/install formie-rating-field
```

### Using DDEV

```bash
cd /path/to/project
```

```bash
ddev composer require lindemannrock/craft-formie-rating-field
```

```bash
ddev craft plugin/install formie-rating-field
```

### Via Control Panel

In the Control Panel, go to Settings → Plugins and click "Install" for Formie Rating Field.

## Configuration

### Plugin Settings

Navigate to **Settings → Plugins → Formie Rating Field** to configure default values for new rating fields:

- **Default Rating Type**: Star, emoji, or NPS
- **Default Size**: Small to extra large
- **Default Range**: Min/max rating values
- **Default Labels**: Start/end label text
- **Default Options**: Half ratings, show labels, etc.

### Config File

Create a `config/formie-rating-field.php` file to override default settings:

```bash
cp vendor/lindemannrock/craft-formie-rating-field/src/config.php config/formie-rating-field.php
```

Example configuration:

```php
// config/formie-rating-field.php
return [
    'defaultRatingType' => 'emoji',
    'defaultRatingSize' => 'large',
    'defaultMinRating' => 0,
    'defaultMaxRating' => 10,
    'defaultAllowHalfRatings' => false,
    'defaultSingleEmojiSelection' => true,
    'defaultShowEndpointLabels' => true,
    'defaultStartLabel' => 'Not Likely',
    'defaultEndLabel' => 'Very Likely',
    'defaultEmojiRenderMode' => 'noto-simple',  // 'system', 'noto-color', 'noto-simple'
    'itemsPerPage' => 50,  // Number of items per page in statistics lists
];
```

See [Configuration Documentation](docs/CONFIGURATION.md) for all available options.

## Statistics & Analytics

The plugin includes a comprehensive analytics dashboard for analyzing rating field submissions.

### Accessing Statistics

Navigate to the plugin's CP section:
1. **Forms Rating** → **Statistics** in the main navigation
2. Select a form to view detailed statistics
3. Use filters to refine your analysis

### Key Features

**Smart Grouping**
- Group ratings by any field in your form (product code, category, hidden fields, etc.)
- View performance breakdown for each group
- Identify top performers and items needing attention

**Date Range Filtering**
- Filter by: Today, Yesterday, Last 7/30/90 days, This Month (MTD), Last Month, This Year, Last Year, or All Time
- Analyze trends over specific periods
- Compare performance across time ranges

**Performance Insights**
- Scale-aware indicators (Excellent/Good/Fair/Poor) that adapt to any rating range
- Reliability warnings for products with insufficient reviews (<5)
- Visual progress bars showing relative performance
- NPS score calculation with promoter/passive/detractor breakdown

**Data Export**
- **Excel** (`.xlsx`): Multi-sheet workbook — Summary, Raw Responses, and By Group (when a grouping field is selected)
- **CSV** (`.zip`): ZIP archive containing one `.csv` file per section
- **JSON**: Single nested file with all sections
- Format availability is gated via the `exports` key in `config/formie-rating-field.php`

### Analytics Views

**When Not Grouped (Default)**
- Overall statistics across all submissions
- Distribution charts showing rating patterns
- Summary metrics (average, median, mode)

**When Grouped by Field**
- Summary cards showing total groups, overall average, top/bottom performers
- Detailed table with performance indicators for each group
- Sortable columns (Product Name, Reviews, Average, Performance)
- Client-side search with instant filtering
- Click any group to drill down to individual submissions

**Drill-Down View**
- Click any product/group to see individual submissions
- Full submission data displayed (all form fields)
- Search, sort, and paginate through submissions
- Export submissions for specific group to CSV
- Direct links to view full submissions in Formie

### CLI Commands

Manage statistics cache via command line:

```bash
# Clear all statistics cache
php craft formie-rating-field/cache/clear

# Clear cache for specific form
php craft formie-rating-field/cache/clear-form --formId=34

# View cache information
php craft formie-rating-field/cache/info
```

Or with DDEV:

```bash
ddev craft formie-rating-field/cache/clear
ddev craft formie-rating-field/cache/info
```

### Cache Behavior

- **Location**: `storage/runtime/formie-rating-field/cache/statistics/`
- **Invalidation**: Automatic on submission save/delete
- **Manual Refresh**: Use CLI commands or "Refresh" button in CP
- **No TTL**: Cache persists until invalidated (optimal performance)

## Usage

### Adding a Rating Field

1. Open your form in the Formie form builder
2. Click "Add Field" and select "Rating" from the field types
3. Configure the field settings:
   - **Rating Type**: Choose star, emoji, or NPS display
   - **Size**: Control the visual size of rating elements
   - **Rating Range**: Set minimum and maximum values
   - **Allow Half Ratings**: Enable for star type (stars only)
   - **Single Emoji Selection**: Enable for single emoji highlighting (emoji only)
   - **Custom Labels**: Define labels for each value (shows when using single emoji selection)
   - **Endpoint Labels**: Add descriptive text at scale ends

### Using Single Emoji Selection with Custom Labels

When **Single Emoji Selection** is enabled for emoji ratings:

1. Only the clicked emoji is highlighted (not cumulative)
2. A custom label displays beneath the selected emoji
3. Define labels in the **Custom Labels** table that appears

**Example:** For a 1-5 rating scale:

| Value | Label |
|-------|-------|
| 1 | Terrible |
| 2 | Bad |
| 3 | Okay |
| 4 | Good |
| 5 | Excellent |

**Important:**
- Define a label for **each value** in your rating range (e.g., if min=0 and max=10, define labels for values 0-10)
- Labels display only when an emoji is selected
- If no custom label is defined for a value, the numeric value displays instead

### Templating

In your templates, rating fields are rendered automatically by Formie:

```twig
{# Render the entire form #}
{{ craft.formie.renderForm('contactForm') }}

{# Or render a specific field #}
{% set form = craft.formie.forms.handle('contactForm').one() %}
{{ craft.formie.renderField(form, 'ratingField') }}
```

### GraphQL Support

Query rating field data via GraphQL:

```graphql
query {
  formieSubmissions(form: "contactForm") {
    ... on contactForm_Submission {
      ratingField
    }
  }
}
```

## Field Settings Reference

| Setting | Description | Options |
|---------|-------------|---------|
| **Rating Type** | Visual style of the rating | `star`, `emoji`, `nps` |
| **Emoji Render Mode** | How emojis are displayed | `system`, `noto-color`, `noto-simple` (emoji only) |
| **Size** | Visual size of rating elements | `small`, `medium`, `large`, `xlarge` |
| **Min Value** | Minimum rating value | 0-1 (NPS is always 0) |
| **Max Value** | Maximum rating value | 3-10 (NPS is always 10) |
| **Allow Half Ratings** | Enable half-star selections | true/false (star only) |
| **Single Emoji Selection** | Highlight only selected emoji (not cumulative) | true/false (emoji only) |
| **Custom Labels** | Define text labels for each rating value | Table with Value/Label pairs |
| **Show Endpoint Labels** | Display labels at scale ends | true/false |
| **Start Label** | Text for lowest value | Any text |
| **End Label** | Text for highest value | Any text |
| **Show Selected Label** | Display selected rating as text | true/false |

### Google Review Integration

Automatically prompt high-rating customers to leave Google Reviews:

| Setting | Description |
|---------|-------------|
| **Enable Google Review Prompt** | Show Google Review link for high ratings |
| **Rating Threshold** | Minimum rating to trigger prompt (e.g., 9 for NPS) |
| **Google Place ID Field** | Handle of field containing Place ID |
| **Review URL Template** | Customizable URL (supports different regions) |
| **Button Label** | Text on review button |
| **Button Alignment** | start/center/end |
| **High/Medium/Low Messages** | Custom messages per rating tier |

**How it works:**
- High ratings (≥ threshold): Shows Google Review button
- Medium ratings (threshold - 2): Generic thank you message
- Low ratings (< medium): Improvement message

All messages are translatable through Formie's translation system.

## Styling

For custom CSS styling options and examples, see [CSS Customization Guide](docs/CSS_CUSTOMIZATION.md).

## File Structure

```
plugins/formie-rating-field/
├── docs/
│   ├── CONFIGURATION.md                   # Configuration guide
│   └── CSS_CUSTOMIZATION.md               # CSS customization guide
├── src/
│   ├── console/
│   │   └── controllers/
│   │       └── CacheController.php        # CLI cache management
│   ├── controllers/
│   │   ├── StatisticsController.php       # Statistics & analytics
│   │   └── SettingsController.php         # Settings pages
│   ├── fields/
│   │   └── Rating.php                     # Main field class
│   ├── integrations/
│   │   └── feedme/
│   │       └── fields/
│   │           └── Rating.php             # Feed Me integration
│   ├── models/
│   │   └── Settings.php                   # Plugin settings model
│   ├── services/
│   │   └── StatisticsService.php          # Statistics calculations
│   ├── templates/
│   │   ├── _components/
│   │   │   └── plugin-credit.twig
│   │   ├── _layouts/
│   │   │   └── settings.twig              # Settings layout with sidebar
│   │   ├── fields/
│   │   │   └── rating/
│   │   │       ├── input.twig             # Field input template
│   │   │       ├── value.twig             # Value display
│   │   │       └── email.twig             # Email template
│   │   ├── settings/
│   │   │   ├── general.twig               # General settings tab
│   │   │   └── interface.twig             # Interface settings tab
│   │   ├── statistics/
│   │   │   ├── index.twig                 # Forms list
│   │   │   └── form.twig                  # Form statistics detail
│   │   ├── settings.twig                  # Settings redirect
│   │   └── index.twig                     # Plugin index redirect
│   ├── twigextensions/
│   │   └── PluginNameExtension.php        # Twig helper (ratingHelper)
│   ├── web/
│   │   └── assets/
│   │       └── field/
│   │           ├── RatingFieldAsset.php
│   │           ├── rating.css
│   │           └── rating.js
│   ├── config.php                         # Config file template
│   ├── icon.svg                           # Plugin icon
│   └── FormieRatingField.php              # Main plugin class
├── CHANGELOG.md
├── LICENSE.md
├── README.md
└── composer.json
```

## Support

- **Documentation**: [https://github.com/LindemannRock/craft-formie-rating-field](https://github.com/LindemannRock/craft-formie-rating-field)
- **Issues**: [https://github.com/LindemannRock/craft-formie-rating-field/issues](https://github.com/LindemannRock/craft-formie-rating-field/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the [Craft License](https://craftcms.github.io/license/). See [LICENSE.md](LICENSE.md) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)

Built for use with [Formie](https://verbb.io/craft-plugins/formie) by Verbb