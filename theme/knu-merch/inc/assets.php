<?php
/**
 * Front-end assets and window.KNU (contract C3).
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version string for a theme file (filemtime when it exists).
 *
 * @param string $rel Path relative to the theme root.
 * @return string
 */
function knu_asset_ver( $rel ) {
	$path = get_theme_file_path( $rel );
	return is_readable( $path ) ? KNU_VERSION . '.' . filemtime( $path ) : KNU_VERSION;
}

/**
 * Data passed to JS as window.KNU.
 *
 * @return array
 */
function knu_js_data() {
	$products = array();
	foreach ( knu_get_products() as $product ) {
		$colors = array();
		foreach ( $product['colors'] as $color ) {
			$colors[ $color['key'] ] = array(
				'name'   => $color['name'],
				'hex'    => $color['hex'],
				'images' => $color['images'],
			);
		}
		$products[ $product['slug'] ] = array(
			'title'          => $product['title'],
			'type'           => $product['type'],
			'price'          => $product['price'],
			'price_separate' => $product['price_separate'],
			'sizes'          => $product['sizes'],
			'colors'         => (object) $colors,
		);
	}

	return array(
		'rest'        => esc_url_raw( rest_url( 'knu/v1/orders' ) ),
		// JS builds `${paidUrl}${number}/paid`.
		'paidUrl'     => esc_url_raw( rest_url( 'knu/v1/orders/' ) ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'lang'        => knu_lang(),
		'products'    => (object) $products,
		'delivery'    => knu_delivery_fees(),
		'contact'     => array(
			'whatsapp' => knu_setting( 'whatsapp' ),
			'telegram' => knu_setting( 'telegram' ),
		),
		'qrRecipient' => knu_setting( 'qr_recipient' ),
		'legal'       => array(
			'offer'            => knu_legal_url( 'offer' ),
			'privacy'          => knu_legal_url( 'privacy' ),
			'returns'          => knu_legal_url( 'returns' ),
			'delivery_payment' => knu_legal_url( 'delivery-payment' ),
		),
		'i18n'        => (object) knu_js_strings(),
	);
}

/**
 * Enqueue styles and scripts.
 */
function knu_enqueue_assets() {
	// Google Fonts: css2 serves Cyrillic subsets via unicode-range. ver = null keeps the URL intact.
	wp_enqueue_style(
		'knu-fonts',
		'https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500;600&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	);
	wp_enqueue_style( 'knu-main', get_theme_file_uri( 'assets/css/main.css' ), array( 'knu-fonts' ), knu_asset_ver( 'assets/css/main.css' ) );

	wp_enqueue_script( 'knu-cart', get_theme_file_uri( 'assets/js/cart.js' ), array(), knu_asset_ver( 'assets/js/cart.js' ), true );
	wp_enqueue_script( 'knu-main', get_theme_file_uri( 'assets/js/main.js' ), array( 'knu-cart' ), knu_asset_ver( 'assets/js/main.js' ), true );

	$json = wp_json_encode( knu_js_data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
	wp_add_inline_script( 'knu-cart', 'window.KNU = ' . $json . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'knu_enqueue_assets' );

/**
 * Preconnect to Google Fonts.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Relation.
 * @return array
 */
function knu_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'knu_resource_hints', 10, 2 );
