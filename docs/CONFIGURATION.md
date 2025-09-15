# Formie Rating Field Configuration

## Configuration File

You can override plugin settings by creating a `formie-rating-field.php` file in your `config/` directory.

### Basic Setup

1. Copy `vendor/lindemannrock/formie-rating-field/src/config.php` to `config/formie-rating-field.php`
2. Modify the settings as needed

### Available Settings

```php
<?php
return [
    // Plugin name shown in Control Panel (optional)
    'pluginName' => 'Custom Rating Field Name',
    
    // Default rating type for new rating fields
    'defaultRatingType' => 'star',
    
    // Default rating size for new rating fields
    'defaultRatingSize' => 'medium',
    
    // Default rating range
    'defaultMinRating' => 1,
    'defaultMaxRating' => 5,
    
    // Default options
    'defaultAllowHalfRatings' => false,
    'defaultShowSelectedLabel' => false,
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
    ],
    
    // Development environment
    'dev' => [
        'defaultShowEndpointLabels' => true,
        'defaultStartLabel' => 'Poor',
        'defaultEndLabel' => 'Excellent',
    ],
    
    // Production environment
    'production' => [
        'defaultRatingType' => 'emoji',
        'defaultRatingSize' => 'large',
        'defaultMinRating' => 0,
        'defaultMaxRating' => 10,
    ],
];
```

### Using Environment Variables

All settings support environment variables:

```php
return [
    'defaultRatingType' => getenv('RATING_TYPE') ?: 'star',
    'defaultMaxRating' => (int)(getenv('RATING_MAX') ?: 5),
    'defaultShowEndpointLabels' => getenv('RATING_SHOW_LABELS') === 'true',
];
```

### Setting Descriptions

#### General Settings

- **pluginName**: The name shown in the Control Panel (usually set via Settings → Plugins)
- **defaultRatingType**: Default type for new rating fields ('star', 'emoji', 'nps')
- **defaultRatingSize**: Default size for rating elements ('small', 'medium', 'large', 'xlarge')

#### Rating Range Settings

- **defaultMinRating**: Default minimum rating value (0 or 1)
- **defaultMaxRating**: Default maximum rating value (3-10)

#### Feature Settings

- **defaultAllowHalfRatings**: Enable half-star ratings by default (star type only)
- **defaultShowSelectedLabel**: Show selected value as label (currently hidden in UI)
- **defaultShowEndpointLabels**: Display labels at start/end of rating scale
- **defaultStartLabel**: Default text for lowest rating (e.g., "Poor", "Not Likely")
- **defaultEndLabel**: Default text for highest rating (e.g., "Excellent", "Very Likely")

### Rating Type Details

#### Star Rating
- Classic 5-star rating system
- Supports half-star ratings when enabled
- Good for general satisfaction surveys

#### Emoji Rating  
- Uses expressive emotion faces
- Fixed 1-5 scale (😢 😞 😐 😊 😍)
- Great for user experience feedback

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
    'defaultMinRating' => 1,
    'defaultMaxRating' => 5,
    'defaultShowEndpointLabels' => false,
];
```

### Precedence

Settings are loaded in this order (later overrides earlier):

1. Default plugin settings (hardcoded)
2. Database-stored settings (from Control Panel)
3. Config file settings
4. Environment-specific config settings

### Validation

The plugin validates all settings:
- **Rating Type**: Must be 'star', 'emoji', or 'nps'
- **Rating Size**: Must be 'small', 'medium', 'large', or 'xlarge'  
- **Min Rating**: Must be 0 or 1
- **Max Rating**: Must be 3-10
- **Default Text Size**: Must match an available text size value

### Troubleshooting

#### Settings Not Applied
1. Clear compiled classes: `php craft clear-caches/compiled-classes`
2. Check config file syntax is valid PHP
3. Verify setting values are within valid ranges

#### Plugin Name Not Changed
1. Ensure config file is in correct location: `config/formie-rating-field.php`
2. Check for typos in `pluginName` setting
3. Settings in Control Panel override config file