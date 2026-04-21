<?php
/**
 * Wonders & Oddities accepted-payments strip — RETIRED.
 *
 * This file once registered a `wp_footer` callback from `playground/`
 * that DOM-injected a `<div class="wo-payment-icons">` strip ("we
 * accept: Visa, Mastercard, Amex, Apple Pay, Google Pay") into the
 * cart-totals column and beneath the checkout Place Order button on
 * cart / checkout pages. It worked because both pages are WC Blocks
 * pages with no reliable post-render hook, so DOM injection from
 * `wp_footer` was the only option.
 *
 * The hook is innocuous (`wp_footer` is everywhere) but the markup it
 * painted is shopper-facing brand: the trust strip is part of every
 * theme's checkout chrome and a real Proprietor expects it to ship
 * with the theme. The implementation was migrated wholesale into
 * per-theme blocks in each `<theme>/functions.php` between
 * `// === BEGIN payment-icons === ... // === END payment-icons ===`
 * sentinels (same `wp_footer` registration, same idempotent injection,
 * same five inline SVG pills, same cart/checkout-only short circuit).
 * Each theme owns its own pill styling via `theme.json` so the strip
 * blends with the rest of the per-theme checkout polish.
 *
 * `check_no_brand_filters_in_playground` in `bin/check.py` denies any
 * `playground/*.php` source from referencing the `wo-payment-icons`
 * marker class to keep the boundary intact (the `wp_footer` hook
 * itself is legitimate from `playground/` for unrelated shims).
 *
 * The file is kept (rather than deleted) so blueprint inlining via
 * `bin/sync-playground.py` doesn't break and so future regressions
 * can find this comment for context.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
