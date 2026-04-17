# Anti-Patterns

Concrete bad-code / good-code pairs. Read this before editing if uncertain.

## 1. Inventing block names

```diff
- "core/time-to-read": { ... }              // not a real block
+ "core/post-time-to-read": { ... }         // verified

- "core/comments-link": { ... }             // not a real block
+ "core/post-comments-link": { ... }        // verified

- "core/term-query": { ... }                // not a real block
+ "core/terms-query": { ... }               // verified
```

Always run `python3 bin/validate-theme-json.py` after editing `theme.json`.

---

## 2. Hardcoding values instead of using tokens

```diff
- "color": { "background": "#FAFAF7" }
+ "color": { "background": "var(--wp--preset--color--base)" }

- "spacing": { "padding": { "top": "24px" } }
+ "spacing": { "padding": { "top": "var:preset|spacing|lg" } }

- "typography": { "fontSize": "1.5rem" }
+ "typography": { "fontSize": "var(--wp--preset--font-size--md)" }
```

Why: hardcoded values defeat the entire design-token system. Project clones can't restyle without editing every file.

To discover tokens: `python3 bin/list-tokens.py`

---

## 3. Reaching for `!important`

```diff
- /* in the css escape hatch */
- color: var(--wp--preset--color--accent) !important;
+ /* fix the specificity at the source: scope the rule narrower, or move it into theme.json's per-block styles */
```

`!important` is a sign the design tokens or block scope are wrong. Fix the cause, not the symptom.

---

## 4. Adding CSS files

```diff
- assets/css/custom.css
- add_action('wp_enqueue_scripts', 'obel_enqueue_styles');
+ // Move the styling into theme.json -> styles.blocks.<block-name>
```

Obel has zero CSS files except `style.css` (which is just the theme header). The block style engine handles everything.

---

## 5. Registering a custom block

```diff
- register_block_type(__DIR__ . '/blocks/my-card');
+ // Build the layout as a pattern instead. patterns/<your-card>.php
+ // Or compose existing blocks: core/group + core/columns + core/image
```

Custom blocks require JS, which means a build step, which violates the "pure PHP + JSON + HTML" rule.

---

## 6. Enqueuing scripts or styles

```diff
- add_action('wp_enqueue_scripts', static function () {
-     wp_enqueue_style('obel-extras', get_template_directory_uri() . '/assets/extras.css');
-     wp_enqueue_script('obel-js', get_template_directory_uri() . '/assets/main.js');
- });
+ // Don't. The theme has no CSS files and no JS bundles.
+ // For new visual behavior, use core blocks that ship the interactivity (Accordion, Details, Modal, etc.).
```

---

## 7. Using non-core, non-WC blocks

```diff
- <!-- wp:acme/fancy-hero -->
+ <!-- wp:cover --> + child blocks

- <!-- wp:my-plugin/product-grid -->
+ <!-- wp:woocommerce/product-collection -->
```

Run `python3 bin/check.py` to scan for forbidden namespaces.

---

## 8. Wrong PHP escapes inside single-quoted strings

```diff
- esc_html_e( 'This season\u2019s picks', 'obel' );  // renders literally as: This season\u2019s picks
+ esc_html_e( 'This season\'s picks', 'obel' );     // renders as: This season's picks
```

In PHP single-quoted strings, only `\\` and `\'` are processed as escapes. `\u2019` becomes the literal four characters.

---

## 9. Marketing tone in user-facing text

```diff
- "A robust, comprehensive solution that seamlessly leverages WordPress's powerful tapestry..."
+ "A block-only WooCommerce starter theme. Pure PHP + JSON + HTML, no build step."
```

`bin/check.py` will flag the words: `leverage`, `robust`, `comprehensive`, `seamless`, `delve`, `tapestry`, and em-dashes (`\u2014`) in `README.md`, `readme.txt`, and `style.css`.

---

## 10. Missing `$schema` line in JSON

```diff
  {
+     "$schema": "https://schemas.wp.org/trunk/theme.json",
      "version": 3,
      "title": "Dark",
      "settings": { ... }
  }
```

Without `$schema`, your editor cannot autocomplete or validate the file in real time. Always include it for `theme.json` and every file in `styles/`.

---

## 11. Custom block style variation in PHP instead of theme.json

```diff
- // functions.php
- register_block_style('core/button', [
-     'name' => 'outline',
-     'label' => 'Outline',
- ]);

+ // theme.json -> styles.blocks.core/button.variations.outline
```

Single source of truth: `theme.json`. PHP block-style registration creates a second source the design system has to track.

---

## 12. Adding `add_editor_style()` because "the editor needs the styles"

```diff
- add_editor_style('assets/editor.css');
+ // Don't. theme.json automatically syncs styles to the block editor.
+ // There is no separate editor stylesheet to load.
```

---

## 13. Naming a new token slug inconsistently

```diff
- // theme.json palette additions
- { "slug": "Gold-Accent", "name": "Gold", "color": "#D4AF37" }       // wrong: capitals + reserved suffix
- { "slug": "gold_accent", "name": "Gold", "color": "#D4AF37" }       // wrong: underscore
+ { "slug": "gold", "name": "Gold", "color": "#D4AF37" }              // lowercase, hyphen-separated, no role suffix
```

Slug rules:
- All lowercase.
- Hyphens only as separators.
- Don't append the category to the slug name (`gold`, not `gold-color`).

---

## 14. Putting comments in JSON

```diff
- {
-     // primary color used for buttons
-     "primary": "#1A1A1A"
- }
+ // JSON does not allow comments. If a value needs explanation, document it in docs/TOKENS.md.
```

`bin/check.py` will fail JSON parsing if you do this.

---

## 15. Forgetting the closing comment in block markup

```diff
  <!-- wp:group {"layout":{"type":"constrained"}} -->
  <div class="wp-block-group">
      <!-- wp:paragraph -->
      <p>...</p>
-     <!-- /wp:paragraph -->     // missing
+     <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->
```

The block parser is strict. A missing closing comment breaks the entire template silently in the editor.

For self-closing blocks (no inner content), use `/-->`:

```html
<!-- wp:site-title /-->
```
