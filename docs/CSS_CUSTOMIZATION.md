# CSS Customization Guide for Formie Rating Field

The rating field CSS uses a cascading CSS custom properties system with three levels of customization:

1. **Global variables** (without `fui-` prefix) - Set these in your global CSS
2. **Field-specific variables** (with `fui-` prefix) - Automatically use global variables if available
3. **Hardcoded fallbacks** - Final fallback values

## How the Cascade Works

```css
/* In your global.css */
:root {
  --rating-star-color: #10b981;  /* This will be used by all rating fields */
}

/* The field CSS automatically uses it */
--fui-rating-star-color: var(--rating-star-color, #f59e0b);

/* Then the actual CSS rule uses the fui variable */
color: var(--fui-rating-star-color, #f59e0b);
```

### Quick Start Example for global.css

```css
/* Add these to your global.css to customize all rating fields */
:root {
  /* Brand colors */
  --rating-star-color: #2d5016;
  --rating-nps-selected-bg: #2d5016;
  --rating-nps-selected-border: #2d5016;
  
  /* Custom sizes */
  --rating-star-size-medium: 28px;
  --rating-nps-size-medium: 44px;
  --rating-emoji-size-medium: 36px;
  
  /* Animations */
  --rating-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --rating-animation-scale: 1.15;
}
```

## Available CSS Variables

Set these global variables in your CSS to customize the rating field appearance:

### Star Rating Colors

```css
:root {
  --rating-star-color: #f59e0b;           /* Filled star color */
  --rating-star-empty-color: #e5e7eb;     /* Empty star color */
}
```

### Star Rating Sizes

```css
:root {
  --rating-star-size-small: 20px;         /* Small star size */
  --rating-star-size-medium: 24px;        /* Medium star size */
  --rating-star-size-large: 32px;         /* Large star size */
  --rating-star-size-xlarge: 40px;        /* Extra large star size */
}
```

### NPS Rating Colors

```css
:root {
  --rating-nps-border: #e5e7eb;           /* Default border */
  --rating-nps-bg: white;                 /* Default background */
  --rating-nps-color: #6b7280;            /* Default text color */
  --rating-nps-selected-bg: #2d5016;      /* Selected background */
  --rating-nps-selected-border: #2d5016;  /* Selected border */
  --rating-nps-selected-color: white;     /* Selected text color */
  --rating-nps-hover-bg: #f9fafb;         /* Hover background */
  --rating-nps-hover-border: #9ca3af;     /* Hover border */
  --rating-nps-selected-hover-bg: #365a1c; /* Selected hover background */
}
```

### NPS Rating Sizes

```css
:root {
  --rating-nps-size-small: 32px;          /* Small box size */
  --rating-nps-size-medium: 40px;         /* Medium box size */
  --rating-nps-size-large: 48px;          /* Large box size */
  --rating-nps-size-xlarge: 56px;         /* Extra large box size */
  --rating-nps-font-small: 11px;          /* Small font size */
  --rating-nps-font-medium: 12px;         /* Medium font size */
  --rating-nps-font-large: 14px;          /* Large font size */
  --rating-nps-font-xlarge: 16px;         /* Extra large font size */
  --rating-nps-font-family: inherit;      /* Font family for NPS numbers */
}
```

### Emoji Rating

```css
:root {
  --rating-emoji-grayscale: 100%;         /* Grayscale for unselected */
  --rating-emoji-opacity: 0.5;            /* Opacity for unselected */
  --rating-emoji-font-family: "Apple Color Emoji", "Segoe UI Emoji", sans-serif; /* Emoji font stack */
}
```

### Emoji Rating Sizes

```css
:root {
  --rating-emoji-size-small: 24px;        /* Small emoji size */
  --rating-emoji-size-medium: 32px;       /* Medium emoji size */
  --rating-emoji-size-large: 40px;        /* Large emoji size */
  --rating-emoji-size-xlarge: 48px;       /* Extra large emoji size */
}
```

### Emoji Sentiment Colors

When using `emojiRenderMode: 'noto-simple'`, emoji colors are automatically applied based on position in the scale (0-10, 1-5, etc.):

```css
:root {
  --rating-emoji-color-1: #ef4444;        /* Red - Very bad (0-20%) */
  --rating-emoji-color-2: #f97316;        /* Orange - Bad (21-40%) */
  --rating-emoji-color-3: #eab308;        /* Yellow - Neutral (41-60%) */
  --rating-emoji-color-4: #84cc16;        /* Light green - Good (61-80%) */
  --rating-emoji-color-5: #22c55e;        /* Green - Very good (81-100%) */
}
```

**Note**: These colors only apply when using the Noto Emoji (simple) web font mode. System emojis and Noto Color Emoji maintain their native colors.

### General Settings
```css
:root {
  --rating-transition: all 0.2s ease;     /* Transition effect */
  --rating-focus-color: currentColor;     /* Focus outline color */
  --rating-border-radius: 0.25rem;        /* Border radius for buttons */
  --rating-gap-small: 2px;                /* Small gap between items */
  --rating-gap-medium: 4px;               /* Medium gap */
  --rating-gap-large: 6px;                /* Large gap */
}
```

### Labels
```css
:root {
  --rating-label-color: #6b7280;          /* Label text color */
  --rating-label-size: 0.875rem;          /* Selected label size */
  --rating-endpoint-size: 0.75rem;        /* Endpoint label size */
}
```

### Error State
```css
:root {
  --rating-error-color: #dc2626;          /* Error color */
}
```

### Animation
```css
:root {
  --rating-animation-scale: 1.2;          /* Pulse animation scale */
  --rating-animation-duration: 0.3s;      /* Animation duration */
}
```

## How to Customize

### Method 1: Global Variables (Recommended)
Set global variables in your main CSS file:

```css
:root {
  /* These will automatically be used by all rating fields */
  --rating-star-color: #10b981;
  --rating-star-empty-color: #d1d5db;
  
  /* Change NPS colors globally */
  --rating-nps-selected-bg: #3b82f6;
  --rating-nps-selected-border: #3b82f6;
  
  /* Change sizes globally */
  --rating-star-size-medium: 28px;
  --rating-emoji-size-medium: 36px;
}
```

### Method 2: Specific Form Override
Target specific forms:

```css
#my-special-form {
  --rating-star-color: #ef4444;
  --rating-nps-selected-bg: #ef4444;
}
```

### Method 3: Dark Mode Support
Add dark mode variations:

```css
@media (prefers-color-scheme: dark) {
  :root {
    --rating-star-empty-color: #374151;
    --rating-nps-bg: #1f2937;
    --rating-nps-color: #f3f4f6;
    --rating-label-color: #d1d5db;
  }
}
```

## Examples

### Brand Colors Example

```css
:root {
  /* Green theme */
  --rating-star-color: #2d5016;
  --rating-nps-selected-bg: #2d5016;
  --rating-nps-selected-border: #2d5016;
  --rating-error-color: #991b1b;
}
```

### Custom Sizes Example

```css
:root {
  /* Larger sizes for touch devices */
  --rating-star-size-small: 28px;
  --rating-star-size-medium: 36px;
  --rating-star-size-large: 44px;
  --rating-star-size-xlarge: 52px;

  /* Bigger NPS boxes */
  --rating-nps-size-medium: 48px;
  --rating-nps-font-medium: 14px;

  /* Bigger emojis */
  --rating-emoji-size-medium: 40px;
}
```

### Subtle Animation Example
```css
:root {
  /* Slower, subtler animations */
  --rating-transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  --rating-animation-duration: 0.5s;
  --rating-animation-scale: 1.1;
}
```

### High Contrast Example
```css
:root {
  /* High contrast for accessibility */
  --rating-star-color: #000000;
  --rating-star-empty-color: #ffffff;
  --rating-nps-border: #000000;
  --rating-nps-selected-bg: #000000;
  --rating-nps-selected-color: #ffffff;
}
```

### Custom Emoji Sentiment Colors Example
```css
:root {
  /* Custom brand colors for emoji sentiment gradient */
  /* These apply when using emojiRenderMode: 'noto-simple' */
  --rating-emoji-color-1: #dc2626;   /* Red - Very bad */
  --rating-emoji-color-2: #f59e0b;   /* Amber - Bad */
  --rating-emoji-color-3: #fbbf24;   /* Yellow - Neutral */
  --rating-emoji-color-4: #10b981;   /* Emerald - Good */
  --rating-emoji-color-5: #059669;   /* Green - Very good */
}
```

## All Variables Reference

Complete list of all available CSS variables with their default values:

```css
:root {
  /* ===== Star Rating ===== */
  --rating-star-color: #f59e0b;
  --rating-star-empty-color: #e5e7eb;
  --rating-star-size-small: 20px;
  --rating-star-size-medium: 24px;
  --rating-star-size-large: 32px;
  --rating-star-size-xlarge: 40px;

  /* ===== NPS Rating ===== */
  --rating-nps-border: #e5e7eb;
  --rating-nps-bg: white;
  --rating-nps-color: #6b7280;
  --rating-nps-selected-bg: #2d5016;
  --rating-nps-selected-border: #2d5016;
  --rating-nps-selected-color: white;
  --rating-nps-hover-bg: #f9fafb;
  --rating-nps-hover-border: #9ca3af;
  --rating-nps-selected-hover-bg: #365a1c;
  --rating-nps-size-small: 32px;
  --rating-nps-size-medium: 40px;
  --rating-nps-size-large: 48px;
  --rating-nps-size-xlarge: 56px;
  --rating-nps-font-small: 11px;
  --rating-nps-font-medium: 12px;
  --rating-nps-font-large: 14px;
  --rating-nps-font-xlarge: 16px;
  --rating-nps-font-family: inherit;

  /* ===== Emoji Rating ===== */
  --rating-emoji-grayscale: 100%;
  --rating-emoji-opacity: 0.5;
  --rating-emoji-font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
  --rating-emoji-size-small: 24px;
  --rating-emoji-size-medium: 32px;
  --rating-emoji-size-large: 40px;
  --rating-emoji-size-xlarge: 48px;

  /* ===== Emoji Sentiment Colors (noto-simple mode only) ===== */
  --rating-emoji-color-1: #ef4444;  /* Red - Very bad (0-20%) */
  --rating-emoji-color-2: #f97316;  /* Orange - Bad (21-40%) */
  --rating-emoji-color-3: #eab308;  /* Yellow - Neutral (41-60%) */
  --rating-emoji-color-4: #84cc16;  /* Light green - Good (61-80%) */
  --rating-emoji-color-5: #22c55e;  /* Green - Very good (81-100%) */

  /* ===== General ===== */
  --rating-transition: all 0.2s ease;
  --rating-focus-color: currentColor;
  --rating-border-radius: 0.25rem;
  --rating-gap-small: 2px;
  --rating-gap-medium: 4px;
  --rating-gap-large: 6px;

  /* ===== Labels ===== */
  --rating-label-color: #6b7280;
  --rating-label-size: 0.875rem;
  --rating-endpoint-size: 0.75rem;

  /* ===== Error State ===== */
  --rating-error-color: #dc2626;

  /* ===== Animation ===== */
  --rating-animation-scale: 1.2;
  --rating-animation-duration: 0.3s;
}
```

## Notes

- All variables have fallback values, so the field will work even without custom properties support
- Changes to CSS variables take effect immediately without needing to modify the original CSS
- Variables cascade, so you can override at any level (global, form, or field)
- The `currentColor` value is used for star SVGs, allowing them to inherit text color