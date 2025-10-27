# Formie Rating Field Plugin

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-formie-rating-field.svg)](https://packagist.org/packages/lindemannrock/craft-formie-rating-field)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![Formie](https://img.shields.io/badge/Formie-3.0+-purple.svg)](https://verbb.io/craft-plugins/formie)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-formie-rating-field.svg)](LICENSE)

A Craft CMS plugin that provides advanced rating field types for Verbb's Formie form builder, including star, emoji, and NPS (Net Promoter Score) rating types.

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

## Installation

### Via Composer

```bash
cd /path/to/project
composer require lindemannrock/craft-formie-rating-field
./craft plugin/install formie-rating-field
```

### Using DDEV

```bash
cd /path/to/project
ddev composer require lindemannrock/craft-formie-rating-field
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
];
```

See [Configuration Documentation](docs/CONFIGURATION.md) for all available options.

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
| **Min Value** | Minimum rating value | 0-10 |
| **Max Value** | Maximum rating value | 1-10 |
| **Allow Half Ratings** | Enable half-star selections | true/false (star only) |
| **Single Emoji Selection** | Highlight only selected emoji (not cumulative) | true/false (emoji only) |
| **Custom Labels** | Define text labels for each rating value | Table with Value/Label pairs |
| **Show Endpoint Labels** | Display labels at scale ends | true/false |
| **Start Label** | Text for lowest value | Any text |
| **End Label** | Text for highest value | Any text |

## Styling

For custom CSS styling options and examples, see [CSS Customization Guide](docs/CSS_CUSTOMIZATION.md).

## File Structure

```
plugins/formie-rating-field/
├── docs/
│   ├── CONFIGURATION.md            # Configuration guide
│   └── CSS_CUSTOMIZATION.md        # CSS customization guide
├── src/
│   ├── fields/
│   │   └── Rating.php              # Main field class
│   ├── models/
│   │   └── Settings.php            # Plugin settings model
│   ├── templates/
│   │   ├── fields/
│   │   │   └── rating/
│   │   │       ├── input.twig      # Field input template
│   │   │       └── preview.twig    # CP preview template
│   │   └── settings.twig           # Plugin settings template
│   ├── web/
│   │   └── assets/
│   │       └── field/
│   │           └── RatingFieldAsset.php
│   ├── icon.svg                    # Plugin icon
│   └── FormieRatingField.php       # Main plugin class
├── CHANGELOG.md
├── LICENSE.md
├── README.md
├── TODO.md
└── composer.json
```

## Support

- **Documentation**: [https://github.com/LindemannRock/craft-formie-rating-field](https://github.com/LindemannRock/craft-formie-rating-field)
- **Issues**: [https://github.com/LindemannRock/craft-formie-rating-field/issues](https://github.com/LindemannRock/craft-formie-rating-field/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)

Built for use with [Formie](https://verbb.io/craft-plugins/formie) by Verbb