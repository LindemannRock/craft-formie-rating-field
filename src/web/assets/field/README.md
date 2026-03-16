# Rating Field Assets

This directory contains the CSS and JavaScript assets for the Formie Rating Field module.

## Files

- **src/js/rating.js** - Editable JavaScript source for the rating field functionality
- **src/css/rating.css** - Editable CSS source for the rating field
- **dist/js/rating.js** - Built JavaScript shipped with the plugin
- **dist/css/rating.css** - Built CSS shipped with the plugin
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
1. Edit `src/js/rating.js` and `src/css/rating.css`
2. Run `npm run build`
3. Test the built output from `dist/js/rating.js` and `dist/css/rating.css`

The asset bundle always uses the built files from `dist/`.
