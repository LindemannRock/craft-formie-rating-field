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

You can set any of these as global variables (remove the `fui-` prefix):

### Star Rating Colors

```css
:root {
  --fui-rating-star-color: #f59e0b;           /* Filled star color */
  --fui-rating-star-empty-color: #e5e7eb;     /* Empty star color */
  --fui-rating-star-hover-scale: 1.1;         /* Scale on hover */
}
```

### Star Rating Sizes

```css
:root {
  --fui-rating-star-size-small: 20px;         /* Small star size */
  --fui-rating-star-size-medium: 24px;        /* Medium star size */
  --fui-rating-star-size-large: 32px;         /* Large star size */
  --fui-rating-star-size-xlarge: 40px;        /* Extra large star size */
}
```

### NPS Rating Colors

```css
:root {
  --fui-rating-nps-border: #e5e7eb;           /* Default border */
  --fui-rating-nps-bg: white;                 /* Default background */
  --fui-rating-nps-color: #6b7280;            /* Default text color */
  --fui-rating-nps-selected-bg: #2d5016;      /* Selected background */
  --fui-rating-nps-selected-border: #2d5016;  /* Selected border */
  --fui-rating-nps-selected-color: white;     /* Selected text color */
  --fui-rating-nps-hover-bg: #f9fafb;         /* Hover background */
  --fui-rating-nps-hover-border: #9ca3af;     /* Hover border */
  --fui-rating-nps-selected-hover-bg: #365a1c; /* Selected hover background */
}
```

### NPS Rating Sizes

```css
:root {
  --fui-rating-nps-size-small: 32px;          /* Small box size */
  --fui-rating-nps-size-medium: 40px;         /* Medium box size */
  --fui-rating-nps-size-large: 48px;          /* Large box size */
  --fui-rating-nps-size-xlarge: 56px;         /* Extra large box size */
  --fui-rating-nps-font-small: 11px;          /* Small font size */
  --fui-rating-nps-font-medium: 12px;         /* Medium font size */
  --fui-rating-nps-font-large: 14px;          /* Large font size */
  --fui-rating-nps-font-xlarge: 16px;         /* Extra large font size */
}
```

### Emoji Rating

```css
:root {
  --fui-rating-emoji-grayscale: 100%;         /* Grayscale for unselected */
  --fui-rating-emoji-opacity: 0.5;            /* Opacity for unselected */
  --fui-rating-emoji-selected-scale: 1.1;     /* Scale when selected */
  --fui-rating-emoji-hover-scale: 1.05;       /* Scale on hover */
}
```

### Emoji Rating Sizes

```css
:root {
  --fui-rating-emoji-size-small: 24px;        /* Small emoji size */
  --fui-rating-emoji-size-medium: 32px;       /* Medium emoji size */
  --fui-rating-emoji-size-large: 40px;        /* Large emoji size */
  --fui-rating-emoji-size-xlarge: 48px;       /* Extra large emoji size */
}
```

### Emoji Sentiment Colors

When using `emojiRenderMode: 'noto-simple'`, emoji colors are automatically applied based on position in the scale (0-10, 1-5, etc.):

```css
:root {
  --fui-rating-emoji-color-1: #ef4444;        /* Red - Very bad (0-20%) */
  --fui-rating-emoji-color-2: #f97316;        /* Orange - Bad (21-40%) */
  --fui-rating-emoji-color-3: #eab308;        /* Yellow - Neutral (41-60%) */
  --fui-rating-emoji-color-4: #84cc16;        /* Light green - Good (61-80%) */
  --fui-rating-emoji-color-5: #22c55e;        /* Green - Very good (81-100%) */
}
```

**Note**: These colors only apply when using the Noto Emoji (simple) web font mode. System emojis and Noto Color Emoji maintain their native colors.

### General Settings
```css
:root {
  --fui-rating-transition: all 0.2s ease;     /* Transition effect */
  --fui-rating-focus-color: currentColor;     /* Focus outline color */
  --fui-rating-gap-small: 2px;                /* Small gap between items */
  --fui-rating-gap-medium: 4px;               /* Medium gap */
  --fui-rating-gap-large: 6px;                /* Large gap */
}
```

### Labels
```css
:root {
  --fui-rating-label-color: #6b7280;          /* Label text color */
  --fui-rating-label-size: 0.875rem;          /* Selected label size */
  --fui-rating-endpoint-size: 0.75rem;        /* Endpoint label size */
}
```

### Error State
```css
:root {
  --fui-rating-error-color: #dc2626;          /* Error color */
}
```

### Animation
```css
:root {
  --fui-rating-animation-scale: 1.2;          /* Pulse animation scale */
  --fui-rating-animation-duration: 0.3s;      /* Animation duration */
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

### Method 2: Field-Specific Override
Override the `fui-` prefixed variables for more specific control:

```css
:root {
  /* Only change specific field variables */
  --fui-rating-star-color: #10b981;
  --fui-rating-star-empty-color: #d1d5db;
  
  /* Change NPS colors */
  --fui-rating-nps-selected-bg: #3b82f6;
  --fui-rating-nps-selected-border: #3b82f6;
}
```

### Method 3: Specific Form Override
Target specific forms:

```css
#my-special-form {
  --fui-rating-star-color: #ef4444;
  --fui-rating-emoji-selected-scale: 1.3;
}
```

### Method 4: Dark Mode Support
Add dark mode variations:

```css
@media (prefers-color-scheme: dark) {
  :root {
    --fui-rating-star-empty-color: #374151;
    --fui-rating-nps-bg: #1f2937;
    --fui-rating-nps-color: #f3f4f6;
    --fui-rating-label-color: #d1d5db;
  }
}
```

## Examples

### Brand Colors Example

```css
:root {
  /* Green theme */
  --fui-rating-star-color: #2d5016;
  --fui-rating-nps-selected-bg: #2d5016;
  --fui-rating-nps-selected-border: #2d5016;
  --fui-rating-error-color: #991b1b;
}
```

### Custom Sizes Example

```css
:root {
  /* Larger sizes for touch devices */
  --fui-rating-star-size-small: 28px;
  --fui-rating-star-size-medium: 36px;
  --fui-rating-star-size-large: 44px;
  --fui-rating-star-size-xlarge: 52px;
  
  /* Bigger NPS boxes */
  --fui-rating-nps-size-medium: 48px;
  --fui-rating-nps-font-medium: 14px;
  
  /* Bigger emojis */
  --fui-rating-emoji-size-medium: 40px;
}
```

### Subtle Animation Example
```css
:root {
  /* Slower, subtler animations */
  --fui-rating-transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  --fui-rating-star-hover-scale: 1.05;
  --fui-rating-emoji-hover-scale: 1.02;
  --fui-rating-animation-duration: 0.5s;
  --fui-rating-animation-scale: 1.1;
}
```

### High Contrast Example
```css
:root {
  /* High contrast for accessibility */
  --fui-rating-star-color: #000000;
  --fui-rating-star-empty-color: #ffffff;
  --fui-rating-nps-border: #000000;
  --fui-rating-nps-selected-bg: #000000;
  --fui-rating-nps-selected-color: #ffffff;
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

## Notes

- All variables have fallback values, so the field will work even without custom properties support
- Changes to CSS variables take effect immediately without needing to modify the original CSS
- Variables cascade, so you can override at any level (global, form, or field)
- The `currentColor` value is used for star SVGs, allowing them to inherit text color