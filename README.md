# Formie Rating Field Plugin

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
- **Endpoint Labels**: Add descriptive labels at scale endpoints (e.g., "Poor" / "Excellent")
- **Custom Labels**: Override numeric values with custom text
- **Plugin Settings**: Configure defaults for all new rating fields

### Seamless Integration
- Native Formie field with full validation support
- GraphQL support for headless implementations
- RTL support for Arabic sites
- Backward compatible with existing forms

## Installation

### Via Composer (Development)

Until published on Packagist, install directly from the repository:

```bash
cd /path/to/project
composer config repositories.formie-rating-field vcs https://github.com/LindemannRock/craft-formie-rating-field
composer require lindemannrock/formie-rating-field:dev-main
./craft plugin/install formie-rating-field
```

### Via Composer (Production - Coming Soon)

Once published on Packagist:

```bash
cd /path/to/project
composer require lindemannrock/formie-rating-field
./craft plugin/install formie-rating-field
```

### Via Plugin Store (Future)

1. Go to the Plugin Store in your Craft control panel
2. Search for "Formie Rating Field"
3. Click "Install"

## Configuration

### Plugin Settings

Navigate to **Settings → Plugins → Formie Rating Field** to configure default values for new rating fields:

- **Default Rating Type**: Star, emoji, or NPS
- **Default Size**: Small to extra large
- **Default Range**: Min/max rating values
- **Default Labels**: Start/end label text
- **Default Options**: Half ratings, show labels, etc.

### Advanced Configuration

For detailed configuration options, see [Configuration Guide](docs/CONFIGURATION.md).

Create a config file for custom defaults:

```php
// config/formie-rating-field.php
return [
    'defaultRatingType' => 'emoji',
    'defaultRatingSize' => 'large',
    'defaultMinRating' => 0,
    'defaultMaxRating' => 10,
    'defaultAllowHalfRatings' => true,
    'defaultShowSelectedLabel' => true,  // Future feature
    'defaultShowEndpointLabels' => true,
    'defaultStartLabel' => 'Poor',
    'defaultEndLabel' => 'Excellent',
];
```

## Usage

### Adding a Rating Field

1. Open your form in the Formie form builder
2. Click "Add Field" and select "Rating" from the field types
3. Configure the field settings:
   - **Rating Type**: Choose star, emoji, or NPS display
   - **Size**: Control the visual size of rating elements
   - **Rating Range**: Set minimum and maximum values
   - **Allow Half Ratings**: Enable for star type
   - **Endpoint Labels**: Add descriptive text at scale ends

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
| **Size** | Visual size of rating elements | `small`, `medium`, `large`, `xlarge` |
| **Min Value** | Minimum rating value | 0-10 |
| **Max Value** | Maximum rating value | 1-10 |
| **Allow Half Ratings** | Enable half-star selections | true/false (star only) |
| **Show Endpoint Labels** | Display labels at scale ends | true/false |
| **Start Label** | Text for lowest value | Any text |
| **End Label** | Text for highest value | Any text |

## Styling

For custom CSS styling options and examples, see [CSS_CUSTOMIZATION.md](CSS_CUSTOMIZATION.md).

## File Structure

```
plugins/formie-rating-field/
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