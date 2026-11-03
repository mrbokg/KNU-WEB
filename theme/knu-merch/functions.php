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
