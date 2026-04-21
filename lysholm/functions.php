<?php
/**
 * Lysholm theme bootstrap.
 *
 * Block-only WooCommerce starter theme. All visual styling lives in
 * theme.json; templates and parts are pure block markup. The only PHP
 * code in the theme is this single after_setup_theme hook.
 *
 * @package Lysholm
 */

declare( strict_types=1 );

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'lysholm', get_template_directory() . '/languages' );

		add_theme_support( 'woocommerce' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support(
			'post-formats',
			array( 'aside', 'audio', 'gallery', 'image', 'link', 'quote', 'status', 'video' )
		);
	}
);

add_action(
	'init',
	static function (): void {
		$categories = array(
			'lysholm'          => array(
				'label'       => __( 'Lysholm', 'lysholm' ),
				'description' => __( 'Generic starter patterns. Delete or replace per project.', 'lysholm' ),
			),
			'woo-commerce'  => array(
				'label'       => __( 'Shop', 'lysholm' ),
				'description' => __( 'Patterns for product listings, collections, and shop sections.', 'lysholm' ),
			),
			'featured'      => array(
				'label'       => __( 'Hero', 'lysholm' ),
				'description' => __( 'Full-width hero and banner patterns.', 'lysholm' ),
			),
			'call-to-action' => array(
				'label'       => __( 'Call to action', 'lysholm' ),
				'description' => __( 'Conversion-focused banners and newsletter signups.', 'lysholm' ),
			),
			'testimonials'  => array(
				'label'       => __( 'Testimonials', 'lysholm' ),
				'description' => __( 'Social proof and customer quote patterns.', 'lysholm' ),
			),
			'footer'        => array(
				'label'       => __( 'Footer', 'lysholm' ),
				'description' => __( 'Footer layout patterns.', 'lysholm' ),
			),
		);

		foreach ( $categories as $slug => $args ) {
			register_block_pattern_category( $slug, $args );
		}
	}
);

add_filter(
	'woocommerce_upsells_columns',
	static function ( int $columns, array $upsells = array() ): int {
		$count = is_array( $upsells ) ? count( $upsells ) : 0;
		return $count > 0 ? min( $count, 4 ) : 4;
	},
	10,
	2
);

add_filter(
	'woocommerce_output_related_products_args',
	static function ( array $args ): array {
		$args['posts_per_page'] = 4;
		$args['columns']        = 4;
		return $args;
	}
);

/**
 * Quieter sale badge.
 *
 * WC ships a chirpy `<span class="onsale">Sale!</span>` on the product image.
 * Lysholm is the quietest of the four — a single thin minus glyph reads as
 * "this is on offer" without ever shouting "SALE!". The pill styling itself
 * lives in theme.json -> styles.css; the minus is U+2212 MINUS SIGN, not a
 * hyphen, so it sits visually centered in the badge.
 */
add_filter(
	'woocommerce_sale_flash',
	static function (): string {
		return '<span class="onsale">' . esc_html__( '−', 'lysholm' ) . '</span>';
	}
);

/**
 * Per-post View Transitions: name the post title and featured image with a
 * stable, post-scoped identifier so the browser can morph between the archive
 * card and the single-post hero across a real cross-document navigation.
 *
 * The cross-document opt-in (`@view-transition { navigation: auto }`) and the
 * persistent header/footer/site-title names live in `theme.json` styles.css.
 * This filter only assigns the per-post names; it adds no other behavior.
 */
add_filter(
	'render_block',
	static function ( string $block_content, array $block, WP_Block $instance ): string {
		$names = array(
			'core/post-title'          => 'title',
			'core/post-featured-image' => 'image',
		);
		$kind = $names[ $block['blockName'] ?? '' ] ?? null;
		if ( null === $kind || '' === trim( $block_content ) ) {
			return $block_content;
		}

		$post_id = (int) ( $instance->context['postId'] ?? 0 );
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}
		if ( ! $post_id && is_singular() ) {
			$post_id = (int) get_queried_object_id();
		}
		if ( ! $post_id ) {
			return $block_content;
		}

		$vt_name = sprintf( 'fifty-post-%d-%s', $post_id, $kind );

		// Per-page uniqueness guard. `view-transition-name` MUST be
		// unique on the page or Chrome aborts every transition with
		// `InvalidStateError: Transition was aborted because of
		// invalid state` AND logs `Unexpected duplicate
		// view-transition-name: fifty-post-<id>-<kind>` to the
		// console. The same post ID can render in two block contexts
		// on the same page (e.g. a featured-products section AND a
		// post-template grid that includes the same post), so the
		// naive "name every post-title block" approach is not safe.
		// Track names already assigned this request via a global; a
		// closure `static` persists across the PHP-FPM worker's
		// lifetime and would silently skip post-87 on request 2 just
		// because request 1 saw it. The companion `init` reset below
		// (registered next to this filter) clears the global at the
		// start of every request so the dedup window IS the page.
		global $fifty_vt_assigned;
		if ( ! is_array( $fifty_vt_assigned ) ) {
			$fifty_vt_assigned = array();
		}
		if ( isset( $fifty_vt_assigned[ $vt_name ] ) ) {
			return $block_content;
		}
		$fifty_vt_assigned[ $vt_name ] = true;

		$processor = new WP_HTML_Tag_Processor( $block_content );
		if ( ! $processor->next_tag() ) {
			return $block_content;
		}
		$existing = $processor->get_attribute( 'style' );
		$decl     = 'view-transition-name:' . $vt_name;
		$value    = is_string( $existing ) && '' !== trim( $existing )
			? rtrim( trim( $existing ), ';' ) . ';' . $decl
			: $decl;
		$processor->set_attribute( 'style', $value );
		return $processor->get_updated_html();
	},
	10,
	3
);

// Reset the per-request `view-transition-name` dedup tracker at the
// top of every request. Without this the global persists across
// requests in the same PHP-FPM worker (or in WP-Playground's single
// long-lived PHP instance) and the dedup "remembers" post IDs from
// previous pageloads, silently dropping their transition names on
// later pages where they'd be perfectly valid. `init` fires once per
// request, before any block render, so this is the right resync
// point.
add_action(
	'init',
	static function (): void {
		$GLOBALS['fifty_vt_assigned'] = array();
	}
);

// === BEGIN wc microcopy ===
//
// Shopper-facing WC microcopy in the Lysholm voice.
//
// This block lives in the theme (not in playground/) so the overrides
// travel with the released theme — drop the directory into
// wp-content/themes/ on a real install and these strings ship with it.
// See AGENTS.md root-rule "Shopper-facing brand lives in the theme,
// not in playground/" for the full split between this block and what
// the playground/ scaffolding is allowed to do.
//
// Sections, in order:
//   1. Archive: page title visibility, pagination arrows, result count
//      format, sort-dropdown labels.
//   2. Cart + checkout + account microcopy via the gettext map.
//   3. WC Blocks (React-rendered) string overrides that bypass gettext.
//   4. Required-field marker swap (red <abbr>* -> theme-styled glyph).
//
// Why a render_block_* filter and NOT a woocommerce_before_shop_loop
// echo for the result count: the legacy loop action fires inside
// wp:woocommerce/product-collection's server render too, so an echo
// paints the count twice — once in the title-row block, once floating
// above the product grid. The render_block filter rewrites the
// already-correctly-positioned <p> in place. See the "23 ITEMS off in
// the middle of nowhere" post-mortem in git history for the long form.
add_filter( 'woocommerce_show_page_title', '__return_true' );

add_filter(
	'woocommerce_pagination_args',
	static function ( array $args ): array {
		$args['prev_text'] = '&larr;';
		$args['next_text'] = '&rarr;';
		return $args;
	}
);

add_filter(
	'render_block_woocommerce/product-results-count',
	static function ( $block_content ) {
		if ( is_admin() || '' === trim( (string) $block_content ) ) {
			return $block_content;
		}
		if ( ! function_exists( 'wc_get_loop_prop' ) ) {
			return $block_content;
		}
		$total = (int) wc_get_loop_prop( 'total', 0 );
		if ( $total <= 0 ) {
			return $block_content;
		}
		$label = sprintf(
			/* translators: %d: number of products in the current archive. */
			esc_html( _n( '%d product', '%d products', $total, 'lysholm' ) ),
			$total
		);
		$rewritten = preg_replace(
			'#(<p\b[^>]*\bclass="[^"]*\bwoocommerce-result-count\b[^"]*"[^>]*>)[\s\S]*?(</p>)#i',
			'$1' . $label . '$2',
			$block_content,
			1,
			$count
		);
		return ( $count > 0 && null !== $rewritten ) ? $rewritten : $block_content;
	},
	20
);

add_filter(
	'woocommerce_default_catalog_orderby_options',
	static function ( array $options ): array {
		if ( isset( $options['menu_order'] ) ) {
			$options['menu_order'] = __( 'Selected', 'lysholm' );
		}
		if ( isset( $options['popularity'] ) ) {
			$options['popularity'] = __( 'Most loved', 'lysholm' );
		}
		if ( isset( $options['rating'] ) ) {
			$options['rating'] = __( 'Best loved', 'lysholm' );
		}
		if ( isset( $options['date'] ) ) {
			$options['date'] = __( 'Newest first', 'lysholm' );
		}
		if ( isset( $options['price'] ) ) {
			$options['price'] = __( 'Price: low first', 'lysholm' );
		}
		if ( isset( $options['price-desc'] ) ) {
			$options['price-desc'] = __( 'Price: high first', 'lysholm' );
		}
		return $options;
	}
);

add_filter(
	'woocommerce_catalog_orderby',
	static function ( array $options ): array {
		if ( isset( $options['menu_order'] ) ) {
			$options['menu_order'] = __( 'Selected', 'lysholm' );
		}
		return $options;
	}
);

add_filter(
	'gettext',
	static function ( $translation, $text, $domain ) {
		if ( 'woocommerce' !== $domain && 'default' !== $domain ) {
			return $translation;
		}
		// WC default => Lysholm voice. Per-theme overrides ship in each
		// theme's functions.php so divergence is visible to the gate
		// (see check_wc_microcopy_distinct_across_themes).
		static $map = array(
			'Estimated total'                                                               => 'Final',
			'Proceed to Checkout'                                                           => 'Continue',
			'Proceed to checkout'                                                           => 'Continue',
			'Lost your password?'                                                           => 'Forgot? Reset',
			'Username or email address'                                                     => 'Email',
			'Username or Email Address'                                                     => 'Email',
			'+ Add apartment, suite, etc.'                                                  => '+ Floor or apartment',
			'You are currently checking out as a guest.'                                    => 'Already a customer? Sign in to autofill.',
			'Showing the single result'                                                     => 'Just one',
			'Default sorting'                                                               => 'Selected',
			'No products were found matching your selection.'                               => 'No matches for that combination.',
			'No products in the cart.'                                                      => 'Cart is empty.',
			'Your cart is currently empty!'                                                 => 'Cart is empty.',
			'Your cart is currently empty.'                                                 => 'Cart is empty.',
			'Return to shop'                                                                => 'Browse more',
			'Return To Shop'                                                                => 'Browse more',
			'Have a coupon?'                                                                => 'Coupon code?',
			'Update cart'                                                                   => 'Refresh',
			'Place order'                                                                   => 'Confirm order',
			'Apply coupon'                                                                  => 'Apply',
			'Coupon code'                                                                   => 'Voucher code',
			'Order details'                                                                 => 'Receipt',
			'Order summary'                                                                 => 'Order recap',
			'Cart subtotal'                                                                 => 'Subtotal',
			'Add to cart'                                                                   => 'Add to basket',
			'Customer details'                                                              => 'Customer',
			'Save my name, email, and website in this browser for the next time I comment.' => 'Remember next time.',
			'Be the first to review'                                                        => 'Be the first to review',
			'Your review'                                                                   => 'Note',
			'Your rating'                                                                   => 'Score',
			'Submit'                                                                        => 'Submit',
			'Description'                                                                   => 'Description',
			'Reviews'                                                                       => 'Reviews',
			'Additional information'                                                        => 'Notes',
			'View cart'                                                                     => 'View cart',
			'View Cart'                                                                     => 'View cart',
			'Choose an option'                                                              => 'Choose one',
			'Clear'                                                                         => 'Clear',
			'Login'                                                                         => 'Sign in',
			'Log in'                                                                        => 'Sign in',
			'Log out'                                                                       => 'Sign out',
			'Register'                                                                      => 'Open an account',
			'Remember me'                                                                   => 'Stay signed in',
			'My account'                                                                    => 'Account',
			'My Account'                                                                    => 'Account',
			'Order received'                                                                => 'Thanks!',
			'Thank you. Your order has been received.'                                      => 'Thank you. Your order is recorded.',
			'You may also like&hellip;'                                                     => 'More like this',
			'You may also like…'                                                            => 'More like this',
			'Related products'                                                              => 'Related',
		);
		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	},
	20,
	3
);

add_filter(
	'woocommerce_blocks_cart_totals_label',
	static function (): string {
		return __( 'Final', 'lysholm' );
	}
);

add_filter(
	'woocommerce_order_button_text',
	static function (): string {
		return __( 'Confirm order', 'lysholm' );
	}
);

add_filter(
	'woocommerce_form_field',
	static function ( $field, $key, $args, $value ) {
		if ( false !== strpos( (string) $field, '<abbr class="required"' ) ) {
			$field = preg_replace(
				'#<abbr class="required"[^>]*>\*</abbr>#i',
				'<span class="wo-required-mark" aria-hidden="true">▪</span>',
				(string) $field
			);
		}
		return $field;
	},
	20,
	4
);

// === END wc microcopy ===
