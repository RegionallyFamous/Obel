# Fifty

**A monorepo of token-driven WooCommerce themes — zero CSS files, zero JavaScript, zero build step. Edit one `theme.json` and the entire storefront re-skins: homepage, archives, product pages, cart, checkout, account dashboard, transactional emails.**

Most WordPress themes are a CSS pile-up with a `package.json` and a webpack config bolted on. Fifty goes the other direction: every theme in this repo is pure block markup plus a `theme.json` of design tokens plus a tiny `functions.php` of WordPress filters. Restyling means moving tokens around, not chasing selectors. Adding a new theme means cloning the base and changing colors and fonts.

It is also a complete worked example of what an **agent-first** WordPress codebase looks like — every rule that keeps the themes consistent is codified in `bin/check.py` and gated by a Playwright + WordPress Playground visual snapshot framework that runs as part of the build. Cursor and Claude can pick this codebase up cold and ship a new theme in one session, because every architectural decision has a load-bearing comment and every footgun has a guardrail.

## Try it now

Each theme below boots a disposable WordPress + WooCommerce instance entirely in your browser, with the theme installed, 30 sample products, 5 orders, a logged-in customer, on-sale and out-of-stock states — all seeded. ~60-90s cold start. No install, no signup, no card.

| Theme | Vibe | Live demo |
| --- | --- | --- |
| **Obel** | Editorial, soft, restrained | [demo.regionallyfamous.com/obel/](https://demo.regionallyfamous.com/obel/) |
| **Chonk** | Neo-brutalist, chunky, high-contrast | [demo.regionallyfamous.com/chonk/](https://demo.regionallyfamous.com/chonk/) |
| **Selvedge** | Workwear indigo, woven textures, raw edges | [demo.regionallyfamous.com/selvedge/](https://demo.regionallyfamous.com/selvedge/) |
| **Lysholm** | Nordic home goods, white-on-white, blonde wood | [demo.regionallyfamous.com/lysholm/](https://demo.regionallyfamous.com/lysholm/) |

Each demo lands you on the designed homepage. From there: shop archive, single product, pre-filled cart, checkout, account dashboard, blog, 404 — every page works. Logged in as `admin` / `password`; sign in as `customer` / `customer` to see the customer dashboard.

## What's cool about Fifty

- **Four distinct storefronts from one codebase.** Same templates, same patterns, same `bin/check.py` gate — totally different vibes.
- **Token-only restyling, for real.** No CSS files in any theme. The look comes entirely from `theme.json` design tokens. Change `--wp--preset--color--accent` and every button, link, focus ring, swatch, hover state, form input, and add-to-cart CTA picks it up. The hard rule is "no `!important` and no raw hex colors outside the palette" — and `bin/check.py` enforces it.
- **One-click WordPress Playground demos.** A short URL like [`demo.regionallyfamous.com/chonk/cart/`](https://demo.regionallyfamous.com/chonk/cart/) boots a fully-seeded WordPress + WooCommerce instance in your browser. No install.
- **Visual regression testing built into every commit.** `bin/snap.py` boots WordPress Playground locally for each theme, drives Playwright Chromium across every `(route × viewport)`, and captures screenshot **+** rendered DOM **+** console messages **+** page errors **+** network failures **+** axe-core a11y violations **+** computed dimensions for tracked layout selectors **+** scripted interactive flows. Diffs against committed baselines, emits a tiered `pass / warn / fail` gate.
- **Custom DOM heuristics, not just axe.** ~25 hand-written checks for the bug classes WordPress + WooCommerce themes actually break on: broken/oversized/blurry images, narrow sidebars, missing `alt` attrs, leaked PHP debug output, raw `__()` i18n tokens, visible WC error notices, ellipsis truncation actively hiding content, empty landmarks, **duplicate `view-transition-name` collisions** (the kind that silently abort every browser-native page transition), and more.
- **Cross-document view transitions, by default.** Every theme opts into [native CSS view transitions](https://developer.chrome.com/docs/web-platform/view-transitions/cross-document): the site title morphs from the header into the PDP hero, post titles morph from archive cards into single-post heroes, header and footer persist across navigation. CSS-only — no JavaScript, no SPA shell.
- **WooCommerce-native, but the WC default chrome is invisible.** The themes paint over WC's loading skeletons, totals blocks, form inputs, sidebar layout, mini-cart drawer, product gallery, sale flashes, and review stars so the result looks like a custom store, not "yet another WooCommerce site".
- **WCAG AA, enforced.** Hand-rolled contrast checks, axe-core on every snap, and a hover/focus state legibility check that catches the "accent color collapsed to invisible against base" footgun separately. No theme ships with a contrast violation.
- **Agent-first by design.** Every theme has an `AGENTS.md`, `INDEX.md`, `CHANGELOG.md`, and `SYSTEM-PROMPT.md`. The repo-root `AGENTS.md` documents every footgun the project has hit, with the regression history and the codified guardrail.

## Documentation

The technical reference lives in the [wiki](https://github.com/RegionallyFamous/fifty/wiki).

| If you want to... | Read |
|---|---|
| Try a theme in WordPress Playground (one-click demos, full URL tables) | [Getting Started](https://github.com/RegionallyFamous/fifty/wiki/Getting-Started) |
| Install a theme into a real WordPress instance | [Getting Started → Local install](https://github.com/RegionallyFamous/fifty/wiki/Getting-Started#loading-themes-into-wordpress) |
| See the monorepo layout and what every directory does | [Project Structure](https://github.com/RegionallyFamous/fifty/wiki/Project-Structure) |
| Run `bin/check.py`, validators, and the rest of the CLI | [Tooling](https://github.com/RegionallyFamous/fifty/wiki/Tooling) |
| Drive the visual snapshot framework | [Visual Snapshots](https://github.com/RegionallyFamous/fifty/wiki/Visual-Snapshots) |
| Scaffold a new theme variant | [Adding a Theme](https://github.com/RegionallyFamous/fifty/wiki/Adding-a-Theme) |
| Make changes to existing themes or shared tooling | [Working in the Repo](https://github.com/RegionallyFamous/fifty/wiki/Working-in-the-Repo) |
| Deep dive into a single theme's design tokens, blocks, templates | [Architecture](https://github.com/RegionallyFamous/fifty/wiki/Architecture) · [Design Tokens](https://github.com/RegionallyFamous/fifty/wiki/Design-Tokens) · [Block Reference](https://github.com/RegionallyFamous/fifty/wiki/Block-Reference) · [Templates](https://github.com/RegionallyFamous/fifty/wiki/Templates) |
| Use Fifty with Cursor / Claude / ChatGPT | [Working with LLMs](https://github.com/RegionallyFamous/fifty/wiki/Working-with-LLMs) |

For agents working in the repo, [`AGENTS.md`](./AGENTS.md) at the root is the load-bearing file: it carries every footgun the project has hit, with the regression history and the codified guardrail.

## License

GPL-2.0-or-later. See [`LICENSE`](./LICENSE).
