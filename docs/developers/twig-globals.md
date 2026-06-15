# Twig Globals

Formie Rating Field provides the following global variables in your Twig templates.

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

