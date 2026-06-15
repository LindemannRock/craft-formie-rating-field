# Translations

Formie Rating Field includes full translations for 12 languages out of the box.

## Supported languages

| Language | Code |
|----------|------|
| English | `en` |
| German | `de` |
| French | `fr` |
| Dutch | `nl` |
| Spanish | `es` |
| Arabic | `ar` |
| Italian | `it` |
| Portuguese | `pt` |
| Japanese | `ja` |
| Swedish | `sv` |
| Danish | `da` |
| Norwegian | `no` |

Translations are applied automatically based on the user's preferred language in Craft's Control Panel settings.

## Overriding translations

Override any string by creating a static translation file in your project under the `formie-rating-field` category:

```
translations/
└── de/
    └── formie-rating-field.php
```

```php
<?php

return [
    'Star Rating' => 'Sternebewertung',  // your override
];
```

Only the keys you include are replaced — every other string uses the plugin's built-in translation.

See [Craft's Static Translation Strings](https://craftcms.com/docs/5.x/system/sites.html#static-message-translations) for details.

## Contributing translations

Found a translation error or want to improve one? [Open an issue](https://github.com/LindemannRock/craft-formie-rating-field/issues) with:

- The language affected
- The current (incorrect) string
- Your suggested correction
