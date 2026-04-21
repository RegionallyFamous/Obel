<?php
/**
 * Wonders & Oddities accepted-payments strip (mu-plugin).
 *
 * Why this exists:
 *   The WC Blocks checkout ships a `cart-accepted-payment-methods-block`
 *   that renders generic Visa/MC/Amex/Discover SVG sprites. That block
 *   is opt-in (we explicitly include it in our seeded cart tree) but
 *   only renders on the cart page totals column, not on the checkout
 *   below the Place Order button — which is where premium storefronts
 *   put the trust strip ("we accept: Visa, Mastercard, Amex, Apple Pay,
 *   Google Pay"). This mu-plugin appends a small wordmark strip to:
 *
 *     - the cart-totals block (inside the cart-totals column)
 *     - the checkout-actions block (immediately after Place Order)
 *
 *   The icons are wordmarks rendered as text pills so we don't have to
 *   ship 5 brand SVGs (and so they degrade gracefully under any palette
 *   without trademark-owner-specific colors). All five wordmarks are
 *   in the .wo-payment-icons__icon list and styled by Phase C CSS.
 *
 * Implementation:
 *   The cart and checkout pages are both WC Blocks pages, so the only
 *   reliable post-render hook is `wp_footer` + DOM injection. We attach
 *   one handler that finds the proceed-to-checkout / place-order button
 *   container and appends a `.wo-payment-icons` div if one isn't already
 *   present. The check is idempotent (if WC Blocks re-renders the page
 *   on an AJAX cart update, the new container is missing the strip and
 *   we re-inject; if it isn't, we do nothing).
 *
 * Why text labels not real SVGs:
 *   - No trademark concerns (rendering "VISA" as text is fair use; the
 *     official logo SVG isn't).
 *   - No bytes shipped on every request.
 *   - Renders consistently across every theme palette via CSS tokens.
 *   - Switches to real SVGs is a one-line change in the JS array below
 *     if the user wants to ship real brand marks later.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_footer',
	function () {
		if ( is_admin() ) {
			return;
		}
		// Only print on cart / checkout / order-pay pages — keeps the
		// JS shim from running on every page load on the storefront.
		if ( ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
			return;
		}
		?>
<script>
(function(){
	var BRANDS = ['Visa','MC','Amex','Discover','Apple Pay','G Pay'];
	function build(){
		var div = document.createElement('div');
		div.className = 'wo-payment-icons';
		var label = document.createElement('span');
		label.className = 'wo-payment-icons__label';
		label.textContent = 'We accept';
		div.appendChild(label);
		var list = document.createElement('span');
		list.className = 'wo-payment-icons__list';
		BRANDS.forEach(function(name){
			var pill = document.createElement('span');
			pill.className = 'wo-payment-icons__icon';
			pill.textContent = name;
			list.appendChild(pill);
		});
		div.appendChild(list);
		return div;
	}
	function inject(){
		// Checkout: place-order actions block.
		var actions = document.querySelector('.wp-block-woocommerce-checkout-actions-block');
		if (actions && !actions.querySelector(':scope > .wo-payment-icons')) {
			actions.appendChild(build());
		}
		// Cart: bottom of totals column.
		var totals = document.querySelector('.wp-block-woocommerce-cart-totals-block');
		if (totals && !totals.querySelector(':scope > .wo-payment-icons')) {
			totals.appendChild(build());
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', inject);
	} else {
		inject();
	}
	// WC Blocks re-renders the cart/checkout on every store mutation;
	// observe the body so we re-inject if the strip gets wiped.
	var mo = new MutationObserver(function(){ inject(); });
	mo.observe(document.body, { childList: true, subtree: true });
})();
</script>
		<?php
	},
	99
);
