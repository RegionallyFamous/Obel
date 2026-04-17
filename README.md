# Obel

A block-only WooCommerce starter theme for WordPress.

Obel ships full template coverage for both WordPress and WooCommerce, composed entirely of core blocks. All visual styling is defined in `theme.json`. There are no custom CSS files, no custom blocks, and no patterns library. The intended workflow is to copy this theme, rename it for the project, and edit `theme.json`.

## Conventions

- Core blocks only. Every template uses blocks that ship with WordPress core or WooCommerce core. No third-party blocks, no custom block authoring.
- No custom CSS. `style.css` contains only the WordPress theme header. There are no other `.css` files. All styling lives in `theme.json` (global tokens, element styles, and per-block overrides).
- No `!important` declarations. Specificity is managed by the block style engine.
- One source of truth for design. To change the look, edit `theme.json`.

## Requirements

- WordPress 6.8 or higher
- PHP 8.2 or higher
- WooCommerce 10.0 or higher (for the canonical `page-cart` and `page-checkout` template slugs)

## Installation

1. Copy the `obel` folder into `wp-content/themes/`.
2. Activate Obel in *Appearance > Themes*.
3. Install and activate WooCommerce if you want the storefront templates to render.

## Cloning for a new project

To start a new theme called for example `acme`:

```bash
python3 bin/clone.py acme
```

This copies the folder, renames every `Obel` / `obel` reference inside editable files, and prints next steps. It works on macOS, Linux, and Windows. See `bin/clone.py --help` for options.

Then edit `theme.json` to set the brand (colors, fonts, spacing, layout widths). Templates do not need edits. See `docs/TOKENS.md` for a guide to which design token to use in which situation.

## File map

```
obel/
  style.css            WordPress theme header
  functions.php        Theme supports + pattern category registration
  theme.json           Design tokens, element styles, block styles
  README.md            This file
  INDEX.md             Auto-generated single-file map of the entire project (read this first)
  SYSTEM-PROMPT.md     Paste-in system prompt for any LLM working in this repo
  AGENTS.md            Full constraints + workflow recipes for AI agents
  CHANGELOG.md         Per-version change log
  LICENSE              GPL-2.0-or-later
  .editorconfig        Editor indent/EOL conventions
  screenshot.png       Theme screenshot
  templates/           FSE templates (WordPress and WooCommerce)
  parts/               Template parts (header, footer, etc.)
  patterns/            Generic starter patterns (delete or replace)
  styles/              Style variations (dark, editorial, high-contrast)
  docs/                Long-form docs (STRUCTURE, TOKENS, RECIPES, ANTI-PATTERNS, BLOCKS)
  bin/                 Tooling (check.py, list-tokens.py, validate-theme-json.py, clone.py)
  _examples/           Copy-from stubs for new patterns/variations/templates
  languages/           Translation files (.mo / .po)
  readme.txt           WordPress.org-format readme
```

For a fully annotated map see [`docs/STRUCTURE.md`](docs/STRUCTURE.md).

## Template inventory

### WordPress

| Template          | Purpose                              |
| ----------------- | ------------------------------------ |
| `index.html`      | Universal fallback                   |
| `home.html`       | Blog index                           |
| `front-page.html` | Static front page                    |
| `singular.html`   | Shared single-entity fallback        |
| `single.html`     | Single post                          |
| `page.html`       | Single page                          |
| `archive.html`    | Generic archive                      |
| `category.html`   | Category archive                     |
| `tag.html`        | Tag archive                          |
| `author.html`     | Author archive                       |
| `date.html`       | Date archive                         |
| `taxonomy.html`   | Custom taxonomy archive              |
| `search.html`     | Search results                       |
| `404.html`        | Not found                            |

### WooCommerce

| Template                          | Purpose                               |
| --------------------------------- | ------------------------------------- |
| `single-product.html`             | Single product                        |
| `archive-product.html`            | Shop / catalog (also handles `product_cat`, `product_tag`, and `product_attribute` archives via the WP template hierarchy fallback) |
| `product-search-results.html`     | Product search results                |
| `page-cart.html`                  | Cart                                  |
| `page-checkout.html`              | Checkout                              |
| `order-confirmation.html`         | Order received                        |
| `page-coming-soon.html`           | Coming-soon mode landing              |

### Template parts

| Part                  | Purpose                                  |
| --------------------- | ---------------------------------------- |
| `header.html`         | Primary header (with mini-cart inline)   |
| `checkout-header.html`| Minimal header for checkout flow         |
| `footer.html`         | Site footer                              |
| `comments.html`       | Comments thread and form                 |
| `post-meta.html`      | Post date, author, categories            |
| `product-meta.html`   | Product SKU, categories, tags            |
| `no-results.html`     | Shared empty-state                       |

## Constraints

- No `.css` files other than `style.css`, which contains only the theme header.
- No `!important` anywhere in the codebase.
- No block prefixes other than `core/` and `woocommerce/` in any template or part.
- `theme.json` validates against the WordPress block-theme schema.
- All `core/*` and `woocommerce/*` block names referenced in `theme.json` are real (verified by `bin/validate-theme-json.py`).

## Validating changes

Run a single command:

```bash
python3 bin/check.py            # JSON, PHP, block names, forbidden patterns (online)
python3 bin/check.py --quick    # same, skipping the network-dependent block check
```

To inspect the design system without parsing `theme.json`:

```bash
python3 bin/list-tokens.py                # everything
python3 bin/list-tokens.py colors --css-vars
python3 bin/list-tokens.py spacing
```

## Working with an LLM

Paste [`SYSTEM-PROMPT.md`](SYSTEM-PROMPT.md) into your assistant's system prompt at the start of any session. The prompt tells the assistant to read [`INDEX.md`](INDEX.md) first, which gives it the entire project structure (files, tokens, block styles, patterns, variations) in one read.

After any change that adds or removes files (or edits `theme.json`), regenerate the index:

```bash
python3 bin/build-index.py
python3 bin/check.py --quick
```

`bin/check.py` includes an "INDEX.md in sync" check that fails if you forgot to regenerate. If your assistant can't run scripts, run these commands yourself and paste the output back into the chat.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
