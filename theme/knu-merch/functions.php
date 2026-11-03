<?php
/**
 * KNU Merch 1925 theme bootstrap.
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

define( 'KNU_VERSION', '1.0.0' );
define( 'KNU_DIR', get_template_directory() );

require_once KNU_DIR . '/inc/i18n.php';
require_once KNU_DIR . '/inc/settings.php';
require_once KNU_DIR . '/inc/cpt-products.php';
require_once KNU_DIR . '/inc/cpt-orders.php';
require_once KNU_DIR . '/inc/rest-orders.php';
require_once KNU_DIR . '/inc/seed.php';
require_once KNU_DIR . '/inc/assets.php';

/**
 * Theme supports.
 */
function knu_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'script', 'style', 'search-form', 'gallery', 'caption' ) );
}
add_action( 'after_setup_theme', 'knu_setup' );

/**
 * Remove emoji scripts and styles.
 */
function knu_disable_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'knu_disable_emoji' );

/**
 * Keep the storefront clean: no admin bar on the front end.
 * Logged-in users reach wp-admin through the footer link.
 */
add_filter( 'show_admin_bar', '__return_false' );

/**
 * Atomic DB lock / claim stored as a non-autoloaded option row.
 *
 * Uses INSERT IGNORE on the unique option_name index, so exactly one
 * concurrent request wins on both MySQL and SQLite (WordPress Playground).
 * A lock older than $ttl seconds is considered stale and taken over.
 *
 * @param string $name Option name used as the lock key.
 * @param int    $ttl  Seconds after which an existing lock is stale; 0 = never.
 * @return bool True when this request now holds the lock.
 */
function knu_db_lock( $name, $ttl = 0 ) {
	global $wpdb;
	$now = time();
	$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( 1 === (int) $wpdb->rows_affected ) {
		return true;
	}
	if ( $ttl > 0 ) {
		$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( null === $held ) {
			// Released between our INSERT and SELECT: try once more.
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return 1 === (int) $wpdb->rows_affected;
		}
		if ( $now - (int) $held > $ttl ) {
			// Compare-and-swap so only one request takes over a stale lock.
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) $now, $name, (string) $held ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return 1 === (int) $wpdb->rows_affected;
		}
	}
	return false;
}

/**
 * Release a lock taken with knu_db_lock().
 *
 * @param string $name Option name.
 */
function knu_db_unlock( $name ) {
	global $wpdb;
	$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_cache_delete( $name, 'options' );
}

/**
 * The theme renders no block markup (legal pages are plain HTML from
 * inc/data-legal.php), so drop core block / global styles on the front end.
 */
function knu_dequeue_block_styles() {
	foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'knu_dequeue_block_styles', 100 );
add_action( 'wp_footer', 'knu_dequeue_block_styles', 0 );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );

/**
 * Favicon from the theme logo when no Site Icon is set in the Customizer.
 */
function knu_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	printf( '<link rel="icon" type="image/png" sizes="96x96" href="%s">' . PHP_EOL, esc_url( get_theme_file_uri( 'assets/img/logo-96.png' ) ) );
	printf( '<link rel="apple-touch-icon" sizes="192x192" href="%s">' . PHP_EOL, esc_url( get_theme_file_uri( 'assets/img/logo-192.png' ) ) );
}
add_action( 'wp_head', 'knu_favicon', 5 );

/**
 * Privacy policy retention: order data is kept 1 year, then deleted.
 * A daily WP-Cron event permanently deletes knu_order posts older than 365 days.
 */
function knu_schedule_order_purge() {
	if ( ! wp_next_scheduled( 'knu_purge_old_orders' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'knu_purge_old_orders' );
	}
}
add_action( 'init', 'knu_schedule_order_purge' );

/**
 * Unschedule the purge when the theme is switched off.
 */
function knu_unschedule_order_purge() {
	wp_clear_scheduled_hook( 'knu_purge_old_orders' );
}
add_action( 'switch_theme', 'knu_unschedule_order_purge' );

/**
 * Permanently delete orders older than the retention period.
 *
 * @return int Number of deleted orders.
 */
function knu_purge_old_orders() {
	$days    = (int) apply_filters( 'knu_order_retention_days', 365 );
	$deleted = 0;
	if ( $days < 1 ) {
		return 0;
	}
	for ( $batch = 0; $batch < 20; $batch++ ) {
		$ids = get_posts(
			array(
				'post_type'        => 'knu_order',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'posts_per_page'   => 100,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'orderby'          => 'date',
				'order'            => 'ASC',
				'date_query'       => array(
					array(
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
					),
				),
			)
		);
		if ( ! $ids ) {
			break;
		}
		foreach ( $ids as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) {
				++$deleted;
			}
		}
	}
	return $deleted;
}
add_action( 'knu_purge_old_orders', 'knu_purge_old_orders' );

/**
 * SEO / social meta: description, Open Graph, Twitter card (front end only).
 */
function knu_social_meta() {
	if ( is_admin() || is_feed() || is_404() ) {
		return;
	}
	$meta  = knu_meta_strings();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( '/' );
	$url   = add_query_arg( 'lang', knu_lang(), $url );
	$image = get_theme_file_uri( 'assets/img/hero.webp' );

	$tags = array(
		array( 'name', 'description', $meta['description'] ),
		array( 'property', 'og:type', 'website' ),
		array( 'property', 'og:site_name', $meta['site'] ),
		array( 'property', 'og:title', $title ),
		array( 'property', 'og:description', $meta['description'] ),
		array( 'property', 'og:url', $url ),
		array( 'property', 'og:image', $image ),
		array( 'property', 'og:image:width', '1600' ),
		array( 'property', 'og:image:height', '1200' ),
		array( 'property', 'og:locale', $meta['locale'] ),
		array( 'property', 'og:locale:alternate', 'ky_KG' === $meta['locale'] ? 'ru_RU' : 'ky_KG' ),
		array( 'name', 'twitter:card', 'summary_large_image' ),
		array( 'name', 'twitter:title', $title ),
		array( 'name', 'twitter:description', $meta['description'] ),
		array( 'name', 'twitter:image', $image ),
	);
	foreach ( $tags as $tag ) {
		printf( '<meta %1$s="%2$s" content="%3$s">' . PHP_EOL, esc_attr( $tag[0] ), esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
	}
}
add_action( 'wp_head', 'knu_social_meta', 4 );
