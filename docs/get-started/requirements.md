# Requirements

## System Requirements

| Requirement | Version |
|-------------|---------|
| [Craft CMS](https://craftcms.com/) | 5.10+ |
| [PHP](https://php.net/) | 8.2+ |
| [Formie](https://verbb.io/craft-plugins/formie) | 3.0+ |

## Dependencies

Composer pulls these packages automatically. Formie must also be installed and enabled in the Control Panel — the Rating field type only appears inside Formie's field list.

| Package | Version | Purpose |
|---------|---------|---------|
| [verbb/formie](https://verbb.io/craft-plugins/formie) | 3.0+ | The forms plugin the Rating field plugs into — required, install in CP |
| [lindemannrock/craft-plugin-base](https://github.com/LindemannRock/craft-plugin-base) | 5.38.2+ | Shared base plugin utilities (helpers, traits, layouts) |

## Optional

| Package | Purpose |
|---------|---------|
| [craftcms/feed-me](https://github.com/craftcms/feed-me) | Import Rating field values from a feed — see [Feed Me](../integrations/feed-me.md). Only needed if you import submissions. |
| [Redis](https://redis.io/) | One possible backend for Craft's application cache on load-balanced or multi-server setups — see [Caching](../feature-tour/caching.md). Redis is not required: the plugin can use durable plugin-owned files, another suitable Craft cache backend, or safe recomputation when no persistent cache is available. |
