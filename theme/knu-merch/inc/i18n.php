<?php
/**
 * Lightweight RU/KG localisation (no plugins).
 *
 * Language source: ?lang=, then cookie knu_lang, otherwise 'kg' (state
 * language, contract C8). Missing strings fall back to RU, then to the key.
 * Strings live in lang/{lang}.php as flat 'key' => 'string' arrays.
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supported languages.
 *
 * @return string[]
 */
function knu_languages() {
	return array( 'ru', 'kg' );
}

/**
 * Normalise a raw language value.
 *
 * @param mixed $value Raw value.
 * @return string Valid language code or '' when invalid.
 */
function knu_sanitize_lang( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = strtolower( sanitize_key( $value ) );
	return in_array( $value, knu_languages(), true ) ? $value : '';
}

/**
 * Current language (default 'kg' — state language, contract C8).
 *
 * @return string 'kg' | 'ru'
 */
function knu_lang() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language switch.
	if ( isset( $_GET['lang'] ) ) {
		$lang = knu_sanitize_lang( wp_unslash( $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $lang ) {
			return $lang;
		}
	}
	if ( isset( $_COOKIE['knu_lang'] ) ) {
		$lang = knu_sanitize_lang( wp_unslash( $_COOKIE['knu_lang'] ) );
		if ( $lang ) {
			return $lang;
		}
	}
	return 'kg';
}

/**
 * Load a language file (cached per request).
 *
 * @param string $lang Language code.
 * @return array<string,string>
 */
function knu_lang_strings( $lang ) {
	static $cache = array();

	$lang = knu_sanitize_lang( $lang );
	if ( ! $lang ) {
		return array();
	}
	if ( isset( $cache[ $lang ] ) ) {
		return $cache[ $lang ];
	}

	$strings = array();
	$file    = get_theme_file_path( 'lang/' . $lang . '.php' );
	if ( is_readable( $file ) ) {
		$loaded = include $file;
		if ( is_array( $loaded ) ) {
			$strings = $loaded;
		}
	}

	$cache[ $lang ] = $strings;
	return $strings;
}

/**
 * Translate a key: current language, then RU, then the key itself.
 *
 * @param string $key String key, e.g. 'hero.title'.
 * @return string
 */
function knu_t( $key ) {
	$key     = (string) $key;
	$strings = knu_lang_strings( knu_lang() );
	if ( isset( $strings[ $key ] ) && is_scalar( $strings[ $key ] ) ) {
		return (string) $strings[ $key ];
	}
	$ru = knu_lang_strings( 'ru' );
	if ( isset( $ru[ $key ] ) && is_scalar( $ru[ $key ] ) ) {
		return (string) $ru[ $key ];
	}
	return $key;
}

/**
 * Echo an escaped translated string.
 *
 * @param string $key String key.
 */
function knu_e( $key ) {
	echo esc_html( knu_t( $key ) );
}

/**
 * Current URL with ?lang= set. Returned HTML-escaped (safe for attributes).
 *
 * @param string $lang Target language.
 * @return string
 */
function knu_lang_url( $lang ) {
	$lang = knu_sanitize_lang( $lang );
	if ( ! $lang ) {
		$lang = 'kg';
	}
	// Root-relative URL of the current request (works for sub-directory installs too).
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! is_string( $uri ) || '' === $uri || '/' !== $uri[0] || 0 === strpos( $uri, '//' ) ) {
		$uri = '/';
	}
	$url = add_query_arg( 'lang', $lang, remove_query_arg( 'lang', $uri ) );

	return esc_url( $url );
}

/**
 * All JS strings (keys starting with "js.") for the current language, RU fallback.
 *
 * @return array<string,string>
 */
function knu_js_strings() {
	$out = array();
	foreach ( array( knu_lang_strings( 'ru' ), knu_lang_strings( knu_lang() ) ) as $strings ) {
		foreach ( $strings as $key => $value ) {
			if ( is_string( $key ) && 0 === strpos( $key, 'js.' ) && is_scalar( $value ) ) {
				$out[ $key ] = (string) $value;
			}
		}
	}
	return $out;
}

/**
 * Remember ?lang= in a cookie.
 */
function knu_remember_lang() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['lang'] ) ) {
		return;
	}
	$lang = knu_sanitize_lang( wp_unslash( $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $lang || headers_sent() ) {
		return;
	}
	$current = isset( $_COOKIE['knu_lang'] ) ? knu_sanitize_lang( wp_unslash( $_COOKIE['knu_lang'] ) ) : '';
	if ( $current === $lang ) {
		return;
	}
	setcookie( 'knu_lang', $lang, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
	$_COOKIE['knu_lang'] = $lang;
}
add_action( 'init', 'knu_remember_lang', 1 );

/**
 * <html lang="ru"> / <html lang="ky"> on the front end only;
 * wp-admin keeps the user's admin locale.
 *
 * @param string $output Attributes string.
 * @return string
 */
function knu_language_attributes( $output ) {
	if ( is_admin() ) {
		return $output;
	}
	$code = ( 'kg' === knu_lang() ) ? 'ky' : 'ru';
	$attr = 'lang="' . esc_attr( $code ) . '"';
	if ( preg_match( '/lang="[^"]*"/', $output ) ) {
		return preg_replace( '/lang="[^"]*"/', $attr, $output );
	}
	return trim( $attr . ' ' . $output );
}
add_filter( 'language_attributes', 'knu_language_attributes' );

/**
 * Localised page title: KG in post_title, RU in meta _knu_title_ru (contract C8).
 *
 * @param int $id Page ID.
 * @return string Raw title (escape on output).
 */
function knu_page_title( $id ) {
	$id = (int) $id;
	if ( 'ru' === knu_lang() ) {
		$ru = (string) get_post_meta( $id, '_knu_title_ru', true );
		if ( '' !== $ru ) {
			return $ru;
		}
	}
	return (string) get_post_field( 'post_title', $id, 'raw' );
}

/**
 * Localised page content: KG in post_content, RU in meta _knu_content_ru.
 *
 * @param int $id Page ID.
 * @return string Safe HTML (wpautop + wp_kses_post).
 */
function knu_page_content( $id ) {
	$id      = (int) $id;
	$content = '';
	if ( 'ru' === knu_lang() ) {
		$content = (string) get_post_meta( $id, '_knu_content_ru', true );
	}
	if ( '' === $content ) {
		$content = (string) get_post_field( 'post_content', $id, 'raw' );
	}
	return wp_kses_post( wpautop( knu_replace_seller_tokens( $content ) ) );
}

/**
 * Replace {{seller_name}}, {{seller_inn}}, {{seller_address}}, {{seller_phone}}
 * and {{seller_email}} with the escaped values from theme settings.
 *
 * @param string $content Content.
 * @return string
 */
function knu_replace_seller_tokens( $content ) {
	if ( false === strpos( (string) $content, '{{seller_' ) ) {
		return (string) $content;
	}
	$map = array();
	foreach ( array( 'seller_name', 'seller_inn', 'seller_address', 'seller_phone', 'seller_email' ) as $key ) {
		$map[ '{{' . $key . '}}' ] = esc_html( knu_setting( $key ) );
	}
	return strtr( (string) $content, $map );
}

/**
 * URL of a legal page by slug, with the current ?lang=.
 *
 * @param string $slug offer | privacy | returns | delivery-payment.
 * @return string Raw URL (escape on output); home URL when the page is missing.
 */
function knu_legal_url( $slug ) {
	$page = get_page_by_path( sanitize_title( (string) $slug ), OBJECT, 'page' );
	$url  = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : home_url( '/' );
	return esc_url_raw( add_query_arg( 'lang', knu_lang(), $url ) );
}

/**
 * Document-level strings (titles, meta description) per language.
 * Kept here because they are used in <head>, not in templates.
 *
 * @param string|null $lang Language; current one when null.
 * @return array{home_title:string,site:string,description:string,locale:string}
 */
function knu_meta_strings( $lang = null ) {
	$lang    = knu_sanitize_lang( null === $lang ? knu_lang() : $lang );
	$strings = array(
		'kg' => array(
			'home_title'  => 'КУУ мерчи · 1925 коллекциясы — Жусуп Баласагын атындагы КУУнун 100 жылдыгына',
			'site'        => 'КУУ мерчи · 1925',
			'description' => 'Ж. Баласагын атындагы КУУнун 100 жылдыгына карата «1925» коллекциясы: худи, свитшот, джоггер, кепка, чыны жана брелок. Сайттан буйрутма бериңиз, КУУдан акысыз алып кетиңиз.',
			'locale'      => 'ky_KG',
		),
		'ru' => array(
			'home_title'  => 'Мерч КНУ · Коллекция 1925 — к 100-летию КНУ им. Ж. Баласагына',
			'site'        => 'Мерч КНУ · 1925',
			'description' => 'Коллекция «1925» к 100-летию КНУ им. Ж. Баласагына: худи, свитшоты, джоггеры, кепки, кружки и брелоки. Закажите на сайте и заберите бесплатно в КНУ.',
			'locale'      => 'ru_RU',
		),
	);
	return $strings[ $lang ? $lang : 'kg' ];
}

/**
 * Localised <title>: front page → full collection title; pages (legal) →
 * knu_page_title() + short brand. wp-admin is untouched.
 *
 * @param array $parts Title parts.
 * @return array
 */
function knu_document_title_parts( $parts ) {
	if ( is_admin() ) {
		return $parts;
	}
	$meta = knu_meta_strings();
	if ( is_front_page() ) {
		return array( 'title' => $meta['home_title'] );
	}
	if ( is_page() ) {
		$title = knu_page_title( get_queried_object_id() );
		if ( '' !== $title ) {
			$parts['title'] = $title;
		}
	}
	$parts['site'] = $meta['site'];
	unset( $parts['tagline'] );
	return $parts;
}
add_filter( 'document_title_parts', 'knu_document_title_parts' );
