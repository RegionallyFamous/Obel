<?php
/**
 * Wonders & Oddities variation-swatches mu-plugin — RETIRED.
 *
 * This file once filtered `woocommerce_dropdown_variation_attribute_
 * options_html` from `playground/` to swap WC's default variation
 * `<select>` with color circles / text pills, plus a `wp_footer` JS
 * shim that forwarded button clicks back to the hidden select so WC's
 * `variation_form.js` continued to drive price + stock + image swap.
 *
 * That's a shopper-facing brand surface — a real Proprietor who pulls
 * the theme down expects the swatches to ship with it, not with the
 * Playground rig. So the entire implementation was migrated into
 * per-theme blocks in each `<theme>/functions.php` between
 * `// === BEGIN swatches === ... // === END swatches ===` sentinels.
 * Each theme owns its own `<theme>_swatches_color_map()` so the
 * palette stays on-brand even when the catalogue is shared, plus an
 * `<theme>_swatches_render_group()` callback that the same filter
 * delegates to (registered at prio 20 in the theme block).
 *
 * The `wp_footer` JS shim was inlined into every theme's swatches
 * block too, gated by an early-return so it's free on URLs without a
 * swatch group on the page.
 *
 * `check_no_brand_filters_in_playground` in `bin/check.py` denies
 * `woocommerce_dropdown_variation_attribute_options_html` registrations
 * in `playground/*.php` to keep the boundary intact.
 *
 * The file is kept (rather than deleted) so blueprint inlining via
 * `bin/sync-playground.py` doesn't break and so future regressions
 * can find this comment for context.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
