![Formie Rating Field](docs/images/hero.webp)

# Formie Rating Field for Craft CMS

[![Latest Version](https://img.shields.io/packagist/v/lindemannrock/craft-formie-rating-field.svg)](https://packagist.org/packages/lindemannrock/craft-formie-rating-field)
[![Craft CMS](https://img.shields.io/badge/Craft%20CMS-5.0+-orange.svg)](https://craftcms.com/)
[![Formie](https://img.shields.io/badge/Formie-3.0+-purple.svg)](https://verbb.io/craft-plugins/formie)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/lindemannrock/craft-formie-rating-field.svg)](LICENSE)

A Craft CMS plugin that adds star, emoji, and NPS (Net Promoter Score) rating field types to Verbb's Formie, plus a statistics dashboard that turns submissions into averages, NPS scores, distributions, and trends.

## License

This is a commercial plugin licensed under the [Craft License](https://craftcms.github.io/license/). It will be available on the [Craft Plugin Store](https://plugins.craftcms.com) soon. See [LICENSE.md](LICENSE.md) for details.

## ⚠️ Pre-Release

This plugin is in active development and not yet available on the Craft Plugin Store. Features and APIs may change before the initial public release.

## Features

- **Three rating types** — classic star ratings, expressive emoji, and 0–10 NPS number boxes
- **Field options** — size, value range, half-star ratings, endpoint labels, custom emoji labels, single-emoji selection, and a selected-value label
- **Emoji render modes** — native system emoji, Noto Color Emoji, or Noto Emoji
- **Google Review prompt** — turn a high rating into a tiered thank-you and a button to your Google review page
- **Statistics dashboard** — type-aware averages, NPS scoring, value distributions, trend charts, grouping by another form field, and date-range / per-site filtering
- **Craft dashboard widget** — optional, site-scoped rating statistics widget for quick access to active rating forms
- **Exports** — Excel (multi-sheet), CSV (zipped per section), or JSON
- **Caching** — file system or Redis, automatic invalidation on new submissions, optional scheduled pre-generation, and CLI management
- **Feed Me import** — map a feed column onto a Rating field when Feed Me is installed
- **12 languages** — translated out of the box

## Requirements

- Craft CMS 5.0 or greater
- PHP 8.2 or greater
- Formie 3.0 or greater

## Installation

### Via Composer

```bash
composer require lindemannrock/craft-formie-rating-field
```

```bash
php craft plugin/install formie-rating-field
```

### Using DDEV

```bash
ddev composer require lindemannrock/craft-formie-rating-field
```

```bash
ddev craft plugin/install formie-rating-field
```

## Documentation

Full documentation is available in the [docs](docs/) folder.

## Support

- **Issues**: [GitHub Issues](https://github.com/LindemannRock/craft-formie-rating-field/issues)
- **Email**: [support@lindemannrock.com](mailto:support@lindemannrock.com)

## License

This plugin is licensed under the [Craft License](https://craftcms.github.io/license/). See [LICENSE.md](LICENSE.md) for details.

---

Developed by [LindemannRock](https://lindemannrock.com)

Built for use with [Formie](https://verbb.io/craft-plugins/formie) by Verbb
