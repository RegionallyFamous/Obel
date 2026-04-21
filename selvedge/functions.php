<?php
/**
 * Selvedge theme bootstrap.
 *
 * Block-only WooCommerce starter theme. All visual styling lives in
 * theme.json; templates and parts are pure block markup. The only PHP
 * code in the theme is this single after_setup_theme hook.
 *
 * @package Selvedge
 */

declare( strict_types=1 );

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'selvedge', get_template_directory() . '/languages' );

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
			'selvedge'          => array(
				'label'       => __( 'Selvedge', 'selvedge' ),
				'description' => __( 'Generic starter patterns. Delete or replace per project.', 'selvedge' ),
			),
			'woo-commerce'  => array(
				'label'       => __( 'Shop', 'selvedge' ),
				'description' => __( 'Patterns for product listings, collections, and shop sections.', 'selvedge' ),
			),
			'featured'      => array(
				'label'       => __( 'Hero', 'selvedge' ),
				'description' => __( 'Full-width hero and banner patterns.', 'selvedge' ),
			),
			'call-to-action' => array(
				'label'       => __( 'Call to action', 'selvedge' ),
				'description' => __( 'Conversion-focused banners and newsletter signups.', 'selvedge' ),
			),
			'testimonials'  => array(
				'label'       => __( 'Testimonials', 'selvedge' ),
				'description' => __( 'Social proof and customer quote patterns.', 'selvedge' ),
			),
			'footer'        => array(
				'label'       => __( 'Footer', 'selvedge' ),
				'description' => __( 'Footer layout patterns.', 'selvedge' ),
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
 * Selvedge is dark editorial; "Reduced" reads as a price tag at a quiet
 * boutique rather than a flash sale shout. The pill styling itself lives in
 * theme.json -> styles.css.
 */
add_filter(
	'woocommerce_sale_flash',
	static function (): string {
		return '<span class="onsale">' . esc_html__( 'Reduced', 'selvedge' ) . '</span>';
	}
);

/**
 * Shop-by-category cover tiles get the first product's featured image.
 *
 * Why this filter exists
 * ----------------------
 * `wp:terms-query` doesn't ship a "term thumbnail" core block, and
 * WC's `product_cat` taxonomy ships an empty `thumbnail_id` term-meta
 * for every category created by a CSV import (`wo-import.php` doesn't
 * sideload category art -- only product art). So the front-page
 * "Shop by Category" terms-query renders a `wp:cover` block with no
 * image source and the cover paints the contrast color flat -- a giant
 * brown box with the term name floating in it. Two failure modes
 * compound: (a) no image, and (b) the cover's `aspect-ratio:portrait`
 * makes each tile occupy a 4:5 slice of the column, so any responsive
 * stacking turns it into a viewport-tall block.
 *
 * The fix here is content-driven: pick the first published product in
 * the category that has a featured image, and inject that image into
 * the cover as `<img class="wp-block-cover__image-background">` (the
 * exact element WP emits for cover blocks given a `url` attribute).
 * That keeps the cover's overlay + dim + content positioning exactly
 * as the editor configured them; we're only filling in the missing
 * background image.
 *
 * Marker className
 * ----------------
 * The filter only fires on `core/cover` blocks whose className includes
 * `selvedge-cat-cover`. Without the marker we'd touch every cover on
 * the site and waste a `wc_get_products` call per render.
 *
 * Caching
 * -------
 * One `wc_get_products(['category'=>$slug,'limit'=>1])` per render.
 * That's 5 queries on the front page (one per category tile) and zero
 * elsewhere. WP object cache hits subsequent renders during the same
 * request. Not memoizing across requests because the "first product"
 * legitimately changes when the catalogue is edited and the cost is
 * trivial. If perf ever becomes a concern, wrap the lookup in a
 * `wp_cache_get` keyed on `selvedge:cat-img:<term_id>` with a short
 * TTL.
 *
 * Failure modes (handled silently)
 * --------------------------------
 *   * No termId in context (the cover wasn't actually inside a
 *     term-template) -> return original markup unchanged.
 *   * Term has zero products / no published products with images ->
 *     return original markup; the cover paints the contrast color
 *     (the original behaviour, but now an explicit fallback rather
 *     than the default state).
 *   * WC isn't active -> return original markup. The check.py gate
 *     guarantees WC is present in every theme that ships this code,
 *     but the runtime guard is cheap insurance for non-Playground
 *     installs.
 */
add_filter(
	'render_block',
	static function ( string $block_content, array $block, WP_Block $instance ): string {
		if ( 'core/cover' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}
		$class_name = (string) ( $block['attrs']['className'] ?? '' );
		if ( false === strpos( $class_name, 'selvedge-cat-cover' ) ) {
			return $block_content;
		}
		$term_id = (int) ( $instance->context['termId'] ?? 0 );
		if ( ! $term_id || ! function_exists( 'wc_get_products' ) ) {
			return $block_content;
		}
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return $block_content;
		}

		// First, honour an explicit category thumbnail if WC ever
		// gets one set (some themes / importers do attach term meta).
		$image_id  = (int) get_term_meta( $term_id, 'thumbnail_id', true );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';

		// Fallback: pull the first product in the category that has
		// a featured image. WC's `product_cat` IDs and the underlying
		// term IDs are the same, so we can pass the term_id directly.
		if ( ! $image_url ) {
			$products = wc_get_products(
				array(
					'category' => array( $term->slug ),
					'status'   => 'publish',
					'limit'    => 5,
					'orderby'  => 'date',
					'order'    => 'DESC',
					'return'   => 'ids',
				)
			);
			foreach ( (array) $products as $pid ) {
				$tid = get_post_thumbnail_id( $pid );
				if ( $tid ) {
					$image_url = wp_get_attachment_image_url( $tid, 'large' );
					if ( $image_url ) {
						break;
					}
				}
			}
		}

		// Inject `<img class="wp-block-cover__image-background">` as
		// the first child of `.wp-block-cover` -- exactly where core
		// puts it when the cover block has a `url` attribute. The
		// dim-overlay span and inner-container come AFTER the img,
		// which lets the existing CSS layering paint the dim on top
		// of the photo and the term-name + count on top of both.
		// Skips silently if there's no image to inject (the cover
		// then paints its overlay color flat -- the original
		// behaviour, retained as an explicit fallback).
		$updated = $block_content;
		if ( $image_url ) {
			$img = sprintf(
				'<img class="wp-block-cover__image-background selvedge-cat-cover__img" alt="" src="%s" loading="lazy" decoding="async" />',
				esc_url( $image_url )
			);
			// Splice the img right after the opening `<div
			// class="wp-block-cover ...">`. Using a simple regex
			// against the cover's leading tag is safe here because
			// the block's render output always starts with that
			// single <div ...> (see core/cover/render.php).
			$spliced = preg_replace(
				'/(<div\s+class="[^"]*wp-block-cover[^"]*"[^>]*>)/',
				'$1' . $img,
				$block_content,
				1
			);
			if ( is_string( $spliced ) ) {
				$updated = $spliced;
			}
		}

		// Wrap the entire cover in an `<a>` so the WHOLE tile is
		// clickable, not just the small term-name heading inside.
		// `wp:term-name` had `isLink:true` until we removed it from
		// the front-page.html, exactly because nesting an `<a>` inside
		// the wrapping `<a>` is invalid HTML5 (the spec disallows
		// `<a>` descendants of `<a>`); browsers split the inner
		// anchor and the click target becomes whichever WP rendered
		// first, which on Chrome is the inner one -- so the giant
		// image area is dead-clickable. Removing the inner link and
		// wrapping the whole cover in one outer anchor gives us a
		// single, large, accessible click target.
		//
		// `<a>` wrapping a flow-content `<div>` is valid in HTML5
		// (the spec was relaxed in 5.0 specifically to enable this
		// "card" pattern). Modern screen readers announce the wrapped
		// content normally; we add an explicit `aria-label` so the
		// accessible name is the term name + count (otherwise screen
		// readers would read out the visual content "Curiosities 11"
		// which is also fine, but the explicit label removes ambiguity
		// when the count format ever changes).
		$term_link = get_term_link( $term );
		if ( is_wp_error( $term_link ) || ! $term_link ) {
			return $updated;
		}
		$count       = (int) $term->count;
		$aria_label  = sprintf(
			/* translators: 1: category name, 2: number of products */
			_n( '%1$s, %2$d product', '%1$s, %2$d products', max( 1, $count ), 'selvedge' ),
			$term->name,
			$count
		);
		return sprintf(
			'<a class="selvedge-cat-cover__link" href="%s" aria-label="%s">%s</a>',
			esc_url( $term_link ),
			esc_attr( $aria_label ),
			$updated
		);
	},
	10,
	3
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
// Shopper-facing WC microcopy in the Selvedge voice.
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
			esc_html( _n( '%d piece', '%d pieces', $total, 'selvedge' ) ),
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
			$options['menu_order'] = __( 'Picks', 'selvedge' );
		}
		if ( isset( $options['popularity'] ) ) {
			$options['popularity'] = __( 'Top movers', 'selvedge' );
		}
		if ( isset( $options['rating'] ) ) {
			$options['rating'] = __( 'Best reviewed', 'selvedge' );
		}
		if ( isset( $options['date'] ) ) {
			$options['date'] = __( 'Latest in', 'selvedge' );
		}
		if ( isset( $options['price'] ) ) {
			$options['price'] = __( 'Price: cheap first', 'selvedge' );
		}
		if ( isset( $options['price-desc'] ) ) {
			$options['price-desc'] = __( 'Price: expensive first', 'selvedge' );
		}
		return $options;
	}
);

add_filter(
	'woocommerce_catalog_orderby',
	static function ( array $options ): array {
		if ( isset( $options['menu_order'] ) ) {
			$options['menu_order'] = __( 'Picks', 'selvedge' );
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
		// WC default => Selvedge voice. Per-theme overrides ship in each
		// theme's functions.php so divergence is visible to the gate
		// (see check_wc_microcopy_distinct_across_themes).
		static $map = array(
			'Estimated total'                                                               => 'Order total',
			'Proceed to Checkout'                                                           => 'Place order',
			'Proceed to checkout'                                                           => 'Place order',
			'Lost your password?'                                                           => 'Lost it? Reset',
			'Username or email address'                                                     => 'Email',
			'Username or Email Address'                                                     => 'Email',
			'+ Add apartment, suite, etc.'                                                  => '+ Unit number',
			'You are currently checking out as a guest.'                                    => 'Got an account? Sign in to fill it in.',
			'Showing the single result'                                                     => '1 piece',
			'Default sorting'                                                               => 'Picks',
			'No products were found matching your selection.'                               => 'Nothing fits those filters.',
			'No products in the cart.'                                                      => 'Cart\'s empty.',
			'Your cart is currently empty!'                                                 => 'Cart\'s empty.',
			'Your cart is currently empty.'                                                 => 'Cart\'s empty.',
			'Return to shop'                                                                => 'Back to shop',
			'Return To Shop'                                                                => 'Back to shop',
			'Have a coupon?'                                                                => 'Got a code?',
			'Update cart'                                                                   => 'Update',
			'Place order'                                                                   => 'Pay & complete',
			'Apply coupon'                                                                  => 'Apply',
			'Coupon code'                                                                   => 'Promo code',
			'Order details'                                                                 => 'Your order',
			'Order summary'                                                                 => 'Recap',
			'Cart subtotal'                                                                 => 'Subtotal',
			'Add to cart'                                                                   => 'Add to bag',
			'Customer details'                                                              => 'Your information',
			'Save my name, email, and website in this browser for the next time I comment.' => 'Remember me.',
			'Be the first to review'                                                        => 'Be the first to review',
			'Your review'                                                                   => 'Comments',
			'Your rating'                                                                   => 'Rating',
			'Submit'                                                                        => 'Send review',
			'Description'                                                                   => 'Description',
			'Reviews'                                                                       => 'Reviews',
			'Additional information'                                                        => 'Specs',
			'View cart'                                                                     => 'View cart',
			'View Cart'                                                                     => 'View cart',
			'Choose an option'                                                              => 'Choose',
			'Clear'                                                                         => 'Wipe',
			'Login'                                                                         => 'Sign in',
			'Log in'                                                                        => 'Sign in',
			'Log out'                                                                       => 'Sign out',
			'Register'                                                                      => 'Sign up',
			'Remember me'                                                                   => 'Stay logged in',
			'My account'                                                                    => 'Account',
			'My Account'                                                                    => 'Account',
			'Order received'                                                                => 'Thanks',
			'Thank you. Your order has been received.'                                      => 'Thanks. Your order\'s in.',
			'You may also like&hellip;'                                                     => 'Pairs well with',
			'You may also like…'                                                            => 'Pairs well with',
			'Related products'                                                              => 'More like this',
		);
		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	},
	20,
	3
);

add_filter(
	'woocommerce_blocks_cart_totals_label',
	static function (): string {
		return __( 'Order total', 'selvedge' );
	}
);

add_filter(
	'woocommerce_order_button_text',
	static function (): string {
		return __( 'Pay & complete', 'selvedge' );
	}
);

add_filter(
	'woocommerce_form_field',
	static function ( $field, $key, $args, $value ) {
		if ( false !== strpos( (string) $field, '<abbr class="required"' ) ) {
			$field = preg_replace(
				'#<abbr class="required"[^>]*>\*</abbr>#i',
				'<span class="wo-required-mark" aria-hidden="true">•</span>',
				(string) $field
			);
		}
		return $field;
	},
	20,
	4
);

// === END wc microcopy ===

// === BEGIN my-account ===
//
// Branded WC My Account dashboard.
//
// FAIL MODE WE'RE FIXING
// ----------------------
// WC's default classic My Account renders a `<nav>` (sidebar links)
// + `<div class="woocommerce-MyAccount-content">` (welcome paragraph
// + "From your account dashboard you can…" paragraph with link
// salad). Without theme intervention WC's frontend.css applies
// `nav { float:left; width:30% }` and `content { float:right;
// width:68% }` inside whatever container the page template provides.
// Our default `page.html` uses a 560px "prose" content size, so 30%
// of that is ~170px (a thin floating nav) and 68% is ~380px (a
// cramped text column). The result is a vast empty page with two
// tiny columns drifting in the middle — not a brand moment, not
// even usable.
//
// FIX
// ---
// `templates/page-my-account.html` widens the layout to `wideSize`
// (1280px), and the CSS block in theme.json (search for
// `.woocommerce-account .woocommerce {`) replaces WC's float layout
// with a CSS grid: a fixed-width sidebar for the nav + a fluid main
// column for the dashboard content. Then the hooks below replace the
// stock dashboard content with a greeting + 3-card quick-link grid
// so the dashboard tab actually feels designed instead of "WC defaults
// painted on a block theme".
//
// Hooks used:
//   * `woocommerce_account_dashboard` — the action that fires inside
//     `myaccount/dashboard.php`. WC ships a default callback
//     (`wc_account_dashboard`) that prints the welcome paragraphs;
//     we remove it and re-add our own at the same priority so the
//     stock copy disappears and the branded markup paints in its
//     place.
//   * `woocommerce_before_account_navigation` / `_after_…` — we
//     don't add wrappers here because the CSS grid already handles
//     placement. The hooks are listed for the next person to know
//     where to inject if the design grows.
//
// Per-theme: each theme owns its own `// === BEGIN my-account ===`
// block in its `functions.php` so the greeting wording, card titles,
// and callouts stay theme-distinct (Obel = quiet/editorial, Chonk =
// brutalist all-caps, Selvedge = workwear, Lysholm = aquavit-precise,
// Aero = sport/technical). Same structural hooks, different voice.
add_action(
	'init',
	static function (): void {
		if ( ! function_exists( 'wc_get_account_menu_items' ) ) {
			return;
		}
		// `wc_account_dashboard` is the WC core callback that prints
		// the "Hello %s (not %s? Log out)" + "From your account
		// dashboard you can…" paragraphs. Remove it once at init so
		// our replacement is the only thing rendered inside the
		// dashboard tab.
		remove_action( 'woocommerce_account_dashboard', 'wc_account_dashboard' );
		add_action( 'woocommerce_account_dashboard', 'selvedge_render_account_dashboard' );
	},
	20
);

if ( ! function_exists( 'selvedge_render_account_dashboard' ) ) {
	/**
	 * Render the Selvedge-branded My Account dashboard tab.
	 *
	 * Replaces WC's default 2-paragraph greeting with:
	 *   1. A display-font greeting using the customer's first name
	 *      (or login name as a fallback).
	 *   2. A short workshop-voiced lede in the Selvedge register.
	 *   3. A 3-card quick-link grid linking to Orders, Addresses,
	 *      and Account details — the surfaces that justify having
	 *      an account in the first place.
	 *
	 * Markup is hand-written (not block markup) because this fires
	 * inside WC's classic shortcode render where block parsing is
	 * already past. The class names (`wo-account-*`) match the CSS
	 * grid + card rules in theme.json's styles.css block and stay
	 * shared across themes so a single CSS hook restyles every
	 * variant.
	 */
	function selvedge_render_account_dashboard(): void {
		$user  = wp_get_current_user();
		$name  = $user && $user->ID ? trim( $user->first_name ) : '';
		if ( '' === $name && $user && $user->ID ) {
			$name = $user->display_name ? $user->display_name : $user->user_login;
		}
		if ( '' === $name ) {
			$name = __( 'maker', 'selvedge' );
		}

		$cards = array(
			array(
				'eyebrow' => __( 'Dispatched', 'selvedge' ),
				'title'   => __( 'Order log', 'selvedge' ),
				'lede'    => __( 'Follow each piece from the bench to your door, and reorder what you wear out.', 'selvedge' ),
				'cta'     => __( 'Open order log', 'selvedge' ),
				'href'    => wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ),
			),
			array(
				'eyebrow' => __( 'Routes', 'selvedge' ),
				'title'   => __( 'Shipping book', 'selvedge' ),
				'lede'    => __( 'Keep your delivery and billing routes ready for the next dispatch.', 'selvedge' ),
				'cta'     => __( 'Edit routes', 'selvedge' ),
				'href'    => wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) ),
			),
			array(
				'eyebrow' => __( 'Maker file', 'selvedge' ),
				'title'   => __( 'Workshop file', 'selvedge' ),
				'lede'    => __( 'Update the name, contact, and password we keep on file for you.', 'selvedge' ),
				'cta'     => __( 'Edit file', 'selvedge' ),
				'href'    => wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ),
			),
		);
		?>
<div class="wo-account-dashboard">
	<header class="wo-account-greeting">
		<p class="wo-account-greeting__eyebrow"><?php esc_html_e( 'On the bench', 'selvedge' ); ?></p>
		<h2 class="wo-account-greeting__title"><?php
			/* translators: %s: customer's first name. */
			echo esc_html( sprintf( __( 'Welcome to the bench, %s.', 'selvedge' ), $name ) );
		?></h2>
		<p class="wo-account-greeting__lede"><?php esc_html_e( 'Every order, address, and pinned detail kept on the bench. Pick up wherever you set it down.', 'selvedge' ); ?></p>
	</header>

	<ul class="wo-account-cards">
		<?php foreach ( $cards as $card ) : ?>
			<li class="wo-account-card">
				<p class="wo-account-card__eyebrow"><?php echo esc_html( $card['eyebrow'] ); ?></p>
				<h3 class="wo-account-card__title"><?php echo esc_html( $card['title'] ); ?></h3>
				<p class="wo-account-card__lede"><?php echo esc_html( $card['lede'] ); ?></p>
				<a class="wo-account-card__cta" href="<?php echo esc_url( $card['href'] ); ?>"><?php echo esc_html( $card['cta'] ); ?> <span aria-hidden="true">&rarr;</span></a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
		<?php
	}
}
// === END my-account ===
