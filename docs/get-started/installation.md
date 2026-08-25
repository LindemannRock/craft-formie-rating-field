# Installation & Setup

> [!IMPORTANT]
> Formie Rating Field needs [Formie](https://verbb.io/craft-plugins/formie) installed and enabled. Composer pulls it in automatically; install it in the Control Panel under **Settings → Plugins**. The Rating field type only appears in Formie's field list once Formie is enabled.

## Composer

Add the package to your project using Composer and the command line.

1. Open your terminal and go to your Craft project:

```bash
cd /path/to/project
```

2. Then tell Composer to require the plugin, and Craft to install it:

```bash title="Composer"
composer require lindemannrock/craft-formie-rating-field && php craft plugin/install formie-rating-field
```

```bash title="DDEV"
ddev composer require lindemannrock/craft-formie-rating-field && ddev craft plugin/install formie-rating-field
```

After installing, a **Formie Rating** section appears in the Control Panel, and the **Rating** field type becomes available in Formie's form builder.

## Post-Install Setup

Formie Rating Field works as soon as it's installed — there's no salt to generate or templates to copy.

### Review configuration

The plugin's settings (field defaults, statistics interface, and caching) are optional; sensible defaults apply out of the box. See [Configuration](configuration.md) for the settings reference, config-file overrides, and environment-specific options.

## Quick Start

See [Quickstart](quickstart.md) for the fastest path from install to your first rating field on a form.
