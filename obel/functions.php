<?php
/**
 * Obel theme bootstrap.
 *
 * Block-only WooCommerce starter theme. All visual styling lives in
 * theme.json; templates and parts are pure block markup. The only PHP
 * code in the theme is this single after_setup_theme hook.
 *
 * @package Obel
 */

declare( strict_types=1 );

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'obel', get_template_directory() . '/languages' );

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
			'obel'          => array(
				'label'       => __( 'Obel', 'obel' ),
				'description' => __( 'Generic starter patterns. Delete or replace per project.', 'obel' ),
			),
			'woo-commerce'  => array(
				'label'       => __( 'Shop', 'obel' ),
				'description' => __( 'Patterns for product listings, collections, and shop sections.', 'obel' ),
			),
			'featured'      => array(
				'label'       => __( 'Hero', 'obel' ),
				'description' => __( 'Full-width hero and banner patterns.', 'obel' ),
			),
			'call-to-action' => array(
				'label'       => __( 'Call to action', 'obel' ),
				'description' => __( 'Conversion-focused banners and newsletter signups.', 'obel' ),
			),
			'testimonials'  => array(
				'label'       => __( 'Testimonials', 'obel' ),
				'description' => __( 'Social proof and customer quote patterns.', 'obel' ),
			),
			'footer'        => array(
				'label'       => __( 'Footer', 'obel' ),
				'description' => __( 'Footer layout patterns.', 'obel' ),
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
 * The exclamation point and the literal word "Sale!" are an immediate "this
 * is a stock WooCommerce store" tell. The wrapper styling lives in
 * theme.json -> styles.css (`.products li.product .onsale, span.onsale`),
 * which already turns the badge into an editorial uppercase pill; here we
 * just swap the copy so each theme has its own brand voice.
 */
add_filter(
	'woocommerce_sale_flash',
	static function (): string {
		return '<span class="onsale">' . esc_html__( 'On sale', 'obel' ) . '</span>';
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
// Shopper-facing WC microcopy in the Obel voice.
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
			esc_html( _n( '%d item', '%d items', $total, 'obel' ) ),
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
			$options['menu_order'] = __( 'Featured', 'obel' );
		}
		if ( isset( $options['popularity'] ) ) {
			$options['popularity'] = __( 'Best sellers', 'obel' );
		}
		if ( isset( $options['rating'] ) ) {
			$options['rating'] = __( 'Top rated', 'obel' );
		}
		if ( isset( $options['date'] ) ) {
			$options['date'] = __( 'New arrivals', 'obel' );
		}
		if ( isset( $options['price'] ) ) {
			$options['price'] = __( 'Price: low to high', 'obel' );
		}
		if ( isset( $options['price-desc'] ) ) {
			$options['price-desc'] = __( 'Price: high to low', 'obel' );
		}
		return $options;
	}
);

add_filter(
	'woocommerce_catalog_orderby',
	static function ( array $options ): array {
		if ( isset( $options['menu_order'] ) ) {
			$options['menu_order'] = __( 'Featured', 'obel' );
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
		// WC default => Obel voice. Per-theme overrides ship in each
		// theme's functions.php so divergence is visible to the gate
		// (see check_wc_microcopy_distinct_across_themes).
		static $map = array(
			'Estimated total'                                                               => 'Total',
			'Proceed to Checkout'                                                           => 'Checkout',
			'Proceed to checkout'                                                           => 'Checkout',
			'Lost your password?'                                                           => 'Forgot password',
			'Username or email address'                                                     => 'Email',
			'Username or Email Address'                                                     => 'Email',
			'+ Add apartment, suite, etc.'                                                  => 'Add address line 2',
			'You are currently checking out as a guest.'                                    => 'Have an account? Sign in to autofill.',
			'Showing the single result'                                                     => '1 item',
			'Default sorting'                                                               => 'Featured',
			'No products were found matching your selection.'                               => 'Nothing matches that filter yet.',
			'No products in the cart.'                                                      => 'Your cart is empty.',
			'Your cart is currently empty!'                                                 => 'Your cart is empty.',
			'Your cart is currently empty.'                                                 => 'Your cart is empty.',
			'Return to shop'                                                                => 'Continue shopping',
			'Return To Shop'                                                                => 'Continue shopping',
			'Have a coupon?'                                                                => 'Coupon code',
			'Update cart'                                                                   => 'Update',
			'Place order'                                                                   => 'Place order',
			'Apply coupon'                                                                  => 'Apply',
			'Coupon code'                                                                   => 'Code',
			'Order details'                                                                 => 'Order',
			'Order summary'                                                                 => 'Summary',
			'Cart subtotal'                                                                 => 'Subtotal',
			'Add to cart'                                                                   => 'Add to cart',
			'Customer details'                                                              => 'Your details',
			'Save my name, email, and website in this browser for the next time I comment.' => 'Remember me for next time.',
			'Be the first to review'                                                        => 'Be the first to review',
			'Your review'                                                                   => 'Review',
			'Your rating'                                                                   => 'Rating',
			'Submit'                                                                        => 'Post review',
			'Description'                                                                   => 'Description',
			'Reviews'                                                                       => 'Reviews',
			'Additional information'                                                        => 'Details',
			'View cart'                                                                     => 'View cart',
			'View Cart'                                                                     => 'View cart',
			'Choose an option'                                                              => 'Select',
			'Clear'                                                                         => 'Reset',
			'Login'                                                                         => 'Sign in',
			'Log in'                                                                        => 'Sign in',
			'Log out'                                                                       => 'Sign out',
			'Register'                                                                      => 'Create account',
			'Remember me'                                                                   => 'Keep me signed in',
			'My account'                                                                    => 'Account',
			'My Account'                                                                    => 'Account',
			'Order received'                                                                => 'Thank you',
			'Thank you. Your order has been received.'                                      => 'Thanks, your order is in.',
			'You may also like&hellip;'                                                     => 'You may also like',
			'You may also like…'                                                            => 'You may also like',
			'Related products'                                                              => 'You may also like',
		);
		return isset( $map[ $text ] ) ? $map[ $text ] : $translation;
	},
	20,
	3
);

add_filter(
	'woocommerce_blocks_cart_totals_label',
	static function (): string {
		return __( 'Total', 'obel' );
	}
);

add_filter(
	'woocommerce_order_button_text',
	static function (): string {
		return __( 'Place order', 'obel' );
	}
);

add_filter(
	'woocommerce_form_field',
	static function ( $field, $key, $args, $value ) {
		if ( false !== strpos( (string) $field, '<abbr class="required"' ) ) {
			$field = preg_replace(
				'#<abbr class="required"[^>]*>\*</abbr>#i',
				'<span class="wo-required-mark" aria-hidden="true">·</span>',
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
		add_action( 'woocommerce_account_dashboard', 'obel_render_account_dashboard' );
	},
	20
);

if ( ! function_exists( 'obel_render_account_dashboard' ) ) {
	/**
	 * Render the Obel-branded My Account dashboard tab.
	 *
	 * Replaces WC's default 2-paragraph greeting with:
	 *   1. A display-font greeting using the customer's first name
	 *      (or login name as a fallback).
	 *   2. A short editorial lede in the Obel voice.
	 *   3. A 3-card quick-link grid linking to Orders, Addresses,
	 *      and Account details — the surfaces that justify having
	 *      an account in the first place.
	 *
	 * Markup is hand-written (not block markup) because this fires
	 * inside WC's classic shortcode render where block parsing is
	 * already past. The class names (`wo-account-*`) match the CSS
	 * grid + card rules in theme.json's styles.css block.
	 */
	function obel_render_account_dashboard(): void {
		$user  = wp_get_current_user();
		$name  = $user && $user->ID ? trim( $user->first_name ) : '';
		if ( '' === $name && $user && $user->ID ) {
			$name = $user->display_name ? $user->display_name : $user->user_login;
		}
		if ( '' === $name ) {
			$name = __( 'there', 'obel' );
		}

		$cards = array(
			array(
				'eyebrow' => __( 'Receipts', 'obel' ),
				'title'   => __( 'Recent orders', 'obel' ),
				'lede'    => __( 'Track shipments and reorder favourites in one tap.', 'obel' ),
				'cta'     => __( 'View orders', 'obel' ),
				'href'    => wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ),
			),
			array(
				'eyebrow' => __( 'Shipping', 'obel' ),
				'title'   => __( 'Saved addresses', 'obel' ),
				'lede'    => __( 'Update your billing and delivery details for faster checkout.', 'obel' ),
				'cta'     => __( 'Edit addresses', 'obel' ),
				'href'    => wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) ),
			),
			array(
				'eyebrow' => __( 'Profile', 'obel' ),
				'title'   => __( 'Account details', 'obel' ),
				'lede'    => __( 'Edit your name, email, and password.', 'obel' ),
				'cta'     => __( 'Edit profile', 'obel' ),
				'href'    => wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ),
			),
		);
		?>
<div class="wo-account-dashboard">
	<header class="wo-account-greeting">
		<p class="wo-account-greeting__eyebrow"><?php esc_html_e( 'Welcome back', 'obel' ); ?></p>
		<h2 class="wo-account-greeting__title"><?php
			/* translators: %s: customer's first name. */
			echo esc_html( sprintf( __( 'Hello, %s.', 'obel' ), $name ) );
		?></h2>
		<p class="wo-account-greeting__lede"><?php esc_html_e( 'A quiet record of every order, address, and detail you trust us with — pick up where you left off.', 'obel' ); ?></p>
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
