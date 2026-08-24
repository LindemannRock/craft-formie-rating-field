# Twig globals

Use the `ratingHelper` global when a template needs the plugin's configured display name rather than a hard-coded label.

## `ratingHelper`

*Provided by `lindemannrock/base`*

| Property | Description |
|----------|-------------|
| `ratingHelper.displayName` | Display name (singular, without "Manager") |
| `ratingHelper.pluralDisplayName` | Plural display name (without "Manager") |
| `ratingHelper.fullName` | Full plugin name (as configured) |
| `ratingHelper.lowerDisplayName` | Lowercase display name (singular) |
| `ratingHelper.pluralLowerDisplayName` | Lowercase plural display name |

### Examples

```twig
{{ ratingHelper.displayName }}
{{ ratingHelper.pluralDisplayName }}
{{ ratingHelper.fullName }}
{{ ratingHelper.lowerDisplayName }}
{{ ratingHelper.pluralLowerDisplayName }}
```

---
