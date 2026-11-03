<?php
/**
 * Seed: products, front page, site title, permalinks, legal pages.
 *
 * Runs on after_switch_theme and on init (Playground may not fire the
 * former reliably). Guards:
 * - knu_seeded = '1' is set only when every product was created or found;
 * - knu_seed_lock (atomic option row, 2 min stale timeout) stops two first
 *   requests from seeding in parallel and creating duplicate products;
 * - knu_seed_version: when KNU_SEED_VERSION is higher, existing products get
 *   colors/images meta re-synced from inc/data-products.php by slug
 *   (titles, prices and other admin edits are left alone);
 * - knu_legal_version: when KNU_LEGAL_VERSION is higher, legal pages are
 *   re-synced from inc/data-legal.php unless an admin has edited them.
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product seed version. v2: sets only grey/navy (no "black"), new image map.
 */
const KNU_SEED_VERSION = 2;

/**
 * Legal pages version. v2: seller details as {{seller_*}} tokens.
 */
const KNU_LEGAL_VERSION = 2;

/**
 * Stale timeout for seed locks, seconds.
 */
const KNU_SEED_LOCK_TTL = 120;

/**
 * Option value straight from the DB (bypasses the per-request option cache,
 * which may be stale after waiting for another request's lock).
 *
 * @param string $name Option name.
 * @return string|null
 */
function knu_option_raw( $name ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Include a theme data file that returns an array.
 *
 * @param string $rel Path relative to the theme root.
 * @return array|null Null when missing, broken or not an array.
 */
function knu_load_seed_file( $rel ) {
	$file = get_theme_file_path( $rel );
	if ( ! is_readable( $file ) ) {
		return null;
	}
	try {
		$data = include $file;
	} catch ( \Throwable $e ) {
		// File may be half-written by a deploy; try again on a later request.
		return null;
	}
	return ( is_array( $data ) && $data ) ? $data : null;
}

/**
 * Run the product seed or its version migration when needed.
 */
function knu_maybe_seed() {
	$seeded  = '1' === (string) get_option( 'knu_seeded' );
	$version = (int) get_option( 'knu_seed_version', 0 );
	if ( $seeded && $version >= KNU_SEED_VERSION ) {
		return;
	}
	if ( ! knu_db_lock( 'knu_seed_lock', KNU_SEED_LOCK_TTL ) ) {
		return; // Another request is seeding right now.
	}

	try {
		// Re-read under the lock: another request may have just finished.
		$seeded  = '1' === (string) knu_option_raw( 'knu_seeded' );
		$version = (int) knu_option_raw( 'knu_seed_version' );
		if ( $seeded && $version >= KNU_SEED_VERSION ) {
			wp_cache_delete( 'alloptions', 'options' );
			return;
		}

		// CPTs must exist before inserting posts.
		if ( ! post_type_exists( 'knu_product' ) ) {
			knu_register_product_cpt();
		}
		if ( ! post_type_exists( 'knu_order' ) ) {
			knu_register_order_cpt();
		}

		if ( ! $seeded ) {
			$ok = knu_seed_products();
			knu_seed_front_page();
			knu_seed_options();
			if ( $ok ) {
				update_option( 'knu_seeded', '1' );
				update_option( 'knu_seed_version', KNU_SEED_VERSION );
			}
		} elseif ( knu_migrate_products() ) {
			update_option( 'knu_seed_version', KNU_SEED_VERSION );
		}
	} finally {
		knu_db_unlock( 'knu_seed_lock' );
	}
}
add_action( 'after_switch_theme', 'knu_maybe_seed' );
add_action( 'init', 'knu_maybe_seed', 20 );

/**
 * Product post ID by slug (any status except trash).
 *
 * @param string $slug Slug.
 * @return int 0 when missing.
 */
function knu_find_product_id( $slug ) {
	$ids = get_posts(
		array(
			'post_type'        => 'knu_product',
			'name'             => $slug,
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Create products from inc/data-products.php (existing slugs are kept).
 *
 * @return bool True when every product exists afterwards.
 */
function knu_seed_products() {
	$products = knu_load_seed_file( 'inc/data-products.php' );
	if ( null === $products ) {
		return false;
	}

	$ok = true;
	foreach ( $products as $product ) {
		if ( ! is_array( $product ) || empty( $product['slug'] ) ) {
			continue;
		}
		$slug = sanitize_title( $product['slug'] );
		if ( knu_find_product_id( $slug ) ) {
			continue;
		}

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'knu_product',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => (string) ( $product['title']['ru'] ?? $slug ),
					'post_content' => (string) ( $product['desc']['ru'] ?? '' ),
					'menu_order'   => (int) ( $product['order'] ?? 0 ),
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$ok = false;
			continue;
		}

		$meta = array(
			'type'           => ( 'accessory' === ( $product['type'] ?? '' ) ) ? 'accessory' : 'set',
			'price'          => (int) ( $product['price'] ?? 0 ),
			'price_separate' => (int) ( $product['price_separate'] ?? 0 ),
			'title_kg'       => (string) ( $product['title']['kg'] ?? '' ),
			'desc_kg'        => (string) ( $product['desc']['kg'] ?? '' ),
			'badge_ru'       => (string) ( $product['badge']['ru'] ?? '' ),
			'badge_kg'       => (string) ( $product['badge']['kg'] ?? '' ),
			'composition_ru' => (string) ( $product['composition']['ru'] ?? '' ),
			'composition_kg' => (string) ( $product['composition']['kg'] ?? '' ),
			'sizes'          => knu_sanitize_sizes( $product['sizes'] ?? array() ),
			'colors'         => knu_sanitize_colors( $product['colors'] ?? array() ),
			'images'         => knu_sanitize_images( $product['images'] ?? array() ),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, '_knu_' . $key, wp_slash( $value ) );
		}
	}

	knu_flush_products_cache();
	return $ok;
}

/**
 * Seed version migration: re-sync colors and images meta of existing
 * products from inc/data-products.php by slug. Titles, prices, texts and
 * order stay as the admin left them; products deleted by the admin are not
 * recreated.
 *
 * @return bool False when the data file could not be read.
 */
function knu_migrate_products() {
	$products = knu_load_seed_file( 'inc/data-products.php' );
	if ( null === $products ) {
		return false;
	}
	foreach ( $products as $product ) {
		if ( ! is_array( $product ) || empty( $product['slug'] ) ) {
			continue;
		}
		$post_id = knu_find_product_id( sanitize_title( $product['slug'] ) );
		if ( ! $post_id ) {
			continue;
		}
		update_post_meta( $post_id, '_knu_colors', wp_slash( knu_sanitize_colors( $product['colors'] ?? array() ) ) );
		update_post_meta( $post_id, '_knu_images', wp_slash( knu_sanitize_images( $product['images'] ?? array() ) ) );
	}
	knu_flush_products_cache();
	return true;
}

/**
 * Create the «Главная» page and make it the static front page.
 */
function knu_seed_front_page() {
	$page_id = (int) get_option( 'page_on_front' );
	if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
		$found   = get_posts(
			array(
				'post_type'        => 'page',
				'name'             => 'glavnaya',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		$page_id = $found ? (int) $found[0] : 0;
	}
	if ( ! $page_id ) {
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Главная',
				'post_name'    => 'glavnaya',
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $page_id ) ) {
			return;
		}
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $page_id );
}

/**
 * Site title, tagline and pretty permalinks.
 */
function knu_seed_options() {
	update_option( 'blogname', 'КНУ Мерч · Коллекция 1925' );
	update_option( 'blogdescription', 'Мерч Кыргызского национального университета им. Ж. Баласагына к 100-летию' );

	global $wp_rewrite;
	update_option( 'permalink_structure', '/%postname%/' );
	if ( $wp_rewrite instanceof WP_Rewrite ) {
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
	}
	flush_rewrite_rules( false );
}

/**
 * Seed / re-sync legal pages from inc/data-legal.php (contract C8).
 *
 * KG goes to post_title/post_content, RU to meta _knu_title_ru /
 * _knu_content_ru. After each write the md5 of the stored content is kept in
 * _knu_seed_hash / _knu_seed_hash_ru. On a KNU_LEGAL_VERSION bump a page is
 * updated only when its current content still matches that hash, i.e. the
 * admin has not edited it. Pages seeded before hashes existed count as
 * unedited when post_modified equals post_date.
 */
function knu_maybe_seed_legal() {
	$seeded  = '1' === (string) get_option( 'knu_legal_seeded' );
	$version = (int) get_option( 'knu_legal_version', $seeded ? 1 : 0 );
	if ( $version >= KNU_LEGAL_VERSION ) {
		return;
	}
	$pages = knu_load_seed_file( 'inc/data-legal.php' );
	if ( null === $pages ) {
		return;
	}
	if ( ! knu_db_lock( 'knu_legal_lock', KNU_SEED_LOCK_TTL ) ) {
		return;
	}

	try {
		$raw_version = knu_option_raw( 'knu_legal_version' );
		if ( null !== $raw_version && (int) $raw_version >= KNU_LEGAL_VERSION ) {
			wp_cache_delete( 'alloptions', 'options' );
			return;
		}

		$ok = true;
		foreach ( $pages as $slug => $page ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug || ! is_array( $page ) ) {
				continue;
			}
			if ( ! knu_seed_legal_page( $slug, $page ) ) {
				$ok = false;
			}
		}

		if ( $ok ) {
			update_option( 'knu_legal_seeded', '1' );
			update_option( 'knu_legal_version', KNU_LEGAL_VERSION );
		}
	} finally {
		knu_db_unlock( 'knu_legal_lock' );
	}
}
add_action( 'after_switch_theme', 'knu_maybe_seed_legal' );
add_action( 'init', 'knu_maybe_seed_legal', 21 );

/**
 * Create or re-sync one legal page.
 *
 * @param string $slug Page slug.
 * @param array  $page Page data: title/content × kg/ru.
 * @return bool False when the page could not be created.
 */
function knu_seed_legal_page( $slug, $page ) {
	$title_kg   = (string) ( $page['title']['kg'] ?? '' );
	$title_ru   = (string) ( $page['title']['ru'] ?? '' );
	$content_kg = (string) ( $page['content']['kg'] ?? '' );
	$content_ru = (string) ( $page['content']['ru'] ?? '' );
	if ( '' === $content_kg ) {
		$content_kg = $content_ru;
	}

	$existing = get_page_by_path( $slug, OBJECT, 'page' );

	if ( ! $existing ) {
		$page_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => '' !== $title_kg ? $title_kg : $title_ru,
					'post_content' => $content_kg,
				)
			),
			true
		);
		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return false;
		}
		update_post_meta( $page_id, '_knu_title_ru', wp_slash( sanitize_text_field( $title_ru ) ) );
		update_post_meta( $page_id, '_knu_content_ru', wp_slash( wp_kses_post( $content_ru ) ) );
		knu_legal_store_hashes( $page_id );
		return true;
	}

	$page_id = (int) $existing->ID;
	$legacy  = ( $existing->post_modified_gmt === $existing->post_date_gmt );

	if ( '' === (string) get_post_meta( $page_id, '_knu_title_ru', true ) ) {
		update_post_meta( $page_id, '_knu_title_ru', wp_slash( sanitize_text_field( $title_ru ) ) );
	}

	// KG: post_content.
	$current = (string) get_post_field( 'post_content', $page_id, 'raw' );
	$hash    = (string) get_post_meta( $page_id, '_knu_seed_hash', true );
	$pristine = ( '' !== $hash ) ? hash_equals( $hash, md5( $current ) ) : $legacy;
	if ( $pristine ) {
		if ( $current !== $content_kg ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $page_id,
						'post_content' => $content_kg,
					)
				)
			);
		}
		update_post_meta( $page_id, '_knu_seed_hash', md5( (string) get_post_field( 'post_content', $page_id, 'raw' ) ) );
	}

	// RU: meta _knu_content_ru.
	$current_ru = (string) get_post_meta( $page_id, '_knu_content_ru', true );
	$hash_ru    = (string) get_post_meta( $page_id, '_knu_seed_hash_ru', true );
	$pristine   = ( '' === $current_ru ) || ( ( '' !== $hash_ru ) ? hash_equals( $hash_ru, md5( $current_ru ) ) : $legacy );
	if ( $pristine ) {
		update_post_meta( $page_id, '_knu_content_ru', wp_slash( wp_kses_post( $content_ru ) ) );
		update_post_meta( $page_id, '_knu_seed_hash_ru', md5( (string) get_post_meta( $page_id, '_knu_content_ru', true ) ) );
	}

	return true;
}

/**
 * Remember what was seeded, to detect admin edits later.
 *
 * @param int $page_id Page ID.
 */
function knu_legal_store_hashes( $page_id ) {
	update_post_meta( $page_id, '_knu_seed_hash', md5( (string) get_post_field( 'post_content', $page_id, 'raw' ) ) );
	update_post_meta( $page_id, '_knu_seed_hash_ru', md5( (string) get_post_meta( $page_id, '_knu_content_ru', true ) ) );
}
