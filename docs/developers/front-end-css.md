# Front-end CSS

The rating widget on a live form is styled by the plugin's stylesheet, and almost every visual detail — colours, sizes, gaps, hover and selected states, animation — is exposed as a CSS custom property you can override. You don't have to fight `!important` or rewrite selectors; set a handful of variables in your own CSS and the widget picks them up.

## How overriding works

Each internal `--fui-rating-*` property falls back to a public `--rating-*` variable, then to a built-in default:

```css
--fui-rating-star-color: var(--rating-star-color, #f59e0b);
```

So to recolour stars site-wide, set the **`--rating-*`** variable on a scope that contains the form — `:root`, a theme wrapper, or a single form:

```css
:root {
    --rating-star-color: #e11d48;        /* selected/filled stars */
    --rating-star-empty-color: #fecdd3;  /* empty stars */
    --rating-nps-selected-bg: #1d4ed8;   /* selected NPS box */
}
```

Scope it tighter to theme one form:

```css
[data-fui-form="feedbackForm"] {
    --rating-star-color: gold;
}
```

## Widget structure

The classes are stable hooks if you need to go beyond variables:

| Class | Element |
|-------|---------|
| `.fui-rating-field` | Wrapper around the whole widget |
| `.fui-rating-visual` | The row of interactive items |
| `.fui-rating-item` | A single star / emoji / NPS box |
| `.fui-rating-star`, `.fui-rating-emoji`, `.fui-rating-nps` | Type modifier on the widget |
| `.fui-rating-size-{small\|medium\|large\|xlarge}` | Size modifier |
| `.fui-rating-selected` | The selected item(s) |
| `.fui-rating-emoji-noto-color`, `.fui-rating-emoji-noto-simple`, `.fui-rating-emoji-webfont` | Emoji render mode |
| `.fui-rating-endpoints`, `.fui-rating-start-label`, `.fui-rating-end-label` | Endpoint labels |
| `.fui-rating-selected-label` | The selected-value label |
| `.fui-rating-loading` | Shown briefly before the widget initializes |

## Variables reference

Set the `--rating-*` name (the override layer). Defaults shown.

### Stars

| Variable | Default |
|----------|---------|
| `--rating-star-color` | `#f59e0b` |
| `--rating-star-empty-color` | `#e5e7eb` |
| `--rating-star-hover-scale` | `1.05` |
| `--rating-star-selected-scale` | `1.2` |
| `--rating-star-size-small` / `-medium` / `-large` / `-xlarge` | `20px` / `24px` / `32px` / `40px` |

### NPS boxes

| Variable | Default |
|----------|---------|
| `--rating-nps-border` | `#e5e7eb` |
| `--rating-nps-bg` | `white` |
| `--rating-nps-color` | `#6b7280` |
| `--rating-nps-selected-bg` | `#2d5016` |
| `--rating-nps-selected-border` | `#2d5016` |
| `--rating-nps-selected-color` | `white` |
| `--rating-nps-hover-bg` | `#f9fafb` |
| `--rating-nps-hover-border` | `#9ca3af` |
| `--rating-nps-selected-hover-bg` | `#365a1c` |
| `--rating-nps-hover-scale` / `--rating-nps-selected-scale` | `1.05` / `1.2` |
| `--rating-nps-size-small` / `-medium` / `-large` / `-xlarge` | `32px` / `40px` / `48px` / `56px` |
| `--rating-nps-font-family` | `inherit` |
| `--rating-nps-font-small` / `-medium` / `-large` / `-xlarge` | `11px` / `12px` / `14px` / `16px` |

### Emoji

| Variable | Default |
|----------|---------|
| `--rating-emoji-grayscale` | `100%` (unselected emoji are greyed) |
| `--rating-emoji-opacity` | `0.5` |
| `--rating-emoji-hover-scale` | `1.05` |
| `--rating-emoji-selected-scale` | `1.2` |
| `--rating-emoji-font-family` | platform emoji stack |
| `--rating-emoji-color-1` … `-5` | `#ef4444`, `#f97316`, `#eab308`, `#84cc16`, `#22c55e` |
| `--rating-emoji-size-small` / `-medium` / `-large` / `-xlarge` | `24px` / `32px` / `40px` / `48px` |

### Layout, labels, states

| Variable | Default |
|----------|---------|
| `--rating-transition` | `all 0.2s ease` |
| `--rating-focus-color` | `currentColor` |
| `--rating-border-radius` | `0.25rem` |
| `--rating-gap-small` / `-medium` / `-large` / `-xlarge` | `2px` / `4px` / `6px` / `8px` |
| `--rating-label-color` | `#6b7280` |
| `--rating-label-size` | `0.875rem` |
| `--rating-endpoint-size` | `0.75rem` |
| `--rating-selected-label-size` | `0.875rem` |
| `--rating-selected-label-color` | `#6b7280` |
| `--rating-selected-label-weight` | `400` |
| `--rating-selected-label-font-family` | `inherit` |
| `--rating-error-color` | `#dc2626` |
| `--rating-animation-scale` | `1.2` |
| `--rating-animation-duration` | `0.3s` |

## Next steps

- [The Rating field](../feature-tour/rating-field.md) — the size and type options these styles target
- [Displaying ratings in templates](../template-guides/displaying-ratings.md)
