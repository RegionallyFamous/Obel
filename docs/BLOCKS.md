# Blocks Used

Inventory of blocks this theme actually styles, grouped by purpose. Read this when you need to know "what block can I use to do X?".

For the full registered set, see [Gutenberg block library](https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library/src) and [WooCommerce blocks](https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce/client/blocks).

## Layout containers

| Block | Use for |
|---|---|
| `core/group` | Generic flex/stack/grid container with padding, background, layout type. Default container. |
| `core/columns` + `core/column` | Multi-column responsive layouts. |
| `core/cover` | Image with overlay + content on top. Hero sections. |
| `core/media-text` | Two-column image + text (brand story, feature highlight). |
| `core/row` | Inline flex layout (variation of group). |
| `core/stack` | Vertical flex layout (variation of group). |
| `core/spacer` | Manual vertical gap. Prefer block padding/margin via tokens instead. |
| `core/separator` | Horizontal divider. Variations: `is-style-default`, `is-style-wide`, `is-style-dots`. |

## Text + headings

| Block | Use for |
|---|---|
| `core/heading` | h1-h6 (level chosen via attribute). |
| `core/paragraph` | Body copy. |
| `core/list` + `core/list-item` | Bulleted or ordered lists. |
| `core/quote` | Block quote with optional citation. |
| `core/pullquote` | Larger callout quote, often left-aligned. |
| `core/verse` | Preformatted verse (whitespace preserved). |
| `core/preformatted` | Preformatted text. |
| `core/code` | Inline code block. Monospace. |
| `core/details` | Native HTML disclosure widget. |
| `core/footnotes` | Auto-generated post footnotes. |

## Interactive

| Block | Use for |
|---|---|
| `core/button` + `core/buttons` | Primary CTA. Has `is-style-fill` and `is-style-outline` variations by default. |
| `core/accordion` + `core/accordion-item` + `core/accordion-heading` + `core/accordion-panel` | FAQ-style collapsible content. WP 6.8+. |
| `core/navigation` + `core/navigation-link` + `core/navigation-submenu` | Site navigation. |
| `core/page-list` | Auto-generated list of pages. Used in footer column patterns. |
| `core/social-links` + `core/social-link` | Branded social icons. |

## Media

| Block | Use for |
|---|---|
| `core/image` | Single image with caption. |
| `core/gallery` | Multi-image gallery (renders as a grid). |
| `core/video`, `core/audio` | Self-hosted media. |
| `core/embed` | Embeds (YouTube, Vimeo, etc.) -- specific embed slugs are also available. |
| `core/file` | Downloadable file. |

## Site identity / templates

| Block | Use for |
|---|---|
| `core/site-title` | Renders the site title from settings. |
| `core/site-tagline` | Renders the tagline. |
| `core/site-logo` | Renders the uploaded logo. |
| `core/template-part` | Includes a reusable region (header, footer). |
| `core/post-title` | Title of the current post in a query/template. |
| `core/post-content` | Body of the current post. |
| `core/post-excerpt` | Short excerpt. |
| `core/post-featured-image` | Featured image. |
| `core/post-date`, `core/post-author`, `core/post-author-name` | Post metadata. |
| `core/post-time-to-read` | Estimated reading time. |
| `core/post-comments-count`, `core/post-comments-link` | Comments meta. |
| `core/terms-query` | Loop over taxonomy terms (categories, tags). |
| `core/post-terms` | Render the current post's terms. |
| `core/query` + `core/post-template` | Generic post loop with template inside. |
| `core/query-pagination` + children | Pagination for post queries. |
| `core/query-no-results` | "No results" placeholder inside a query. |

## Comments

| Block | Use for |
|---|---|
| `core/comments` | Comments wrapper. |
| `core/comment-template` | Per-comment template. |
| `core/comment-author-name`, `core/comment-author-avatar` | Author info. |
| `core/comment-date`, `core/comment-content`, `core/comment-edit-link`, `core/comment-reply-link` | Comment body parts. |
| `core/post-comments-form` | Submission form. |

## Forms (search, login)

| Block | Use for |
|---|---|
| `core/search` | Search form. |
| `core/loginout` | Login / logout link. |
| `core/avatar` | User avatar. |

## WooCommerce -- Product display

| Block | Use for |
|---|---|
| `woocommerce/product-collection` | Modern queryable product loop (replaces `products`). |
| `woocommerce/product-template` | Per-product template inside a Product Collection. |
| `woocommerce/product-image`, `woocommerce/product-image-gallery` | Product photo. |
| `woocommerce/product-title` | Title. |
| `woocommerce/product-price` | Price (handles sales, ranges). |
| `woocommerce/product-summary` | Short description. |
| `woocommerce/product-rating`, `woocommerce/product-rating-stars`, `woocommerce/product-rating-counter` | Reviews. |
| `woocommerce/product-button` | Add-to-cart button (single click for simple products). |
| `woocommerce/product-meta` | SKU, categories, tags. |
| `woocommerce/product-sku`, `woocommerce/product-stock-indicator` | Specific meta blocks. |
| `woocommerce/product-sale-badge` | "Sale" pill on product cards. |
| `woocommerce/related-products` | Related products carousel/grid. |

## WooCommerce -- Filters (use inside Product Collection)

| Block | Use for |
|---|---|
| `woocommerce/product-filters` | Filter container. Holds the inner blocks below. |
| `woocommerce/product-filter-active` | Active filter chips. |
| `woocommerce/product-filter-attribute` | Attribute filter (size, color, etc.). |
| `woocommerce/product-filter-price` | Price range filter. |
| `woocommerce/product-filter-rating` | Rating filter. |
| `woocommerce/product-filter-status` | Stock status / sale filter. |
| `woocommerce/product-filter-category`, `woocommerce/product-filter-tag`, `woocommerce/product-filter-brand` | Taxonomy filters. |
| `woocommerce/product-filter-checkbox-list`, `woocommerce/product-filter-chips` | UI styles for the above. |
| `woocommerce/product-filter-clear-button` | Reset filters. |

## WooCommerce -- Cart

| Block | Use for |
|---|---|
| `woocommerce/cart` | Cart wrapper. |
| `woocommerce/filled-cart-block`, `woocommerce/empty-cart-block` | Branches for empty vs populated cart. |
| `woocommerce/cart-items-block`, `woocommerce/cart-line-items-block` | Item table. |
| `woocommerce/cart-totals-block`, `woocommerce/cart-order-summary-block` + child blocks | Totals area. |
| `woocommerce/proceed-to-checkout-block` | Primary CTA. |
| `woocommerce/cart-cross-sells-block` | Recommended add-ons. |
| `woocommerce/mini-cart`, `woocommerce/mini-cart-contents` | Header drawer mini-cart. |

## WooCommerce -- Checkout

| Block | Use for |
|---|---|
| `woocommerce/checkout` | Checkout wrapper. |
| `woocommerce/checkout-fields-block`, `woocommerce/checkout-totals-block` | Two-column checkout layout. |
| `woocommerce/checkout-contact-information-block` | Email field. |
| `woocommerce/checkout-shipping-address-block`, `woocommerce/checkout-billing-address-block` | Address fields. |
| `woocommerce/checkout-shipping-method-block`, `woocommerce/checkout-payment-block` | Shipping + payment selection. |
| `woocommerce/checkout-actions-block`, `woocommerce/checkout-terms-block` | Submit + terms agreement. |
| `woocommerce/checkout-order-note-block` | Customer note field. |
| `woocommerce/checkout-order-summary-block` + child blocks | Order summary sidebar. |

## WooCommerce -- Order confirmation

| Block | Use for |
|---|---|
| `woocommerce/order-confirmation-summary` | Order intro. |
| `woocommerce/order-confirmation-status` | Status indicator. |
| `woocommerce/order-confirmation-totals`, `woocommerce/order-confirmation-totals-wrapper` | Receipt. |
| `woocommerce/order-confirmation-shipping-address`, `woocommerce/order-confirmation-billing-address` | Address summary. |
| `woocommerce/order-confirmation-additional-fields`, `woocommerce/order-confirmation-additional-information` | Custom field display. |
| `woocommerce/order-confirmation-create-account` | Account-creation prompt. |
| `woocommerce/order-confirmation-downloads` | Downloadable product links. |

## WooCommerce -- Other

| Block | Use for |
|---|---|
| `woocommerce/store-notices` | Site-wide notices area (errors, success messages). |
| `woocommerce/customer-account` | Login/account icon for the header. |
| `woocommerce/breadcrumbs` | Shop breadcrumbs. |
| `woocommerce/featured-product`, `woocommerce/featured-category` | Single-item highlight blocks. |
