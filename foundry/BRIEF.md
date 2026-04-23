# Foundry — design brief

This file was emitted by `bin/design.py` after cloning `obel` -> `foundry` and applying the spec's palette + font choices. Read it before you do anything else; it captures the design intent the spec encoded so you can write the per-theme microcopy block, restyle the templates that need it, and brief any product photography in the same voice.

## Tagline

> Carefully compounded goods.

## Voice

Victorian apothecary register: 'remedy' and 'tonic' for product, 'compound' and 'hand-wrought' as verbs, 'parcel' for order, 'ledger' for cart, 'counting house' for checkout, 'apothecary' for account, 'no. XII' for fields, '*' as the required marker, '—' as the dash between phrases, date stamps use roman numerals (MMXXVI), all caps for section headings, italic body for descriptions, copy feels like it was engraved on a shop card in 1898

Write this voice into the `// === BEGIN wc microcopy ===` block in `foundry/functions.php`. Every theme's microcopy must read distinctly from every other theme's; `bin/check.py check_wc_microcopy_distinct_across_themes` enforces it. Crib the structure from any sibling theme's microcopy block, then rewrite every literal string in this voice.

## Layout hints

- ornate boxed hero with engraved botanical flourishes flanking a centered serif headline + roman-numeral established date
- three-column product catalog in bordered cards with hairline rules, prices centered below italic product names, little apothecary-icon separator between cards
- section dividers use engraved ornamental flourishes (fleuron glyph or thin ornamental rule) instead of plain horizontal lines
- category nav sits left of a centered wordmark with tiny botanical icons above each nav label, account/cart sit right mirroring the category nav
- footer reads like a shop card: 'Compounded & Parcelled from [address] — Est. MMXXVI' above three thin-rule columns (remedies / about / parcel status)
- product archive shows a single photo per card against cream with a thin amber border, no hover image swap, price in the oxblood accent

These hints came from the spec. Restructure `templates/front-page.html` and any sibling templates whose composition needs to change. Token swaps alone are never enough; see `.claude/skills/build-block-theme-variant/SKILL.md` step 6.

## Palette applied

| Slug | Hex |
|------|-----|
| `accent` | `#8b2f1f` |
| `accent-soft` | `#ead1b9` |
| `base` | `#f5eed8` |
| `border` | `#c9b583` |
| `contrast` | `#1c1711` |
| `muted` | `#e0d0a4` |
| `primary` | `#1c1711` |
| `primary-hover` | `#4a2418` |
| `secondary` | `#3a2e1e` |
| `subtle` | `#efe4c4` |
| `surface` | `#fdf8e8` |
| `tertiary` | `#6d5b3a` |

Run `python3 bin/check-contrast.py` (if it exists in this repo) before locking the palette. Verify every pairing in the WCAG table at `.claude/skills/build-block-theme-variant/SKILL.md` step 5.

## Fonts registered

**Google Fonts to download as `.woff2`** (the `fontFace` entries already
point at `foundry/assets/fonts/<slug>-<weight>.woff2` — drop the files there):

- `Cormorant Garamond` (display, weights 400, 600, 700)
- `Cormorant Garamond` (serif, weights 400, 500, 600)
- `IBM Plex Mono` (sans, weights 400, 500)

Use https://gwfh.mranftl.com/fonts to pull the official `.woff2` files (one per weight + style). Then run `python3 bin/check.py check_no_remote_fonts` to confirm no remote URLs slipped in.

## Next steps

1. Open `theme.json` and confirm the palette / font slots match your intent.
2. Drop product photographs as `foundry/playground/images/product-wo-*.jpg` 
   (one per product). Generate them so they read as this theme's voice;
   `bin/check.py check_product_images_unique_across_themes` will reject any
   byte-shared with a sibling theme.
3. Edit the `// === BEGIN wc microcopy ===` block in `functions.php` to match
   the voice above.
4. Restructure `templates/front-page.html` per the layout hints; every theme's
   homepage must be structurally distinct (`check_front_page_unique_layout`).
5. Re-shoot screenshot.png: `python3 bin/build-theme-screenshots.py foundry`.
6. Snap baseline: `python3 bin/snap.py shoot foundry && \
   python3 bin/snap.py baseline foundry`.
7. Verify: `python3 bin/check.py foundry --quick` — fix every failure before
   committing. Don't suppress with `--no-verify`.
8. Commit + push everything (theme dir, blueprint, content, baselines).

`BRIEF.md` is committed alongside the theme so future agents (and the next
human reading the repo a year from now) can see the design intent that
seeded the theme without spelunking the original prompt.

_Brief auto-generated for foundry by `bin/design.py`._
