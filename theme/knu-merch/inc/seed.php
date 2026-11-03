<?php
/**
 * One-time seed: products, front page, site title, permalinks.
 *
 * Runs on after_switch_theme and on init (Playground may not fire the
 * former reliably), guarded by option knu_seeded = '1'. Idempotent:
 * existing products (by slug) and the front page are not duplicated.
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run the seed if it has not run yet.
 */
function knu_maybe_seed() {
	if ( '1' === (string) get_option( 'knu_seeded' ) ) {
		return;
	}
	// CPTs must exist before inserting posts.
	if ( ! post_type_exists( 'knu_product' ) ) {
		knu_register_product_cpt();
	}
	if ( ! post_type_exists( 'knu_order' ) ) {
		knu_register_order_cpt();
	}

	knu_seed_products();
	knu_seed_front_page();
	knu_seed_options();

	update_option( 'knu_seeded', '1' );
}
add_action( 'after_switch_theme', 'knu_maybe_seed' );
add_action( 'init', 'knu_maybe_seed', 20 );

/**
 * Create products from inc/data-products.php.
 */
function knu_seed_products() {
	$file = get_theme_file_path( 'inc/data-products.php' );
	if ( ! is_readable( $file ) ) {
		return;
	}
	$products = include $file;
	if ( ! is_array( $products ) ) {
		return;
	}

	foreach ( $products as $product ) {
		if ( empty( $product['slug'] ) ) {
			continue;
		}
		$slug     = sanitize_title( $product['slug'] );
		$existing = get_posts(
			array(
				'post_type'        => 'knu_product',
				'name'             => $slug,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		if ( $existing ) {
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
}

/**
 * Create the «Главная» page and make it the static front page.
 */
function knu_seed_front_page() {
	$page_id = (int) get_option( 'page_on_front' );
	if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
		$found = get_posts(
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
 * Seed legal pages from inc/data-legal.php (contract C8).
 *
 * Separate guard (knu_legal_seeded) so it runs on a later request once the
 * data file exists. KG goes to post_title/post_content, RU to meta
 * _knu_title_ru / _knu_content_ru.
 */
function knu_maybe_seed_legal() {
	if ( '1' === (string) get_option( 'knu_legal_seeded' ) ) {
		return;
	}
	$file = get_theme_file_path( 'inc/data-legal.php' );
	if ( ! is_readable( $file ) ) {
		return;
	}
	try {
		$pages = include $file;
	} catch ( \Throwable $e ) {
		// File may be half-written by a deploy; try again on a later request.
		return;
	}
	if ( ! is_array( $pages ) || ! $pages ) {
		return;
	}

	foreach ( $pages as $slug => $page ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug || ! is_array( $page ) ) {
			continue;
		}
		$title_kg   = (string) ( $page['title']['kg'] ?? '' );
		$title_ru   = (string) ( $page['title']['ru'] ?? '' );
		$content_kg = (string) ( $page['content']['kg'] ?? '' );
		$content_ru = (string) ( $page['content']['ru'] ?? '' );

		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			$page_id = (int) $existing->ID;
		} else {
			$page_id = wp_insert_post(
				wp_slash(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_name'    => $slug,
						'post_title'   => '' !== $title_kg ? $title_kg : $title_ru,
						'post_content' => '' !== $content_kg ? $content_kg : $content_ru,
					)
				),
				true
			);
			if ( is_wp_error( $page_id ) || ! $page_id ) {
				continue;
			}
		}

		if ( '' === (string) get_post_meta( $page_id, '_knu_title_ru', true ) ) {
			update_post_meta( $page_id, '_knu_title_ru', wp_slash( sanitize_text_field( $title_ru ) ) );
		}
		if ( '' === (string) get_post_meta( $page_id, '_knu_content_ru', true ) ) {
			update_post_meta( $page_id, '_knu_content_ru', wp_slash( wp_kses_post( $content_ru ) ) );
		}
	}

	update_option( 'knu_legal_seeded', '1' );
}
add_action( 'after_switch_theme', 'knu_maybe_seed_legal' );
add_action( 'init', 'knu_maybe_seed_legal', 21 );
