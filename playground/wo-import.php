<?php
/**
 * Wonders & Oddities sample-data importer for WordPress Playground.
 *
 * Run via `wp eval-file /wordpress/wo-import.php` after the file has been
 * fetched into the Playground filesystem by a `writeFile` blueprint step.
 *
 * Why this script exists:
 *   The previous version of this importer leaned on
 *   WC_Product_CSV_Importer + WC_Product_CSV_Importer_Controller, which is
 *   not part of WooCommerce's stable public surface. Across WC versions the
 *   helper methods have flipped between public/protected, static/instance,
 *   and the importer's read_file() rejects any path that does not look like
 *   a CSV upload. Each WC release broke the blueprint in a new way.
 *
 *   This rewrite uses only WC's stable public CRUD API:
 *     - WC_Product_Simple / WC_Product_Variable / WC_Product_Grouped /
 *       WC_Product_External
 *     - the standard set_*() setters
 *     - wc_get_product_id_by_sku()
 *     - wp_insert_term() / get_term_by()
 *
 *   These have been stable for years and do not depend on any internal
 *   importer plumbing.
 *
 * The CSV is the canonical Wonders & Oddities products file. We parse it
 * directly with PHP's str_getcsv() and create one product per row.
 *
 * The script is idempotent: products are looked up by SKU and skipped if
 * they already exist, so re-running the blueprint will not create
 * duplicates.
 *
 * Product images are sideloaded from the URLs in the CSV's "Images"
 * column (comma-separated; first becomes the featured image, the rest
 * become the gallery). Each fetch is wrapped in its own try/catch so a
 * single 404 or upstream timeout drops just that image and lets the
 * product save with whatever else loaded.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce is not active. Aborting W&O import.' );
}

// WO_CONTENT_BASE_URL is prepended to this script by bin/sync-playground.py
// when the script is inlined into each theme's blueprint.json. It points at
// the per-theme playground/ directory on raw.githubusercontent.com, e.g.
//     https://raw.githubusercontent.com/RegionallyFamous/fifty/main/obel/playground/
// so this importer pulls content/products.csv from the SAME theme that
// served the blueprint. Each theme owns its own catalogue and product
// imagery -- divergent copy and styling are first-class.
//
// The fallback to the upstream wonders-oddities repo is intentional: if a
// developer runs `wp eval-file wo-import.php` directly without the sync
// script having defined the constant (e.g. while debugging), we still get
// a working catalogue from the original source.
$wo_content_base = defined( 'WO_CONTENT_BASE_URL' )
	? WO_CONTENT_BASE_URL
	: 'https://raw.githubusercontent.com/RegionallyFamous/wonders-oddities/main/';
$wo_csv_url = rtrim( $wo_content_base, '/' ) . '/content/products.csv';
// Legacy fallback: the upstream wonders-oddities repo doesn't have a
// content/ subdirectory -- the CSV sits at the repo root. Detect that
// case so the script stays runnable against the original source.
if ( false !== strpos( $wo_content_base, 'wonders-oddities' ) ) {
	$wo_csv_url = rtrim( $wo_content_base, '/' ) . '/wonders-oddities-products.csv';
}

/**
 * Walk a "Parent > Child > Grandchild" category path, creating any missing
 * terms. Returns the leaf term_id, or 0 if the path could not be resolved.
 *
 * Caches resolved paths so repeated lookups across rows are cheap and so
 * that the same term is not inserted twice when WP's term cache is cold.
 */
function wo_resolve_category_path( string $path ): int {
	static $cache = array();

	$path = trim( $path );
	if ( '' === $path ) {
		return 0;
	}
	if ( isset( $cache[ $path ] ) ) {
		return $cache[ $path ];
	}

	$segments = array_filter( array_map( 'trim', explode( '>', $path ) ), 'strlen' );
	$parent   = 0;
	$term_id  = 0;

	foreach ( $segments as $name ) {
		$existing = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'name'       => $name,
				'parent'     => $parent,
				'hide_empty' => false,
				'number'     => 1,
			)
		);

		if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
			$term_id = (int) $existing[0]->term_id;
		} else {
			$inserted = wp_insert_term( $name, 'product_cat', array( 'parent' => $parent ) );
			if ( is_wp_error( $inserted ) ) {
				$term_id = 0;
				break;
			}
			$term_id = (int) $inserted['term_id'];
		}

		$parent = $term_id;
	}

	return $cache[ $path ] = $term_id;
}

/**
 * Resolve (or create) a flat product_tag term by name. Returns 0 on failure
 * so the caller can simply drop unresolvable tags rather than aborting the
 * whole row.
 */
function wo_resolve_tag( string $name ): int {
	static $cache = array();

	$name = trim( $name );
	if ( '' === $name ) {
		return 0;
	}
	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$existing = get_term_by( 'name', $name, 'product_tag' );
	if ( $existing ) {
		return $cache[ $name ] = (int) $existing->term_id;
	}

	$inserted = wp_insert_term( $name, 'product_tag' );
	if ( is_wp_error( $inserted ) ) {
		return $cache[ $name ] = 0;
	}
	return $cache[ $name ] = (int) $inserted['term_id'];
}

/**
 * Best-effort cast of WC's truthy-ish CSV cell values ("1", "yes", "no",
 * "true", "") to a real bool. Anything ambiguous becomes false so we never
 * accidentally publish or feature a product the source CSV did not intend.
 */
function wo_truthy( $value ): bool {
	$value = strtolower( trim( (string) $value ) );
	return in_array( $value, array( '1', 'yes', 'true' ), true );
}

/**
 * Walk the up-to-three "Attribute N name / value(s) / visible / global /
 * default" column groups present in WC's CSV export format and return a
 * structured list. Each entry is an associative array with `name` (the
 * human-readable label), `values` (array of options, split on `|`),
 * `visible` (bool), `global` (bool — whether WC should treat this as a
 * global pa_<slug> taxonomy; we ignore the flag for import simplicity and
 * always create local attributes), and `default` (the default option for
 * variation pickers, '' when the source row left it blank). Empty
 * attribute slots are skipped silently so a 1-attribute row does not
 * leak two empty entries.
 */
function wo_collect_attributes_from_row( array $row ): array {
	$out = array();
	for ( $n = 1; $n <= 3; $n++ ) {
		$name = trim( (string) ( $row[ "Attribute $n name" ] ?? '' ) );
		if ( '' === $name ) {
			continue;
		}
		$value_str = trim( (string) ( $row[ "Attribute $n value(s)" ] ?? '' ) );
		if ( '' === $value_str ) {
			continue;
		}
		$values = array_values(
			array_filter(
				array_map( 'trim', explode( '|', $value_str ) ),
				'strlen'
			)
		);
		if ( empty( $values ) ) {
			continue;
		}
		$out[] = array(
			'name'    => $name,
			'values'  => $values,
			'visible' => wo_truthy( $row[ "Attribute $n visible" ] ?? '1' ),
			'default' => trim( (string) ( $row[ "Attribute $n default" ] ?? '' ) ),
		);
	}
	return $out;
}

/**
 * Sideload a single image URL into the media library and return the new
 * attachment ID, or 0 on failure.
 *
 * Caches by URL so the same image referenced by multiple products is only
 * downloaded once per import run. Looks for an existing attachment with a
 * matching _wo_source_url meta first, so re-running the blueprint
 * (idempotent by SKU at the product level) also avoids re-downloading
 * images that already landed in a previous run.
 *
 * media_sideload_image() lives in wp-admin/includes/media.php and pulls in
 * file.php and image.php transitively; we require all three explicitly so
 * the function exists in the wp-cli context where wp-admin isn't loaded by
 * default.
 */
function wo_sideload_image( string $url, int $parent_post_id ): int {
	static $cache = array();

	$url = trim( $url );
	if ( '' === $url ) {
		return 0;
	}
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}

	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'meta_key'       => '_wo_source_url',
			'meta_value'     => $url,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	if ( ! empty( $existing ) ) {
		return $cache[ $url ] = (int) $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$id = media_sideload_image( $url, $parent_post_id, null, 'id' );
	if ( is_wp_error( $id ) || ! $id ) {
		return $cache[ $url ] = 0;
	}

	update_post_meta( (int) $id, '_wo_source_url', $url );
	return $cache[ $url ] = (int) $id;
}

$response = wp_remote_get(
	$wo_csv_url,
	array( 'timeout' => 60 )
);

if ( is_wp_error( $response ) ) {
	WP_CLI::error( 'Failed to fetch W&O CSV: ' . $response->get_error_message() );
}

$body = wp_remote_retrieve_body( $response );
if ( '' === $body ) {
	WP_CLI::error( 'W&O CSV body was empty (HTTP ' . wp_remote_retrieve_response_code( $response ) . ').' );
}

// Normalize line endings then split into rows. We do this manually rather
// than writing the body to a temp file because WC's CSV reader insists the
// file be at a path with a .csv extension and a matching wp_check_filetype
// MIME — both of which are easy to get wrong inside Playground's WASM PHP.
$lines = preg_split( '/\r\n|\r|\n/', trim( $body ) );
if ( count( $lines ) < 2 ) {
	WP_CLI::error( 'W&O CSV looked malformed: fewer than 2 lines after trim.' );
}

$headers = str_getcsv( array_shift( $lines ) );
$num     = count( $headers );

// Two-pass parse:
//
//   Pass 1 reads every CSV row into memory, resolving the row dictionary
//   once so we can reorder execution without re-parsing. We then split
//   into "parents" (simple / variable / grouped / external) and
//   "variations" (rows whose Type is `variation` and whose Parent points
//   at another row via `id:<row-id>`).
//
//   Pass 2a creates parents in CSV order and remembers the
//   row-ID -> WC product-ID map so pass 2b can resolve `Parent: id:1013`
//   into a real WP post ID.
//
//   Pass 2b creates each variation as a real WC_Product_Variation under
//   its parent. WITHOUT this split the pre-fix importer fell into the
//   default `WC_Product_Simple` branch for every variation row,
//   silently shipping ~20 orphan simple products with names like "Left
//   Sock of Destiny – Small / Moonlit Grey", no Categories cell, and
//   the resulting "Uncategorized" fallback term — visible as
//   `/product-category/uncategorized/` filling up with variation-named
//   junk and the shop archive padding to 55 results instead of 32.
//   A single-pass fix would be simpler but does not hold across rows
//   where a variation references a parent declared LATER in the CSV.
$rows = array();
foreach ( $lines as $line ) {
	if ( '' === trim( $line ) ) {
		continue;
	}
	$cells = str_getcsv( $line );
	if ( count( $cells ) < $num ) {
		$cells = array_pad( $cells, $num, '' );
	} elseif ( count( $cells ) > $num ) {
		$cells = array_slice( $cells, 0, $num );
	}
	$rows[] = array_combine( $headers, $cells );
}

$row_id_to_product_id = array();   // CSV ID column -> WC post ID
$variation_parent_ids = array();   // WC parent IDs that received variations
$created = 0;
$skipped = 0;
$failed  = 0;

// -------------------------------------------------------------------
// Pass 2a: parent products (simple / variable / grouped / external).
// -------------------------------------------------------------------
foreach ( $rows as $row ) {
	$type = strtolower( trim( (string) ( $row['Type'] ?? 'simple' ) ) );
	if ( 'variation' === $type ) {
		continue; // handled in pass 2b
	}

	$sku = trim( (string) ( $row['SKU'] ?? '' ) );
	if ( '' !== $sku ) {
		$existing_pid = wc_get_product_id_by_sku( $sku );
		if ( $existing_pid ) {
			++$skipped;
			$row_csv_id = (int) ( $row['ID'] ?? 0 );
			if ( $row_csv_id ) {
				$row_id_to_product_id[ $row_csv_id ] = (int) $existing_pid;
			}
			continue;
		}
	}

	switch ( $type ) {
		case 'variable':
			$product = new WC_Product_Variable();
			break;
		case 'grouped':
			$product = new WC_Product_Grouped();
			break;
		case 'external':
			$product = new WC_Product_External();
			break;
		default:
			$product = new WC_Product_Simple();
			break;
	}

	// External and grouped products do not support stock management or
	// physical dimensions in WC; calling the setters throws
	// WC_Data_Exception. Gate by type instead of guarding every setter.
	$supports_stock = $product->is_type( 'simple' ) || $product->is_type( 'variable' );
	$supports_dims  = ! $product->is_type( 'external' ) && ! $product->is_type( 'grouped' );

	// Set everything inside a single try/catch so an unexpected
	// rejection from any one setter (new validation rule, schema
	// change) only loses this row instead of the whole import.
	try {
		$product->set_name( (string) ( $row['Name'] ?? '' ) );
		$product->set_status( wo_truthy( $row['Published'] ?? '1' ) ? 'publish' : 'draft' );
		if ( '' !== $sku ) {
			$product->set_sku( $sku );
		}
		$product->set_description( (string) ( $row['Description'] ?? '' ) );
		$product->set_short_description( (string) ( $row['Short description'] ?? '' ) );
		$product->set_featured( wo_truthy( $row['Is featured?'] ?? '0' ) );

		$visibility = trim( (string) ( $row['Visibility in catalog'] ?? 'visible' ) );
		if ( in_array( $visibility, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
			$product->set_catalog_visibility( $visibility );
		}

		// Grouped products derive their price from children; setting one
		// directly is rejected. Skip pricing on grouped, allow it on
		// simple/variable/external.
		if ( ! $product->is_type( 'grouped' ) ) {
			$reg = trim( (string) ( $row['Regular price'] ?? '' ) );
			if ( '' !== $reg ) {
				$product->set_regular_price( $reg );
			}
			$sale = trim( (string) ( $row['Sale price'] ?? '' ) );
			if ( '' !== $sale ) {
				$product->set_sale_price( $sale );
			}
		}

		$tax_status = trim( (string) ( $row['Tax status'] ?? '' ) );
		if ( in_array( $tax_status, array( 'taxable', 'shipping', 'none' ), true ) ) {
			$product->set_tax_status( $tax_status );
		}

		if ( $supports_stock ) {
			if ( wo_truthy( $row['In stock?'] ?? '1' ) ) {
				$product->set_stock_status( 'instock' );
				$stock = trim( (string) ( $row['Stock'] ?? '' ) );
				if ( '' !== $stock && is_numeric( $stock ) ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( (int) $stock );
				}
			} else {
				$product->set_stock_status( 'outofstock' );
			}
		}

		if ( $supports_dims ) {
			foreach ( array( 'Weight (kg)' => 'set_weight', 'Length (cm)' => 'set_length', 'Width (cm)' => 'set_width', 'Height (cm)' => 'set_height' ) as $key => $setter ) {
				$value = trim( (string) ( $row[ $key ] ?? '' ) );
				if ( '' !== $value && is_numeric( $value ) ) {
					$product->{$setter}( $value );
				}
			}
		}

		// External-only fields. Both have safe defaults if missing.
		if ( $product->is_type( 'external' ) ) {
			$ext_url = trim( (string) ( $row['External URL'] ?? '' ) );
			if ( '' !== $ext_url ) {
				$product->set_product_url( $ext_url );
			}
			$button = trim( (string) ( $row['Button text'] ?? '' ) );
			if ( '' !== $button ) {
				$product->set_button_text( $button );
			}
		}
		$cat_ids = array();
		$cats    = trim( (string) ( $row['Categories'] ?? '' ) );
		if ( '' !== $cats ) {
			foreach ( explode( ',', $cats ) as $cat_path ) {
				$tid = wo_resolve_category_path( $cat_path );
				if ( $tid ) {
					$cat_ids[] = $tid;
				}
			}
		}
		if ( ! empty( $cat_ids ) ) {
			$product->set_category_ids( array_values( array_unique( $cat_ids ) ) );
		}

		$tag_ids = array();
		$tags    = trim( (string) ( $row['Tags'] ?? '' ) );
		if ( '' !== $tags ) {
			foreach ( explode( ',', $tags ) as $tag_name ) {
				$tid = wo_resolve_tag( $tag_name );
				if ( $tid ) {
					$tag_ids[] = $tid;
				}
			}
		}
		if ( ! empty( $tag_ids ) ) {
			$product->set_tag_ids( array_values( array_unique( $tag_ids ) ) );
		}

		// Variable-product attributes. We mark an attribute as "for
		// variations" iff the parent row supplied a default value for
		// it (the WC export convention) — `Brand` etc. on the same
		// row are informational only and stay non-variation.
		// All attributes are imported as LOCAL (per-product) rather
		// than global (`pa_<slug>`) taxonomies, mirroring what
		// wo-configure.php does for Bottled Morning / Pocket Thunder.
		// Local attributes Just Work for variation pickers and avoid
		// having to wc_create_attribute() + flush rewrite rules in a
		// long-lived PHP-FPM context.
		if ( $product->is_type( 'variable' ) ) {
			$attr_specs = wo_collect_attributes_from_row( $row );
			$wc_attrs   = array();
			$defaults   = array();
			foreach ( $attr_specs as $idx => $spec ) {
				$a = new WC_Product_Attribute();
				$a->set_name( $spec['name'] );
				$a->set_options( $spec['values'] );
				$a->set_position( $idx );
				$a->set_visible( $spec['visible'] );
				$a->set_variation( '' !== $spec['default'] );
				$wc_attrs[] = $a;
				if ( '' !== $spec['default'] ) {
					$defaults[ 'attribute_' . sanitize_title( $spec['name'] ) ] = $spec['default'];
				}
			}
			if ( ! empty( $wc_attrs ) ) {
				$product->set_attributes( $wc_attrs );
			}
			if ( ! empty( $defaults ) ) {
				$product->set_default_attributes( $defaults );
			}
		}

		$product_id = $product->save();

		// Remember row -> product mapping so pass 2b can resolve
		// `Parent: id:1013` references into a real WP post ID.
		$row_csv_id = (int) ( $row['ID'] ?? 0 );
		if ( $row_csv_id && $product_id ) {
			$row_id_to_product_id[ $row_csv_id ] = (int) $product_id;
		}

		// Images are attached after the initial save so each attachment
		// can record its parent post ID. We re-fetch the product object
		// before the second save so the image-id setters operate on a
		// fresh CRUD instance with no stale cached data.
		$image_urls = array_filter(
			array_map( 'trim', explode( ',', (string) ( $row['Images'] ?? '' ) ) ),
			'strlen'
		);
		if ( ! empty( $image_urls ) && $product_id ) {
			$attachment_ids = array();
			foreach ( $image_urls as $img_url ) {
				$att_id = wo_sideload_image( $img_url, $product_id );
				if ( $att_id ) {
					$attachment_ids[] = $att_id;
				}
			}
			if ( ! empty( $attachment_ids ) ) {
				$product = wc_get_product( $product_id );
				$product->set_image_id( (int) array_shift( $attachment_ids ) );
				if ( ! empty( $attachment_ids ) ) {
					$product->set_gallery_image_ids( array_values( array_unique( $attachment_ids ) ) );
				}
				$product->save();
			}
		}

		++$created;
	} catch ( Exception $e ) {
		++$failed;
		WP_CLI::warning( sprintf( 'Skipping "%s" (SKU %s, type %s): %s', $row['Name'] ?? '?', $sku, $type, $e->getMessage() ) );
	}
}

// -------------------------------------------------------------------
// Pass 2b: variations.
// -------------------------------------------------------------------
// Each variation row references its parent variable product via the
// `Parent` column in the form `id:<csv-row-id>`. We resolve that to a
// real WC post ID through the row-id map populated in pass 2a, then
// build a WC_Product_Variation with the row's per-variation attribute
// pairs (`attribute_<sanitized name>` => `<chosen value>`), price,
// stock, and SKU. After every variation for a given parent has been
// inserted we call WC_Product_Variable::sync($parent_id) so WC
// recomputes the parent's price range, in-stock-children flag, and
// visible-children index — without it the PDP picker shows the right
// options but a broken add-to-cart state.
//
// Variations are post_type=product_variation, NOT post_type=product,
// so registering them properly (rather than as orphan simples)
// removes them from /product-category/<term>/ archives entirely. That
// is what fixes the "23 entries showing in /product-category/uncategorized/"
// symptom and brings the shop archive count back from 55 down to ~32.
$variations_created = 0;
$variations_skipped = 0;
$variations_failed  = 0;
foreach ( $rows as $row ) {
	$type = strtolower( trim( (string) ( $row['Type'] ?? '' ) ) );
	if ( 'variation' !== $type ) {
		continue;
	}

	$sku = trim( (string) ( $row['SKU'] ?? '' ) );
	if ( '' !== $sku && wc_get_product_id_by_sku( $sku ) ) {
		++$variations_skipped;
		continue;
	}

	$parent_ref = trim( (string) ( $row['Parent'] ?? '' ) );
	if ( ! preg_match( '/^id:(\d+)$/', $parent_ref, $m ) ) {
		++$variations_failed;
		WP_CLI::warning( sprintf( 'Variation %s has unparseable Parent %s; skipping.', $sku, $parent_ref ) );
		continue;
	}
	$parent_csv_id     = (int) $m[1];
	$parent_product_id = $row_id_to_product_id[ $parent_csv_id ] ?? 0;
	if ( ! $parent_product_id ) {
		++$variations_failed;
		WP_CLI::warning( sprintf( 'Variation %s could not resolve parent CSV id %d; skipping.', $sku, $parent_csv_id ) );
		continue;
	}

	try {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent_product_id );

		$attr_specs = wo_collect_attributes_from_row( $row );
		$attr_map   = array();
		foreach ( $attr_specs as $spec ) {
			$attr_map[ 'attribute_' . sanitize_title( $spec['name'] ) ] = $spec['values'][0];
		}
		if ( ! empty( $attr_map ) ) {
			$variation->set_attributes( $attr_map );
		}

		if ( '' !== $sku ) {
			$variation->set_sku( $sku );
		}
		$variation->set_status( wo_truthy( $row['Published'] ?? '1' ) ? 'publish' : 'draft' );

		$reg = trim( (string) ( $row['Regular price'] ?? '' ) );
		if ( '' !== $reg ) {
			$variation->set_regular_price( $reg );
		}
		$sale = trim( (string) ( $row['Sale price'] ?? '' ) );
		if ( '' !== $sale ) {
			$variation->set_sale_price( $sale );
		}

		if ( wo_truthy( $row['In stock?'] ?? '1' ) ) {
			$variation->set_stock_status( 'instock' );
			$stock = trim( (string) ( $row['Stock'] ?? '' ) );
			if ( '' !== $stock && is_numeric( $stock ) ) {
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( (int) $stock );
			}
		} else {
			$variation->set_stock_status( 'outofstock' );
		}

		$variation->save();
		$variation_parent_ids[ $parent_product_id ] = true;
		++$variations_created;
	} catch ( Exception $e ) {
		++$variations_failed;
		WP_CLI::warning( sprintf( 'Skipping variation "%s" (SKU %s): %s', $row['Name'] ?? '?', $sku, $e->getMessage() ) );
	}
}

// Resync each variable parent that received variations so the parent's
// derived data (price range, stock state, default selected variation
// resolved against in-stock children) reflects what we just inserted.
foreach ( array_keys( $variation_parent_ids ) as $pid ) {
	WC_Product_Variable::sync( (int) $pid );
}

WP_CLI::success(
	sprintf(
		'W&O import done. Created=%d Skipped=%d Failed=%d  | Variations: created=%d skipped=%d failed=%d',
		$created,
		$skipped,
		$failed,
		$variations_created,
		$variations_skipped,
		$variations_failed
	)
);
