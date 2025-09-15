# Rating Field Assets

This directory contains the CSS and JavaScript assets for the Formie Rating Field module.

## Files

- **rating.js** - The main JavaScript file that handles the rating field functionality
- **rating.min.js** - Minified version of rating.js (used in production)
- **rating.css** - Styles for the rating field
- **RatingFieldAsset.php** - Asset bundle that registers the CSS and JS files

## How it Works

1. When a Formie form with a rating field is rendered, the `RatingFieldAsset` bundle is registered
2. The JavaScript initializes all rating fields on page load
3. It converts the standard `<select>` element into an interactive rating widget
4. The original select remains hidden but is updated when ratings change
5. The widget supports three types: stars, emojis, and NPS (numeric)

## JavaScript API

The JavaScript exposes a global `FormieRating` object with two methods:

```javascript
// Initialize all rating fields on the page
FormieRating.init();

// Initialize a specific field
FormieRating.initField(selectElement);
```

## CSS Classes

The CSS provides classes for:
- Container: `.fui-rating-container`
- Items wrapper: `.fui-rating-items`
- Individual items: `.fui-rating-item`
- States: `.is-selected`, `.is-active`, `.is-hover`
- Type-specific: `[data-rating-type="star"]`, etc.
- Size variations: `[data-rating-size="small"]`, etc.

## Integration with Formie

The field automatically integrates with Formie's event system:
- Listens for `formie:field-added` events to initialize dynamically added fields
- Dispatches standard `change` events when values update
- Maintains compatibility with Formie's validation and submission

## Development

To modify the assets:
1. Edit `rating.js` and `rating.css`
2. Minify the JS for production: Use any JS minifier to create `rating.min.js`
3. Test in both dev mode (unminified) and production mode (minified)

The asset bundle automatically uses the appropriate version based on Craft's `devMode` setting.