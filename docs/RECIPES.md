# Recipes

Step-by-step instructions for the most common Obel tasks. Each recipe ends with a validation step. Run `python3 bin/check.py` after any structural change.

---

## 1. Change the brand color

Edit `theme.json`. Find the `accent` entry under `settings.color.palette`:

```json
{ "slug": "accent", "name": "Accent", "color": "#B66E3C" }
```

Change the `color` value. The accent color is used for link hovers, sale prices, ratings stars, and accent backgrounds. To match the dark and high-contrast variations too, also update `accent` in `styles/dark.json` and `styles/high-contrast.json`.

Validate: `python3 bin/check.py --quick`

---

## 2. Change the body font

Edit `theme.json` -> `settings.typography.fontFamilies`. Find the `sans` entry:

```json
{
  "slug": "sans",
  "name": "Sans",
  "fontFamily": "'Inter', system-ui, -apple-system, ..."
}
```

To self-host:

1. Drop the font files into `assets/fonts/`.
2. Add a `fontFace` array under the entry:

```json
{
  "slug": "sans",
  "name": "Sans",
  "fontFamily": "'YourFont', system-ui, sans-serif",
  "fontFace": [
    {
      "fontFamily": "YourFont",
      "fontWeight": "400",
      "fontStyle": "normal",
      "src": ["file:./assets/fonts/yourfont-regular.woff2"]
    }
  ]
}
```

Validate: `python3 bin/check.py --quick`

---

## 3. Add a new color slug

Add an entry to `settings.color.palette` in `theme.json`:

```json
{ "slug": "highlight", "name": "Highlight", "color": "#F0E68C" }
```

The slug becomes available everywhere as:
- CSS variable: `var(--wp--preset--color--highlight)`
- Block attribute: `"backgroundColor":"highlight"` or `"textColor":"highlight"`
- Style preset reference: `"var:preset|color|highlight"`

Mirror the slug into every variation in `styles/` so the variation doesn't ship with a missing color.

Validate: `python3 bin/check.py --quick`

---

## 4. Style an existing core block

Decide what you want to override (background, padding, border, typography). Add an entry under `styles.blocks` in `theme.json`:

```json
"core/quote": {
  "border": {
    "left": {
      "color": "var(--wp--preset--color--accent)",
      "style": "solid",
      "width": "3px"
    }
  },
  "spacing": {
    "padding": { "left": "var(--wp--preset--spacing--lg)" }
  },
  "typography": {
    "fontStyle": "italic",
    "fontSize": "var(--wp--preset--font-size--md)"
  }
}
```

Always use design tokens. Check first that the block name is real:

```bash
python3 bin/validate-theme-json.py
```

---

## 5. Add a block style variation (e.g. "outlined" buttons)

A "block style" is a named preset the user can pick from the editor's block sidebar. Define it under `styles.blocks.<name>.variations`:

```json
"core/button": {
  "variations": {
    "outline": {
      "border": {
        "color": "var(--wp--preset--color--contrast)",
        "style": "solid",
        "width": "1px"
      },
      "color": {
        "background": "transparent",
        "text": "var(--wp--preset--color--contrast)"
      }
    }
  }
}
```

In block markup, apply the variation by adding `"className":"is-style-outline"` to the button block's attributes.

---

## 6. Add a new style variation (whole-theme look)

1. Copy `_examples/style-variation.json.txt` to `styles/your-name.json`.
2. Set the `title` field at the top.
3. Override `settings.color.palette` (and optionally `settings.typography` and `styles.elements`).
4. Skip keys you don't want to change. Variations are merged on top of `theme.json`.

The variation appears in Site Editor -> Styles automatically.

Validate: `python3 bin/check.py --quick`

---

## 7. Add a new starter pattern

1. Copy `_examples/pattern.php.txt` to `patterns/your-slug.php`.
2. Update the header (Title, Slug, Categories, Description, Keywords, Viewport Width).
3. Build the layout in the WP block editor first, copy the resulting markup into the file body.
4. Replace any hardcoded colors/sizes/spaces with token references (`var:preset|spacing|lg`, etc.).
5. Wrap any user-visible strings in `esc_html_e('text', 'obel')` for translation.

The pattern appears in the inserter under the "Obel" category as soon as the file exists.

Validate: `python3 bin/check.py --quick`

---

## 8. Override a WooCommerce template

WooCommerce 10.x uses block templates. To customize, copy the canonical template from WooCommerce trunk and edit it in `templates/`. The theme's version takes precedence over the plugin's.

Example: customize the cart page.

1. Find the canonical template at `plugins/woocommerce/templates/templates/page-cart.html` in [WooCommerce on GitHub](https://github.com/woocommerce/woocommerce).
2. The Obel theme already ships `templates/page-cart.html` based on this. Edit it.
3. Use only `core/*` and `woocommerce/*` blocks. Use design tokens for any custom colors/spacing.

Validate: `python3 bin/check.py --quick`

---

## 9. Adjust the layout widths

Two settings control all layout widths:

```json
"layout": {
  "contentSize": "720px",
  "wideSize": "1280px"
}
```

- `contentSize` controls the column width for prose content (post content, comments).
- `wideSize` controls the width of `align: wide` blocks (cover, columns, product collections).

Style variations may override these (e.g. `styles/editorial.json` shrinks `contentSize` to `640px`).

---

## 10. Bootstrap a new shop from this framework

```bash
python3 bin/clone.py acme           # creates ../acme/ with everything renamed
cd ../acme
# Edit theme.json -> palette and typography
# Replace screenshot.png with your own (1200x900)
# Update style.css Author, Author URI, Theme URI
python3 bin/check.py
```

The clone script handles the macOS/Linux/Windows differences in `sed`. See `bin/clone.py --help` for options.
