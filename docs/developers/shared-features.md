# Shared Features

Formie Rating Field uses the following shared libraries and features.

## `lindemannrock/base`

| Feature | Description |
|---------|-------------|
| `PluginHelper::bootstrap()` | Initializes base module, Twig globals, and logging configuration |
| `SettingsConfigTrait` | Config file override detection and log level validation |
| `SettingsDisplayNameTrait` | Standardized plugin name helper methods |

### Details

**PluginHelper::bootstrap()**

Provides plugin name helpers in Twig templates (see Twig Globals section)

**SettingsConfigTrait**

Settings can be overridden via config/{plugin-handle}.php. Debug logging requires devMode.

**SettingsDisplayNameTrait**

Provides getDisplayName(), getFullName(), getPluralDisplayName(), etc.

---

