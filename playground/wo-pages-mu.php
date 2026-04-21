<?php
/**
 * Wonders & Oddities branded WC pages mu-plugin — RETIRED.
 *
 * This file once registered five WC hooks that all painted shopper-
 * facing brand markup from `playground/`:
 *
 *   1. My Account login chrome (intro panel + help line + 2-column
 *      wrapper grid) via `woocommerce_before_customer_login_form` /
 *      `_after_customer_login_form`.
 *   2. Branded empty cart via `woocommerce_cart_is_empty`.
 *   3. Branded "no products found" empty state via
 *      `woocommerce_no_products_found`.
 *   4. Editorial archive hero via `woocommerce_before_main_content`.
 *   5. Per-theme `theme-<slug>` body class via `body_class`.
 *
 * Every one of those was a violation of the root rule "Shopper-facing
 * brand lives in the theme, not in playground/" — anything that
 * affects what a real shopper sees on a released theme has to ship in
 * the theme directory so a Proprietor who downloads the theme and
 * drops it into `wp-content/themes/` gets the same chrome as the
 * Playground demo. The five hooks are now registered from per-theme
 * blocks in each `<theme>/functions.php` between matching `// ===
 * BEGIN <slug> ===` sentinels:
 *
 *   - my-account: prio 4/5/6 (login-grid / intro / login-form open)
 *     and after-prio 19/20/25 (login-form / help / login-grid close)
 *   - empty-states: prio 5 (`woocommerce_cart_is_empty`) and an
 *     `init` callback at prio 20 that swaps WC's
 *     `wc_no_products_found` for the theme's branded version at
 *     prio 10 on `woocommerce_no_products_found`
 *   - archive-hero: prio 5 (`woocommerce_before_main_content`)
 *   - body-class: filter on `body_class` with the theme slug
 *     hardcoded (NOT read from `WO_THEME_SLUG`, which only exists
 *     in the Playground bootstrap)
 *
 * Empty states + archive hero are classic-template hooks that never
 * actually fire on the WC Blocks cart / product collection used by
 * the demo, so removing the duplicate registrations from this file
 * has no visual effect on the snapshots — but the per-theme blocks
 * are what guarantee the chrome reappears on a real install where a
 * Proprietor wires up the legacy WC pages.
 *
 * The boundary is enforced by `check_no_brand_filters_in_playground`
 * in `bin/check.py` — every hook listed above is on its denylist.
 * This file is kept (rather than deleted) so blueprint inlining via
 * `bin/sync-playground.py` doesn't break and so future regressions
 * can find this comment for context.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
