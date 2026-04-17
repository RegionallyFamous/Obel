# Project Structure

Read this file to orient yourself in the codebase. Every file and folder is listed with one line about what it does and when you'd touch it.

```
obel/
|
|-- INDEX.md               Auto-generated. Single-file map of every template/part/pattern/variation/token/block style. LLMs read this first.
|-- SYSTEM-PROMPT.md       Paste-in system prompt for any LLM working in this repo.
|-- AGENTS.md              Full constraints + workflow recipes for AI agents. Read on demand.
|-- README.md              Human-facing overview. Install, clone, constraints, validate.
|-- CHANGELOG.md           Per-version change log. Append entries here, not in readme.txt.
|-- LICENSE                GPL-2.0-or-later.
|-- .editorconfig          Editor/IDE indent + EOL conventions.
|
|-- style.css              WordPress theme header. Contains zero CSS rules. Edit only the header fields.
|-- functions.php          Theme bootstrap. Only add_theme_support + pattern category registration.
|-- theme.json             ENTIRE design system: tokens, element styles, per-block styles. Edit here for any visual change.
|-- readme.txt             WordPress.org readme format. Mirror the headline info from README.md.
|-- screenshot.png         Theme screenshot shown in Appearance > Themes (1200x900).
|
|-- templates/             FSE page templates. Each .html maps to a request type (cart, single-product, archive, etc.).
|   |-- index.html         Default fallback for any unspecified template.
|   |-- 404.html, page.html, single.html, archive.html, search.html, ...
|   |-- single-product.html         WooCommerce single-product layout.
|   |-- archive-product.html        WooCommerce shop / category archive.
|   |-- page-cart.html              WooCommerce cart page.
|   |-- page-checkout.html          WooCommerce checkout page.
|   `-- order-confirmation.html     WooCommerce order received page.
|
|-- parts/                 Reusable template regions. Referenced from templates via wp:template-part.
|   |-- header.html
|   |-- footer.html
|   |-- sidebar-product-filters.html
|   `-- ...
|
|-- patterns/              Inserter-available block patterns. Each is a PHP file with a pattern header comment.
|   |-- hero-image.php             Full-bleed hero with cover image.
|   |-- hero-text.php              Centered text-only hero.
|   |-- featured-products.php      3-up product grid via woocommerce/product-collection.
|   |-- value-props.php            3-column USPs row.
|   |-- faq-accordion.php          5-item FAQ section using core/accordion.
|   |-- testimonials.php           3 customer quote cards.
|   |-- brand-story.php            Image + text "about us" section.
|   |-- newsletter.php             Email signup callout.
|   |-- cta-banner.php             Single-CTA banner.
|   |-- footer-columns.php         Replacement footer with site map.
|   `-- category-tiles.php         3 cover-image links to top categories.
|
|-- styles/                Global style variations. User can switch between these in Site Editor > Styles.
|   |-- dark.json                  Inverts base/contrast for dark mode.
|   |-- editorial.json             Serif body, tighter content size, magazine-style layout.
|   `-- high-contrast.json         WCAG-leaning palette + always-underlined links + thicker button borders.
|
|-- docs/                  Long-form docs for theme developers and AI agents.
|   |-- STRUCTURE.md       This file.
|   |-- TOKENS.md          Design-token guide. Which slug to use when.
|   |-- RECIPES.md         Step-by-step recipes for the 10 most common tasks.
|   |-- ANTI-PATTERNS.md   Bad-code/good-code pairs. Read before editing if unsure.
|   `-- BLOCKS.md          Inventory of every block actually used in this theme.
|
|-- bin/                   Tooling. Not loaded by WordPress. All scripts are stdlib-only Python.
|   |-- check.py                   Run every project check. Use this before committing.
|   |-- build-index.py             Regenerate INDEX.md after structural changes. Run before check.py.
|   |-- validate-theme-json.py     Verify every block name in theme.json against Gutenberg/WC trunk.
|   |-- list-tokens.py             Print every design token defined in theme.json.
|   `-- clone.py                   Cross-platform clone-and-rename for new projects.
|
|-- _examples/             Annotated stub files. Copy these when scaffolding new files. Underscore prefix prevents WP from loading.
|   |-- pattern.php.txt            Template for a new patterns/<slug>.php file.
|   |-- style-variation.json.txt   Template for a new styles/<slug>.json file.
|   `-- template.html.txt          Template for a new templates/<slug>.html file.
|
|-- assets/
|   `-- fonts/             Self-hosted font files referenced from theme.json.
|
`-- languages/             Translation files (.mo / .po / .pot).
```

## "I want to..." quick reference

| Goal | Touch |
|---|---|
| Change brand color | `theme.json` -> `settings.color.palette` -> `accent` slug |
| Change body font | `theme.json` -> `settings.typography.fontFamilies` -> `sans` slug |
| Change a heading style | `theme.json` -> `styles.elements.h2` (or h1, h3, ...) |
| Change a block's default look | `theme.json` -> `styles.blocks.<core/foo>` |
| Add a dark mode variant | New file in `styles/` |
| Add a reusable section | New file in `patterns/` (copy `_examples/pattern.php.txt`) |
| Override a WC page layout | Edit the matching `templates/page-*.html` or `templates/single-product.html` |
| Change the header/footer | Edit `parts/header.html` or `parts/footer.html` |
| Bootstrap a new project | `python3 bin/clone.py NEW_NAME` |
| Verify your changes | `python3 bin/check.py` |
