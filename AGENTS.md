# AGENTS.md

Instructions for AI coding agents working in this repository. Read this file in full before making any changes. Human-oriented docs live in `README.md`.

## Required reading order

1. **`INDEX.md`** -- auto-generated map of every template, part, pattern, style variation, design token, and block style entry. Read this first; it tells you what exists without reading individual files.
2. This file (constraints + workflow).
3. `docs/STRUCTURE.md` (annotated project map for humans).
4. `docs/TOKENS.md` (design-token usage).
5. For specific tasks: `docs/RECIPES.md`, `docs/ANTI-PATTERNS.md`, `docs/BLOCKS.md`.

## Tools you should use

| Command | What it does |
|---|---|
| `python3 bin/check.py` | Run every project check. Use this before declaring "done". |
| `python3 bin/check.py --quick` | Same, skipping the network-dependent block-name check. |
| `python3 bin/build-index.py` | Regenerate `INDEX.md` after adding/removing files or editing `theme.json`. |
| `python3 bin/list-tokens.py` | Print every design token in `theme.json`. (`INDEX.md` already contains this; use this script for fresh output if `INDEX.md` is stale.) |
| `python3 bin/validate-theme-json.py` | Verify every `core/*` and `woocommerce/*` block name in `theme.json` against trunk. |
| `python3 bin/clone.py NEW_NAME` | Clone Obel into a new theme folder, renaming all identifiers. |
| `python3 bin/list-templates.py` | Print every template file alongside the WordPress URL it handles. Paste output into LLM context to find the right file without reading the directory. |

If you remember nothing else from this file: **read `INDEX.md` first, run `python3 bin/check.py --quick` last.**

## What this project is

Obel is a block-only WooCommerce starter theme for WordPress. It is intended to be copied (use `python3 bin/clone.py NEW_NAME`) and then customized by editing `theme.json` and adding project-specific patterns. The framework itself is deliberately small.

## Hard rules — never violate

These rules are not preferences. They define what this theme *is*. Do not break them, even if the user's request would be easier to fulfill by breaking them. If a request requires breaking a rule, push back and propose an alternative.

1. **No CSS files.** `style.css` exists only for the WordPress theme header. Do not create any other `.css` file. Do not add `<style>` tags. Do not enqueue stylesheets.
2. **No `!important`.** Anywhere. The block style engine handles specificity; `!important` is a sign that the design tokens or block scope are wrong.
3. **No custom blocks.** Use only blocks shipped by WordPress core (`core/*`) or WooCommerce core (`woocommerce/*`). Do not register new block types in PHP or JS.
4. **No JavaScript bundles.** No `package.json`, no `webpack`, no build step. Pure PHP + JSON + HTML.
5. **`theme.json` is the single source of truth for styling.** Every visual change goes through `theme.json` — global tokens, element styles, or per-block `styles.blocks.*` entries.
6. **All block names must be real.** Verify every `core/*` and `woocommerce/*` key against the Gutenberg / WooCommerce source before adding it. Run `bin/validate-theme-json.py` after editing `theme.json`. Past mistakes (`core/time-to-read` instead of `core/post-time-to-read`, etc.) cost real time.
7. **No marketing fluff in user-facing text.** Plain, factual prose. No em-dashes (`—`), no triadic constructions ("clean, fast, beautiful"), no "leverage / robust / comprehensive / seamless" vocabulary.

## Allowed dependencies

- WordPress 6.8 or newer
- PHP 8.2 or newer
- WooCommerce 10.0 or newer

Anything else (Composer packages, NPM packages, external libraries) is forbidden in the framework. Project clones may add what they need.

## Where to put things

| Change | Goes in |
|---|---|
| Color, font, spacing, shadow, radius, transition tokens | `theme.json` → `settings.color` / `settings.typography` / `settings.spacing` / `settings.shadow` / `settings.custom` |
| Default styling for an HTML element (h1–h6, link, button, caption, cite) | `theme.json` → `styles.elements.*` |
| Default styling for a specific block | `theme.json` → `styles.blocks.<block-name>` |
| A named visual variant of a block (e.g. button outline, separator dots) | `theme.json` → `styles.blocks.<block-name>.variations.<name>` |
| A whole-theme look (e.g. dark mode, editorial) | A new file in `styles/` (style variation JSON) |
| A reusable layout the user can insert | A new `.php` file in `patterns/` |
| A page layout (front-page, cart, etc.) | A `.html` file in `templates/` |
| A reusable region (header, footer) | A `.html` file in `parts/` |
| Theme bootstrap (`add_theme_support`, pattern category registration) | `functions.php` — keep it minimal |
| Translation strings | `languages/` |
| Project tooling (validators, clone script) | `bin/` |
| Copy-from stubs for new files | `_examples/` |
| Long-form docs for humans and agents | `docs/` |
| Paste-in system prompt for any LLM | `SYSTEM-PROMPT.md` |

## Workflow for common tasks

### Restyling the theme

1. Edit `theme.json`. Start with `settings.color.palette` and `settings.typography.fontFamilies`. Do not edit individual `styles.blocks.*` unless a block needs to deviate from the global tokens.
2. Run `bin/validate-theme-json.py` to confirm all block names still exist.
3. Test in a real WP install (or wp-env / Playground). The site editor at `/wp-admin/site-editor.php` is the primary preview surface.

### Adding a new pattern

1. Create `patterns/my-pattern.php` with the standard WP pattern header (see existing files in `patterns/`).
2. Use only `core/*` and `woocommerce/*` blocks in the markup.
3. Reference design tokens via the `var:preset|...` syntax inside block attributes, never hardcoded colors or pixel values.
4. Set `Categories: obel-store` for project-style patterns or another registered category.
5. Set `Block Types: core/post-content` to make it inserter-available in the post-content area.

### Adding a new style variation

1. Create `styles/my-variation.json`. Schema is the same as `theme.json` but only the keys you want to override.
2. Variations should override `settings.color.palette` and possibly `settings.typography` and `styles.elements`. Avoid overriding individual `styles.blocks.*` unless necessary.
3. Add a `title` field at the top so it appears with a friendly name in the global styles UI.

### Adding styling for a new core block

1. **Verify the block name exists** by checking [the Gutenberg source](https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library/src). The folder name is the block slug.
2. Add the entry under `styles.blocks.<core/blockname>` in `theme.json`.
3. Use design tokens (`var(--wp--preset--color--*)`, `var(--wp--preset--font-size--*)`, `var(--wp--preset--spacing--*)`, `var(--wp--custom--*)`) — never hardcode values.
4. Run `bin/validate-theme-json.py`.

### Cloning Obel for a new project

Use `bin/clone.py` (cross-platform Python script). The script handles macOS, Linux, and Windows.

```bash
python3 bin/clone.py acme            # clones into ../acme
python3 bin/clone.py acme --target ~/Projects
python3 bin/clone.py --help
```

### Scaffolding a new pattern, style variation, or template

Copy from `_examples/`:

```bash
cp _examples/pattern.php.txt patterns/your-slug.php
cp _examples/style-variation.json.txt styles/your-name.json
cp _examples/template.html.txt templates/your-template.html
```

Then update the header (slug, title, description) and replace the body with your content. The `.txt` suffix is intentional and prevents WordPress from loading the stubs.

## Glossary of design tokens

See `docs/TOKENS.md` for which token to use in which situation. **Do not introduce new token slugs** for project clones unless you also document them. Prefer reusing existing slugs over inventing new ones.

## Things that look like good ideas but aren't

- **Adding `add_editor_style()`.** The theme has no CSS by design. There is nothing to load.
- **Registering block styles in PHP via `register_block_style()`.** Use `theme.json` → `styles.blocks.*.variations` instead. Single source of truth.
- **Inlining CSS in HTML templates via `<style>` blocks.** The block markup in templates already contains `style="..."` attributes from the block editor; that's fine. Free-standing `<style>` blocks are not.
- **Using the `css` escape hatch in `theme.json` for layout fixes.** Only use `css` when the block style engine literally cannot express the design (e.g. negative-margin tricks for accordion border collapse). If you find yourself reaching for `css` more than 2-3 times, reconsider the design.
- **Switching to `woocommerce/add-to-cart-with-options` in `single-product.html`** before WooCommerce switches its own canonical template. Stay aligned with the WC trunk template at `plugins/woocommerce/templates/templates/single-product.html`.
- **Adding emojis to user-facing text or commit messages.** WP core themes don't, and Automattic reviewers notice.

## Validation checklist (run before declaring "done")

```bash
python3 bin/check.py            # full check (online; validates block names)
python3 bin/check.py --quick    # offline subset (skip block-name network check)
```

`bin/check.py` runs every check the project cares about: JSON validity, PHP syntax, block-name validity, `!important` scan, stray-CSS scan, block-namespace scan, and AI-fingerprint scan. Output is one line per check. Exit code 0 if all pass.

For a deeper dive into individual checks, see the script source.

## When in doubt

1. Run `python3 bin/list-tokens.py` to see the design system at a glance (or ask the user to paste the output if you can't run scripts).
2. Read `docs/STRUCTURE.md` for the project map.
3. Read `docs/RECIPES.md` for the closest matching task.
4. If still unsure, read `theme.json` end-to-end (it's the entire design system in one file, about 5 minutes to skim).
5. Then ask the user before making structural changes.

If you are an LLM working in this repo via system prompt: see `SYSTEM-PROMPT.md` for the canonical prompt to paste in.
