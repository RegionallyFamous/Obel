# Design Tokens

Reference for which token to use in which situation. Read this before editing `theme.json`.

All tokens are defined in `theme.json` under `settings`. They surface as CSS custom properties at runtime (e.g. `var(--wp--preset--color--accent)`) and as preset references in block markup (e.g. `"backgroundColor":"accent"` or `"style":{"spacing":{"padding":{"top":"var:preset|spacing|md"}}}`).

## Color

The palette is intentionally small. Pick the most semantic option, not the closest hex match.

| Slug | Use for |
|---|---|
| `base` | Body background. The dominant page color. |
| `surface` | Pure white surfaces (cards, modals, mini-cart) when contrast against `base` is needed. |
| `subtle` | Soft backgrounds (cart totals, code blocks, summary boxes). One step darker than `base`. |
| `muted` | Section dividers, hover backgrounds for subtle elements. |
| `border` | All hairline borders (1px). |
| `tertiary` | Lowest-priority text (timestamps, SKUs, post terms). |
| `secondary` | Mid-priority text (excerpts, captions, summaries). |
| `contrast` | Body text. Highest readability. |
| `primary` | Primary button background. Identical to `contrast` by default; override for branded buttons. |
| `primary-hover` | Primary button hover background. |
| `accent` | Brand accent for links on hover, ratings stars, sale prices. **The brand color of a clone.** |
| `accent-soft` | Tinted backgrounds (heroes, callouts) where accent at full strength is too loud. |
| `success` | Success states (in-stock indicators, confirmation banners). |
| `warning` | Low-stock, pending, or attention states. |
| `error` | Out-of-stock, validation errors, destructive actions. |
| `info` | Informational notices (shipping notes, neutral banners). |

Rules:

- Never hardcode hex values. Always reference a slug.
- For a brand-led clone, the most important slug to change is `accent`.
- `contrast` and `base` together define light/dark mode. The `dark` style variation in `styles/dark.json` swaps these.

## Typography

### Font sizes

Fluid by default (scale between viewport widths). Pick by semantic role, not pixel size.

| Slug | Approx desktop | Use for |
|---|---|---|
| `xs` | 13px | Meta labels (date, SKU, post terms), captions, fine print |
| `sm` | 15px | Secondary UI text (nav, search, buttons, comments meta) |
| `base` | 17px | Body copy. The default. |
| `md` | 19px | Lead paragraphs, post content, key product info |
| `lg` | 24px | h5, small section titles |
| `xl` | 30px | h4, callouts |
| `2xl` | 40px | h3, pullquotes |
| `3xl` | 52px | h2, post titles, archive titles |
| `4xl` | 68px | h1 |
| `5xl` | 88px | Hero display text |
| `6xl` | 112px | Oversized hero / splash |

Don't reach for `5xl` / `6xl` casually. They're intended for hero moments.

### Font families

| Slug | Use for |
|---|---|
| `sans` | UI, body, navigation, buttons. Default. |
| `serif` | Long-form reading content if the brand reads literary. |
| `mono` | Code, preformatted text, math, verse. |
| `display` | Headings. Currently identical to `serif`; clones often swap to a brand display font. |

### Line height (custom tokens)

| Slug | Value | Use for |
|---|---|---|
| `tight` | 1.1 | Large display headings (h1, h2) |
| `snug` | 1.25 | Smaller headings (h3-h6) |
| `normal` | 1.5 | Default body |
| `relaxed` | 1.65 | Long-form post content, comments |
| `loose` | 1.85 | Spacious editorial layouts |

### Letter spacing (custom tokens)

| Slug | Use for |
|---|---|
| `tighter` | Very large display headings (h1) |
| `tight` | Display headings (h2, h3) |
| `normal` | Default. Body, UI. |
| `wide` | Buttons, navigation, small caps |
| `wider` | Uppercase meta labels (post terms, SKU, sale badges) |
| `widest` | Decorative oversized labels |

### Font weight (custom tokens)

| Slug | Value | Use for |
|---|---|---|
| `regular` | 400 | Body, headings |
| `medium` | 500 | Buttons, active nav, emphasis |
| `semibold` | 600 | h5, h6, comment author names, sale badges |
| `bold` | 700 | Reserved. Avoid by default. |

## Spacing

Fluid spacing scale (clamps between viewport widths). The system is **two-axis**: spacing is used for both inline gaps and block padding.

| Slug | Approx desktop | Use for |
|---|---|---|
| `2xs` | 4-6px | Inline pill padding, badge padding |
| `xs` | 8-12px | Tight inline gaps, button row gaps |
| `sm` | 12-16px | Small element padding (buttons, form fields), tight blockGap |
| `md` | 16-24px | Default blockGap, body padding, component spacing |
| `lg` | 24-40px | Card padding, section internal padding, columns gap |
| `xl` | 32-60px | Section padding, separator margins |
| `2xl` | 48-88px | Major section padding, between-region margins |
| `3xl` | 64-120px | Hero padding, splash margins |
| `4xl` | 80-160px | Reserved for very large layouts |
| `5xl` | 96-224px | Reserved. Use sparingly. |

Decision rules:

- Inside a single component (button padding, card padding) → `sm` to `lg`.
- Between components in the same section (blockGap) → `md` (default).
- Between sections on the same page → `xl` to `2xl`.
- Above/below a hero or major section change → `2xl` to `3xl`.

## Shadow

| Slug | Use for |
|---|---|
| `xs` | Hairline lift (input focus, hover indicators) |
| `sm` | Card resting state |
| `md` | Card hover, elevated panels |
| `lg` | Modals, mini-cart, dropdowns |
| `xl` | Lightboxes, full-screen overlays |
| `inset` | Pressed buttons, sunken inputs |

Default theme uses shadows sparingly. Add explicitly when needed.

## Border radius (custom tokens)

| Slug | Value | Use for |
|---|---|---|
| `none` | 0 | Sharp corners (editorial layouts) |
| `sm` | 4px | Form inputs, small buttons |
| `md` | 8px | Cards, images, file blocks |
| `lg` | 16px | Large cards, totals panels |
| `xl` | 24px | Hero containers, modals |
| `pill` | 9999px | Pills, badges, primary buttons |

## Transitions (custom tokens)

| Slug | Value | Use for |
|---|---|---|
| `fast` | 120ms ease-out | Hovers, focus rings |
| `base` | 200ms ease-out | Default UI transitions |
| `slow` | 320ms ease-out | Page-level animations, modals opening |

## Layout sizes

Defined under `settings.layout` (the WordPress globals — emitted as `--wp--style--global--content-size` and `--wp--style--global--wide-size`):

- `contentSize: 720px`: default column width for constrained groups and prose-heavy content (post-content, comments).
- `wideSize: 1280px`: column width for `align: wide` blocks (cover, columns, product grid).

`alignfull` blocks span the full viewport.

Plus four named widths defined under `settings.custom.layout` for special containers (404, no-results, narrow CTAs):

| Slug | Value | Used by |
|---|---|---|
| `narrow` | 480px | `parts/no-results.html` |
| `prose` | 560px | `templates/404.html` |
| `comfortable` | 640px | `patterns/newsletter.php`, `templates/page-coming-soon.html` |

Reference them in markup via `var(--wp--custom--layout--<slug>)`. Editing one value here resizes every container that uses it.

## Cover sizes (custom tokens)

Defined under `settings.custom.cover`. Used in `min-height` on `core/cover` blocks.

| Slug | Value | Used by |
|---|---|---|
| `hero` | 640px | `patterns/hero-image.php` |
| `promo` | 520px | `templates/front-page.html` (mid-page promo cover) |
| `tile` | 320px | `patterns/category-tiles.php` |

Reference via `var(--wp--custom--cover--<slug>)` in `style="min-height:..."`.

## Aspect ratios (custom tokens)

Defined under `settings.custom.aspect-ratio`. Used on `core/post-featured-image` and `woocommerce/product-image`.

| Slug | Value | Used by |
|---|---|---|
| `square` | 1 | Product cards (archive-product, product-search-results, single-product related, front-page featured) |
| `portrait` | 4/5 | Reserved (no current usage) |
| `card` | 4/3 | Post archive cards (archive, category, tag, author, date, taxonomy, home, front-page journal) |
| `widescreen` | 16/9 | Single post / page featured images, blog index featured images |

Reference via `var(--wp--custom--aspect-ratio--<slug>)` in the `aspectRatio` block attribute.

## Borders (custom tokens)

Defined under `settings.custom.border.width`. Use these instead of literal `1px`/`2px` in inline border styles.

| Slug | Value |
|---|---|
| `hairline` | 1px |
| `thick` | 2px |

## Adding new tokens

Don't, in the framework. Project clones may add tokens in `theme.json` under `settings.custom.*` or extend the existing scales. When adding to a clone:

1. Match the existing scale step (don't add `md-large` between `md` and `lg`; use `lg` or rescale).
2. Document the new token in this file.
3. Use the same slug across `color`, `spacing`, etc. for consistency (e.g. if you have a new color named `gold`, use `gold` everywhere, not `Gold` or `gold-color`).
