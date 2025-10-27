# Formie Rating Field Configuration

## Configuration File

You can override plugin settings by creating a `formie-rating-field.php` file in your `config/` directory.

### Basic Setup

1. Copy `vendor/lindemannrock/craft-formie-rating-field/src/config.php` to `config/formie-rating-field.php`
2. Modify the settings as needed

### Available Settings

```php
<?php
return [
    // General Settings
    'pluginName' => 'Formie Rating Field',

    // Field Default Settings
    'defaultRatingType' => 'star',
    'defaultEmojiRenderMode' => 'system',
    'defaultRatingSize' => 'medium',
    'defaultMinRating' => 1,
    'defaultMaxRating' => 5,

    // Rating Type Settings
    'defaultAllowHalfRatings' => false,
    'defaultSingleEmojiSelection' => false,

    // Label Settings
    'defaultShowEndpointLabels' => false,
    'defaultStartLabel' => '',
    'defaultEndLabel' => '',
];
```

### Multi-Environment Configuration

You can have different settings per environment:

```php
<?php
return [
    // Global settings
    '*' => [
        'defaultRatingType' => 'star',
        'defaultRatingSize' => 'medium',
        'defaultMinRating' => 1,
        'defaultMaxRating' => 5,
    ],

    // Development environment
    'dev' => [
        // Development-specific settings can go here
    ],

    // Staging environment
    'staging' => [
        // Staging-specific settings can go here
    ],

    // Production environment
    'production' => [
        'defaultRatingType' => 'emoji',
        'defaultRatingSize' => 'large',
        'defaultMinRating' => 0,
        'defaultMaxRating' => 10,
        'defaultSingleEmojiSelection' => true,
    ],
];
```

### Setting Descriptions

#### General Settings

##### pluginName
Display name for the plugin in Craft CP navigation.
- **Type:** `string`
- **Default:** `'Formie Rating Field'`

#### Field Default Settings

##### defaultRatingType
Default rating type for new rating fields.
- **Type:** `string`
- **Options:** `'star'`, `'emoji'`, `'nps'`
- **Default:** `'star'`

##### defaultEmojiRenderMode
How emoji ratings are rendered (emoji type only).
- **Type:** `string`
- **Options:**
  - `'system'`: Native platform emojis (iOS, Android, Windows, etc.)
  - `'noto-color'`: Noto Color Emoji from Google Fonts (detailed, colorful style)
  - `'noto-simple'`: Noto Emoji from Google Fonts (simple, clean style)
  - `'webfont'`: Deprecated, maps to `'noto-color'` for backward compatibility
- **Default:** `'system'`

##### defaultRatingSize
Default size for rating elements.
- **Type:** `string`
- **Options:** `'small'`, `'medium'`, `'large'`, `'xlarge'`
- **Default:** `'medium'`

##### defaultMinRating
Default minimum rating value.
- **Type:** `int`
- **Options:** `0`, `1`
- **Default:** `1`

##### defaultMaxRating
Default maximum rating value.
- **Type:** `int`
- **Options:** `3`, `4`, `5`, `6`, `7`, `8`, `9`, `10`
- **Default:** `5`

#### Rating Type Settings

##### defaultAllowHalfRatings
Enable half-star ratings by default (star type only).
- **Type:** `bool`
- **Default:** `false`

##### defaultSingleEmojiSelection
Enable single emoji selection mode by default (emoji type only).
- **Type:** `bool`
- **Default:** `false`
- **Behavior:** When enabled, only the selected emoji is highlighted (not cumulative) and custom labels display beneath the selected emoji

#### Label Settings

##### defaultShowEndpointLabels
Display labels at start/end of rating scale.
- **Type:** `bool`
- **Default:** `false`

##### defaultStartLabel
Default text for lowest rating.
- **Type:** `string`
- **Default:** `''` (empty)
- **Examples:** `'Poor'`, `'Not Likely'`, `'Disagree'`

##### defaultEndLabel
Default text for highest rating.
- **Type:** `string`
- **Default:** `''` (empty)
- **Examples:** `'Excellent'`, `'Very Likely'`, `'Strongly Agree'`

### Rating Type Details

#### Star Rating
- Classic 5-star rating system
- Supports half-star ratings when enabled
- Good for general satisfaction surveys

#### Emoji Rating
- Uses expressive emotion faces
- Supports 0-10 scale with smart emoji selection (😭 😢 😕 😐 😊 😍 🤩 🥰 😎 🤗 🥳)
- Great for user experience feedback
- Three render modes:
  - **System**: Native platform emojis
  - **Noto Color**: Detailed, colorful Google Font emoji style
  - **Noto Simple**: Clean Google Font style with customizable sentiment colors (red → orange → yellow → green gradient)

#### NPS (Net Promoter Score)
- Numeric boxes from 0-10
- Standard business metric
- Ideal for customer loyalty surveys

### Common Configuration Examples

#### Customer Satisfaction Survey

```php
return [
    'defaultRatingType' => 'star',
    'defaultRatingSize' => 'large',
    'defaultMinRating' => 1,
    'defaultMaxRating' => 5,
    'defaultShowEndpointLabels' => true,
    'defaultStartLabel' => 'Very Dissatisfied',
    'defaultEndLabel' => 'Very Satisfied',
];
```

#### Net Promoter Score

```php
return [
    'defaultRatingType' => 'nps',
    'defaultRatingSize' => 'medium',
    'defaultMinRating' => 0,
    'defaultMaxRating' => 10,
    'defaultShowEndpointLabels' => true,
    'defaultStartLabel' => 'Not Likely',
    'defaultEndLabel' => 'Very Likely',
];
```

#### User Experience Feedback

```php
return [
    'defaultRatingType' => 'emoji',
    'defaultRatingSize' => 'xlarge',
    'defaultMinRating' => 0,
    'defaultMaxRating' => 10,
    'defaultShowEndpointLabels' => false,
    'defaultSingleEmojiSelection' => true,
    'defaultEmojiRenderMode' => 'noto-simple',
];
```

### Precedence

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings
2. Database-stored settings (from CP)
3. Config file settings
4. Environment-specific config settings

**Note:** Config file settings always override database settings, making them ideal for production environments where you want to enforce specific values.

### Using Environment Variables

All settings support environment variables:

```php
use craft\helpers\App;

return [
    'defaultRatingType' => App::env('RATING_TYPE') ?: 'star',
    'defaultMaxRating' => (int)App::env('RATING_MAX') ?: 5,
    'defaultShowEndpointLabels' => (bool)App::env('RATING_SHOW_LABELS') ?: false,
];
```

### Validation

The plugin validates all settings:
- **Rating Type**: Must be `'star'`, `'emoji'`, or `'nps'`
- **Rating Size**: Must be `'small'`, `'medium'`, `'large'`, or `'xlarge'`
- **Min Rating**: Must be `0` or `1`
- **Max Rating**: Must be `3-10`
- **Emoji Render Mode**: Must be `'system'`, `'noto-color'`, `'noto-simple'`, or `'webfont'`

### Troubleshooting

#### Settings Not Applied

1. Clear compiled classes: `php craft clear-caches/compiled-classes`
2. Check config file syntax is valid PHP
3. Verify setting values are within valid ranges

#### Plugin Name Not Changed

1. Ensure config file is in correct location: `config/formie-rating-field.php`
2. Check for typos in `pluginName` setting
3. Settings in Control Panel override config file
