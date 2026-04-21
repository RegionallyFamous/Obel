<?php
/**
 * Wonders & Oddities variation-swatches mu-plugin.
 *
 * Why this exists:
 *   The default WooCommerce variation form on a variable product page
 *   renders a `<select>` per attribute ("Size", "Finish", "Intensity").
 *   That dropdown is the single most "I am a generic WooCommerce store"
 *   tell on a PDP. Premium storefronts (Glossier, Aesop, Aritzia, every
 *   apparel/cosmetic brand) use either:
 *
 *     - color circles for color/finish attributes
 *     - typography-driven button pills for size / intensity / qty options
 *
 *   Plugins exist to do this (Variation Swatches Pro, etc.) but they
 *   are paid and pull in admin UI we don't need. Since we control the
 *   demo entirely, we render the swatches server-side in front of the
 *   default `<select>` and use a tiny inline JS shim to forward button
 *   clicks into the select's `change` event. WC's variation_form.js
 *   already listens for `change` and refreshes price/stock/image, so
 *   the existing variation logic continues to work unchanged.
 *
 * How it works:
 *   1. Filter `woocommerce_dropdown_variation_attribute_options_html`
 *      to wrap WC's default select markup in a container and append a
 *      sibling button-group rendered from the same options.
 *   2. The original select is kept in the DOM but visually hidden via
 *      CSS (.wo-swatch-select). It remains the WC source of truth so
 *      add-to-cart / variation-image-swap continue working.
 *   3. Inline JS listens for clicks on the .wo-swatch button group,
 *      sets `select.value` to the chosen option, dispatches a native
 *      `change` event so WC's listener picks it up, and toggles the
 *      `is-selected` class on the buttons for visual state.
 *
 * Color swatches:
 *   Color attributes have no built-in WC color metadata when the
 *   underlying attributes are local (not pa_* taxonomies). Migrating
 *   the existing demo products from local to global attributes would
 *   wipe variations on a re-seed (and require a full version-bump on
 *   `_wo_configured`), so we keep a small static color map keyed on
 *   the lowercased option text. Anything not in the map renders as a
 *   text-pill button (the right default for Size / Intensity).
 *
 * Scope:
 *   - Frontend only.
 *   - No DB writes, no options stored, no admin UI.
 *   - No external script enqueue: the JS is ~25 lines, inlined once
 *     per request via wp_footer with an `if (document.querySelector(...))`
 *     gate so it no-ops on non-variable pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Map lowercase option text -> hex color for visual swatches. Anything
 * not in this map falls through to a typography-driven text pill.
 *
 * Add new colors here when new finish-style attributes are introduced.
 * Keys are lowercased for case-insensitive matching.
 */
function wo_swatches_color_map() {
	return array(
		'amber'    => '#c98018',
		'clear'    => '#e8e3d8',
		'black'    => '#0a0a0a',
		'white'    => '#f7f5ef',
		'natural'  => '#d6c8a4',
		'midnight' => '#1a1f3a',
		'forest'   => '#2d4a3e',
		'rust'     => '#a64a1f',
	);
}

/**
 * Render the swatch button group HTML for a single attribute. Receives
 * the same arguments WC passes to the dropdown filter (options array,
 * args including `attribute` slug and `selected`). Returns the wrapper
 * markup that replaces WC's default `<select>...</select>` HTML.
 *
 * `$default_html` is WC's original dropdown HTML; we keep it (visually
 * hidden via .wo-swatch-select) so WC's variation_form.js keeps reading
 * the chosen value off the select. Buttons are pure presentation +
 * JS-forwarded value setters.
 *
 * @param string $default_html WC's default `<select>` markup.
 * @param array  $args         WC dropdown args (attribute, options, selected, ...)
 * @return string Wrapper markup containing the hidden select + visible swatches.
 */
function wo_swatches_render_swatch_group( $default_html, $args ) {
	$attribute_name = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
	$options        = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
	$selected       = isset( $args['selected'] ) ? (string) $args['selected'] : '';
	$product        = isset( $args['product'] ) ? $args['product'] : null;

	if ( empty( $options ) || empty( $attribute_name ) ) {
		return $default_html;
	}

	// `attribute_size` -> "size" -> "Size" (label-cased only for ARIA).
	$attr_label = ucwords( str_replace( array( 'attribute_', 'pa_', '_', '-' ), array( '', '', ' ', ' ' ), $attribute_name ) );

	// Hidden-but-functional select: keep WC's original markup, add a
	// class so CSS can hide it without breaking the form submission.
	$hidden_select = preg_replace(
		'/<select\b/',
		'<select class="wo-swatch-select" aria-hidden="true" tabindex="-1"',
		$default_html,
		1
	);

	$colors  = wo_swatches_color_map();
	$buttons = '';

	foreach ( $options as $opt_value ) {
		// Resolve human-readable label. For local attrs, value === label.
		// For global pa_* attrs, look up the term name. If the lookup
		// fails (e.g. attr is local), fall back to value as label.
		$label = $opt_value;
		if ( taxonomy_exists( $attribute_name ) ) {
			$term = get_term_by( 'slug', $opt_value, $attribute_name );
			if ( $term && ! is_wp_error( $term ) ) {
				$label = $term->name;
			}
		} elseif ( $product && method_exists( $product, 'get_variation_attributes' ) ) {
			// Local attributes: WC stores them as text, value === label.
			$label = $opt_value;
		}

		$key      = strtolower( trim( (string) $label ) );
		$is_color = isset( $colors[ $key ] );
		$selected_class = ( $selected !== '' && (string) $selected === (string) $opt_value )
			? ' is-selected'
			: '';

		$button_class = 'wo-swatch'
			. ( $is_color ? ' wo-swatch--color' : ' wo-swatch--text' )
			. $selected_class;

		$style = $is_color
			? sprintf( ' style="--wo-swatch-color:%s"', esc_attr( $colors[ $key ] ) )
			: '';

		$visual = $is_color
			? '<span class="wo-swatch__dot" aria-hidden="true"></span>'
			: '<span class="wo-swatch__label">' . esc_html( $label ) . '</span>';

		$aria_label = sprintf( '%s: %s', $attr_label, $label );

		$buttons .= sprintf(
			'<button type="button" class="%1$s" data-value="%2$s" aria-label="%3$s" title="%4$s"%5$s>%6$s</button>',
			esc_attr( $button_class ),
			esc_attr( $opt_value ),
			esc_attr( $aria_label ),
			esc_attr( $label ),
			$style,
			$visual
		);
	}

	$group = sprintf(
		'<div class="wo-swatch-group" role="radiogroup" aria-label="%s">%s</div>',
		esc_attr( $attr_label ),
		$buttons
	);

	return '<div class="wo-swatch-wrap">' . $hidden_select . $group . '</div>';
}

add_filter(
	'woocommerce_dropdown_variation_attribute_options_html',
	function ( $html, $args ) {
		if ( is_admin() ) {
			return $html;
		}
		return wo_swatches_render_swatch_group( $html, $args );
	},
	20,
	2
);

/**
 * Inline JS shim: wire button-group clicks back to the hidden select
 * so WC's variation_form.js continues to refresh price/stock/image.
 *
 * Footer-print only on requests that actually have a swatch group; the
 * `if (!document.querySelector(...))` early-return makes it free on
 * every other URL.
 *
 * Self-contained vanilla JS, no jQuery dependency. Intentionally
 * concise — under 30 logical lines, no module wrapper, no event
 * delegation overhead beyond a single click listener per swatch group.
 */
add_action(
	'wp_footer',
	function () {
		if ( is_admin() ) {
			return;
		}
		?>
<script>
(function(){
	var groups = document.querySelectorAll('.wo-swatch-group');
	if (!groups.length) return;
	groups.forEach(function(group){
		var wrap = group.closest('.wo-swatch-wrap');
		if (!wrap) return;
		var sel = wrap.querySelector('select.wo-swatch-select');
		if (!sel) return;
		group.addEventListener('click', function(e){
			var btn = e.target.closest('.wo-swatch');
			if (!btn) return;
			e.preventDefault();
			var v = btn.getAttribute('data-value') || '';
			if (sel.value === v) {
				// Click again to clear -> reset to empty (matches WC reset link).
				v = '';
			}
			sel.value = v;
			sel.dispatchEvent(new Event('change', { bubbles: true }));
			// jQuery change for legacy WC handlers.
			if (window.jQuery) { window.jQuery(sel).trigger('change'); }
			group.querySelectorAll('.wo-swatch').forEach(function(b){
				b.classList.toggle('is-selected', b === btn && v !== '');
			});
		});
		// Reflect external resets ("Clear" link) back into the buttons.
		sel.addEventListener('change', function(){
			var v = sel.value;
			group.querySelectorAll('.wo-swatch').forEach(function(b){
				b.classList.toggle('is-selected', b.getAttribute('data-value') === v && v !== '');
			});
		});
	});
})();
</script>
		<?php
	},
	99
);
