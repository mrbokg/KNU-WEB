<?php
/**
 * REST endpoint POST /wp-json/knu/v1/orders (contract C4).
 *
 * Validates everything server-side and recalculates prices from product data.
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Delivery fees in som — the single source of truth used by REST pricing,
 * window.KNU.delivery and templates.
 *
 * @return array<string,int> pickup | bishkek | regions => fee.
 */
function knu_delivery_fees() {
	$fees = apply_filters(
		'knu_delivery_fees',
		array(
			'pickup'  => 0,
			'bishkek' => 150,
			'regions' => 300,
		)
	);
	$out  = array();
	foreach ( array( 'pickup', 'bishkek', 'regions' ) as $key ) {
		$out[ $key ] = isset( $fees[ $key ] ) ? max( 0, (int) $fees[ $key ] ) : 0;
	}
	return $out;
}

/**
 * Max request body size for the order endpoints, bytes.
 */
const KNU_REST_MAX_BODY = 20480;

/**
 * Register the route.
 */
function knu_register_order_route() {
	register_rest_route(
		'knu/v1',
		'/orders',
		array(
			'methods'             => 'POST',
			'callback'            => 'knu_rest_create_order',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'knu/v1',
		'/orders/(?P<number>KNU-\d{4,})/paid',
		array(
			'methods'             => 'POST',
			'callback'            => 'knu_rest_mark_paid',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'knu_register_order_route' );

/**
 * Core rejects a stale nonce of a logged-in user with the English
 * rest_cookie_invalid_nonce ("Cookie check failed") before our callback runs.
 * For knu/v1 routes, replace it with our localized knu_nonce 403.
 *
 * @param WP_HTTP_Response $result  Response.
 * @param WP_REST_Server   $server  Server.
 * @param WP_REST_Request  $request Request.
 * @return WP_HTTP_Response
 */
function knu_rest_localize_cookie_error( $result, $server, $request ) {
	if ( ! ( $result instanceof WP_HTTP_Response ) || ! ( $request instanceof WP_REST_Request ) ) {
		return $result;
	}
	if ( 0 !== strpos( (string) $request->get_route(), '/knu/v1/' ) ) {
		return $result;
	}
	$data = $result->get_data();
	if ( ! is_array( $data ) || 'rest_cookie_invalid_nonce' !== ( $data['code'] ?? '' ) ) {
		return $result;
	}
	$params = knu_rest_params( $request );
	$lang   = knu_sanitize_lang( knu_rest_str( $params['lang'] ?? '' ) );
	$msg    = knu_rest_messages( $lang ? $lang : knu_lang() );
	return rest_convert_error_to_response( new WP_Error( 'knu_nonce', $msg['nonce'], array( 'status' => 403 ) ) );
}
add_filter( 'rest_post_dispatch', 'knu_rest_localize_cookie_error', 10, 3 );

/**
 * Validation messages.
 *
 * @param string $lang Language.
 * @return array<string,string>
 */
function knu_rest_messages( $lang ) {
	$messages = array(
		'ru' => array(
			'invalid'  => 'Проверьте поля формы',
			'nonce'    => 'Сессия устарела. Обновите страницу и попробуйте снова.',
			'name'     => 'Введите имя (от 2 до 60 символов)',
			'phone'    => 'Введите номер телефона',
			'delivery' => 'Выберите способ получения',
			'address'  => 'Укажите адрес доставки',
			'items'    => 'Корзина пуста или содержит недоступные товары',
			'payment'  => 'Выберите способ оплаты',
			'consent'  => 'Подтвердите согласие с офертой и политикой конфиденциальности',
			'save'     => 'Не удалось сохранить заказ. Попробуйте ещё раз.',
			'token'    => 'Заказ не найден или ссылка недействительна',
			'state'    => 'Этот заказ не ожидает оплаты',
			'rate'     => 'Слишком много заказов с этого устройства. Попробуйте через несколько минут.',
			'large'    => 'Слишком большой запрос',
			'address_long' => 'Адрес слишком длинный (не более 200 символов)',
			'comment'  => 'Комментарий слишком длинный (не более 500 символов)',
		),
		'kg' => array(
			'invalid'  => 'Форманын талааларын текшериңиз',
			'nonce'    => 'Сессиянын мөөнөтү бүттү. Баракты жаңылап, кайра аракет кылыңыз.',
			'name'     => 'Атыңызды жазыңыз (2ден 60 белгиге чейин)',
			'phone'    => 'Телефон номериңизди жазыңыз',
			'delivery' => 'Алуу ыкмасын тандаңыз',
			'address'  => 'Жеткирүү дарегин жазыңыз',
			'items'    => 'Себет бош же анда жеткиликсиз товарлар бар',
			'payment'  => 'Төлөө ыкмасын тандаңыз',
			'consent'  => 'Оферта жана купуялык саясаты менен макул экениңизди ырастаңыз',
			'save'     => 'Буйрутманы сактоо мүмкүн болгон жок. Кайра аракет кылыңыз.',
			'token'    => 'Буйрутма табылган жок же шилтеме жараксыз',
			'state'    => 'Бул буйрутма төлөмдү күтпөйт',
			'rate'     => 'Бул түзмөктөн буйрутмалар өтө көп. Бир нече мүнөттөн кийин кайра аракет кылыңыз.',
			'large'    => 'Суроо-талап өтө чоң',
			'address_long' => 'Дарек өтө узун (200 белгиден ашпасын)',
			'comment'  => 'Комментарий өтө узун (500 белгиден ашпасын)',
		),
	);
	return $messages[ $lang ] ?? $messages['kg'];
}

/**
 * Scalar → trimmed string; anything else → ''.
 *
 * @param mixed $value Raw.
 * @return string
 */
function knu_rest_str( $value ) {
	if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
		return trim( (string) $value );
	}
	return '';
}

/**
 * Is the database the SQLite drop-in (WordPress Playground)?
 *
 * @return bool
 */
function knu_db_is_sqlite() {
	global $wpdb;
	if ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( (string) DB_ENGINE ) ) {
		return true;
	}
	return is_object( $wpdb ) && false !== stripos( get_class( $wpdb ), 'sqlite' );
}

/**
 * Next order number: KNU-0001, KNU-0002, … Unique under concurrent requests.
 *
 * MySQL: one atomic UPDATE … LAST_INSERT_ID(option_value + 1), then
 * SELECT LAST_INSERT_ID() (connection-local, so no race).
 * SQLite / fallback: atomic UPDATE option_value = option_value + 1, then read;
 * two requests may read the same value, so each number is additionally
 * claimed with an INSERT IGNORE row (knu_db_lock) — only one request wins,
 * the loser increments again. knu_release_order_number() drops the claim once
 * the order is saved. A number already used by an order is never reused.
 *
 * @return string e.g. KNU-0007.
 */
function knu_next_order_number() {
	global $wpdb;

	if ( false === get_option( 'knu_order_seq', false ) ) {
		add_option( 'knu_order_seq', 0, '', 'no' );
	}

	$use_last_insert_id = ! knu_db_is_sqlite();

	for ( $attempt = 0; $attempt < 25; $attempt++ ) {
		$seq = 0;

		if ( $use_last_insert_id ) {
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s", 'knu_order_seq' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( 1 === (int) $updated ) {
				$seq = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			if ( $seq < 1 ) {
				// Driver without LAST_INSERT_ID(expr): use the portable path from now on.
				$use_last_insert_id = false;
			}
		}

		$claimed = false;
		if ( $seq < 1 ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", 'knu_order_seq' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$seq = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'knu_order_seq' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $seq < 1 ) {
				continue;
			}
			if ( ! knu_db_lock( 'knu_order_claim_' . $seq ) ) {
				continue; // Another request took this number.
			}
			$claimed = true;
		}
		wp_cache_delete( 'knu_order_seq', 'options' );

		$number = sprintf( 'KNU-%04d', $seq );
		if ( knu_find_order_id( $number ) ) {
			// Counter was reset or edited: skip numbers already used.
			if ( $claimed ) {
				knu_db_unlock( 'knu_order_claim_' . $seq );
			}
			continue;
		}
		return $number;
	}

	// Practically unreachable: a random suffix keeps the number unique.
	return sprintf( 'KNU-%04d', (int) get_option( 'knu_order_seq', 0 ) ) . '-' . strtoupper( wp_generate_password( 4, false ) );
}

/**
 * Drop the claim row created by knu_next_order_number() (no-op on MySQL).
 *
 * @param string $number Order number.
 */
function knu_release_order_number( $number ) {
	if ( preg_match( '/^KNU-(\d+)$/', (string) $number, $m ) ) {
		knu_db_unlock( 'knu_order_claim_' . (int) $m[1] );
	}
}

/**
 * Rate-limit transient name: salted hash, the raw IP is never stored.
 *
 * @return string
 */
function knu_rate_limit_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return 'knu_rl_' . md5( wp_salt() . $ip );
}

/**
 * Has the client used up its order quota?
 *
 * Limit: apply_filters( 'knu_order_rate_limit', 5 ) orders per
 * apply_filters( 'knu_order_rate_window', 600 ) seconds.
 *
 * @return bool
 */
function knu_rate_limited() {
	$limit = (int) apply_filters( 'knu_order_rate_limit', 5 );
	if ( $limit < 1 ) {
		return false;
	}
	$state = get_transient( knu_rate_limit_key() );
	return is_array( $state ) && (int) ( $state['count'] ?? 0 ) >= $limit;
}

/**
 * Count one created order for the client.
 */
function knu_rate_limit_hit() {
	$window = max( 60, (int) apply_filters( 'knu_order_rate_window', 10 * MINUTE_IN_SECONDS ) );
	$key    = knu_rate_limit_key();
	$state  = get_transient( $key );
	$now    = time();
	if ( ! is_array( $state ) || $now - (int) ( $state['start'] ?? 0 ) >= $window ) {
		$state = array(
			'start' => $now,
			'count' => 0,
		);
	}
	++$state['count'];
	set_transient( $key, $state, max( 1, $window - ( $now - (int) $state['start'] ) ) );
}

/**
 * Normalise a Kyrgyz phone number.
 *
 * Digits only; a leading 996 country code and then one leading 0 (trunk
 * prefix) are stripped; the national number must be exactly 9 digits.
 *
 * @param string $phone Raw phone.
 * @return string '+996 XXX XXX XXX', or '' when invalid.
 */
function knu_normalize_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( strlen( $digits ) > 9 && 0 === strpos( $digits, '996' ) ) {
		$digits = substr( $digits, 3 );
	}
	if ( '' !== $digits && '0' === $digits[0] ) {
		$digits = substr( $digits, 1 );
	}
	if ( ! preg_match( '/^\d{9}$/', $digits ) ) {
		return '';
	}
	return '+996 ' . substr( $digits, 0, 3 ) . ' ' . substr( $digits, 3, 3 ) . ' ' . substr( $digits, 6, 3 );
}

/**
 * Decoded JSON (or form) params of a request.
 *
 * @param WP_REST_Request $request Request.
 * @return array
 */
function knu_rest_params( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = $request->get_body_params();
	}
	return is_array( $params ) ? $params : array();
}

/**
 * Body larger than KNU_REST_MAX_BODY?
 *
 * @param WP_REST_Request $request Request.
 * @return bool
 */
function knu_rest_body_too_large( WP_REST_Request $request ) {
	return strlen( (string) $request->get_body() ) > KNU_REST_MAX_BODY;
}

/**
 * Validate the cart items against product data and price them.
 *
 * @param mixed  $raw_items Raw items.
 * @param string $lang      Language for titles / color names.
 * @return array|null Priced items, or null when invalid.
 */
function knu_rest_price_items( $raw_items, $lang ) {
	if ( ! is_array( $raw_items ) || count( $raw_items ) < 1 || count( $raw_items ) > 20 ) {
		return null;
	}

	$catalog = array();
	foreach ( knu_query_products( $lang ) as $product ) {
		$catalog[ $product['slug'] ] = $product;
	}

	$items = array();
	$index = array(); // "slug|color|size" => position in $items.
	foreach ( $raw_items as $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$slug  = sanitize_title( knu_rest_str( $raw['slug'] ?? '' ) );
		$color = sanitize_key( knu_rest_str( $raw['color'] ?? '' ) );
		$size  = sanitize_text_field( knu_rest_str( $raw['size'] ?? '' ) );
		$qty   = $raw['qty'] ?? null;

		if ( '' === $slug || ! isset( $catalog[ $slug ] ) ) {
			return null;
		}
		if ( ! ( is_int( $qty ) || ( is_string( $qty ) && ctype_digit( $qty ) ) || ( is_float( $qty ) && floor( $qty ) === $qty ) ) ) {
			return null;
		}
		$qty = (int) $qty;
		if ( $qty < 1 || $qty > 10 ) {
			return null;
		}

		$product = $catalog[ $slug ];

		$color_name = null;
		foreach ( $product['colors'] as $c ) {
			if ( $c['key'] === $color ) {
				$color_name = $c['name'];
				break;
			}
		}
		if ( null === $color_name ) {
			return null;
		}

		if ( empty( $product['sizes'] ) ) {
			if ( '' !== $size ) {
				return null;
			}
		} elseif ( ! in_array( $size, $product['sizes'], true ) ) {
			return null;
		}

		// Same slug + color + size → one line; the merged qty must stay within 1–10.
		$line_key = $slug . '|' . $color . '|' . $size;
		if ( isset( $index[ $line_key ] ) ) {
			$pos = $index[ $line_key ];
			if ( $items[ $pos ]['qty'] + $qty > 10 ) {
				return null;
			}
			$items[ $pos ]['qty'] += $qty;
			$items[ $pos ]['sum']  = $items[ $pos ]['price'] * $items[ $pos ]['qty'];
			continue;
		}

		$price              = (int) $product['price'];
		$index[ $line_key ] = count( $items );
		$items[]            = array(
			'slug'      => $slug,
			'title'     => $product['title'],
			'color_key' => $color,
			'color'     => $color_name,
			'size'      => $size,
			'qty'       => $qty,
			'price'     => $price,
			'sum'       => $price * $qty,
		);
	}

	return $items;
}

/**
 * Create an order.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function knu_rest_create_order( WP_REST_Request $request ) {
	if ( knu_rest_body_too_large( $request ) ) {
		$msg = knu_rest_messages( knu_lang() );
		return new WP_Error( 'knu_too_large', $msg['large'], array( 'status' => 413 ) );
	}

	$params = knu_rest_params( $request );

	$lang = knu_sanitize_lang( knu_rest_str( $params['lang'] ?? '' ) );
	if ( ! $lang ) {
		$lang = 'kg';
	}
	$msg = knu_rest_messages( $lang );

	// Nonce. WP core only checks X-WP-Nonce for logged-in cookies, so verify it here for everyone.
	$nonce = (string) $request->get_header( 'X-WP-Nonce' );
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'knu_nonce', $msg['nonce'], array( 'status' => 403 ) );
	}

	// Honeypot ("website" — legacy name, "knu_hp" — current): pretend success, store nothing.
	$honeypot = false;
	foreach ( array( 'website', 'knu_hp' ) as $hp ) {
		if ( isset( $params[ $hp ] ) && ( ! is_string( $params[ $hp ] ) || '' !== trim( $params[ $hp ] ) ) ) {
			$honeypot = true;
		}
	}
	if ( $honeypot ) {
		return new WP_REST_Response(
			array(
				'ok'     => true,
				'number' => 'KNU-0000',
			),
			200
		);
	}

	if ( knu_rate_limited() ) {
		return new WP_Error( 'knu_rate_limited', $msg['rate'], array( 'status' => 429 ) );
	}

	$fields = array();

	$name = trim( sanitize_text_field( wp_strip_all_tags( knu_rest_str( $params['name'] ?? '' ) ) ) );
	$len  = mb_strlen( $name );
	if ( $len < 2 || $len > 60 ) {
		$fields['name'] = $msg['name'];
	}

	// Stored normalised: '+996 XXX XXX XXX'.
	$phone_raw = sanitize_text_field( knu_rest_str( $params['phone'] ?? '' ) );
	$phone     = mb_strlen( $phone_raw ) > 30 ? '' : knu_normalize_phone( $phone_raw );
	if ( '' === $phone ) {
		$fields['phone'] = $msg['phone'];
	}

	$delivery = sanitize_key( knu_rest_str( $params['delivery'] ?? '' ) );
	$fees     = knu_delivery_fees();
	if ( ! array_key_exists( $delivery, $fees ) ) {
		$fields['delivery'] = $msg['delivery'];
	}

	// Tags stripped, whitespace trimmed; too long → field error (not silently cut).
	$address = trim( sanitize_text_field( wp_strip_all_tags( knu_rest_str( $params['address'] ?? '' ) ) ) );
	if ( 'pickup' === $delivery ) {
		$address = '';
	} elseif ( mb_strlen( $address ) > 200 ) {
		$fields['address'] = $msg['address_long'];
	} elseif ( array_key_exists( $delivery, $fees ) && mb_strlen( $address ) < 3 ) {
		$fields['address'] = $msg['address'];
	}

	$comment = trim( sanitize_textarea_field( wp_strip_all_tags( knu_rest_str( $params['comment'] ?? '' ) ) ) );
	if ( mb_strlen( $comment ) > 500 ) {
		$fields['comment'] = $msg['comment'];
	}

	$items = knu_rest_price_items( $params['items'] ?? null, $lang );
	if ( null === $items ) {
		$fields['items'] = $msg['items'];
	}

	$payment = sanitize_key( knu_rest_str( $params['payment'] ?? '' ) );
	if ( ! in_array( $payment, array( 'qr', 'cash' ), true ) ) {
		$fields['payment'] = $msg['payment'];
	}

	$consent = $params['consent'] ?? false;
	if ( true !== $consent && 1 !== $consent && '1' !== $consent && 'true' !== $consent ) {
		$fields['consent'] = $msg['consent'];
	}

	if ( $fields ) {
		return new WP_Error(
			'knu_invalid',
			$msg['invalid'],
			array(
				'status' => 400,
				'fields' => $fields,
			)
		);
	}

	$subtotal     = (int) array_sum( wp_list_pluck( $items, 'sum' ) );
	$delivery_fee = (int) $fees[ $delivery ];
	$total        = $subtotal + $delivery_fee;
	$number       = knu_next_order_number();
	$status       = ( 'qr' === $payment ) ? 'awaiting_payment' : 'new';
	$token        = bin2hex( random_bytes( 16 ) );

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'knu_order',
			'post_status' => 'publish',
			'post_title'  => $number,
			'post_name'   => strtolower( $number ),
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		knu_release_order_number( $number );
		return new WP_Error( 'knu_save_failed', $msg['save'], array( 'status' => 500 ) );
	}

	$meta = array(
		'number'       => $number,
		'name'         => $name,
		'phone'        => $phone,
		'delivery'     => $delivery,
		'address'      => $address,
		'comment'      => $comment,
		'items'        => $items,
		'subtotal'     => $subtotal,
		'delivery_fee' => $delivery_fee,
		'total'        => $total,
		'status'       => $status,
		'lang'         => $lang,
		'payment'      => $payment,
		'consent_at'   => wp_date( 'c' ),
		'token'        => $token,
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, '_knu_' . $key, wp_slash( $value ) );
	}
	knu_release_order_number( $number );
	knu_rate_limit_hit();

	$public_items = array();
	foreach ( $items as $item ) {
		$public_items[] = array(
			'title' => $item['title'],
			'color' => $item['color'],
			'size'  => $item['size'],
			'qty'   => $item['qty'],
			'price' => $item['price'],
			'sum'   => $item['sum'],
		);
	}

	return new WP_REST_Response(
		array(
			'ok'           => true,
			'number'       => $number,
			'subtotal'     => $subtotal,
			'delivery_fee' => $delivery_fee,
			'total'        => $total,
			'items'        => $public_items,
			'payment'      => $payment,
			'status'       => $status,
			'token'        => $token,
		),
		201
	);
}

/**
 * Find an order post ID by its number.
 *
 * @param string $number Order number, e.g. KNU-0007.
 * @return int 0 when not found.
 */
function knu_find_order_id( $number ) {
	$ids = get_posts(
		array(
			'post_type'        => 'knu_order',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_key'         => '_knu_number', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $number, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * POST /knu/v1/orders/{number}/paid — customer says the QR payment is done.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function knu_rest_mark_paid( WP_REST_Request $request ) {
	if ( knu_rest_body_too_large( $request ) ) {
		$msg = knu_rest_messages( knu_lang() );
		return new WP_Error( 'knu_too_large', $msg['large'], array( 'status' => 413 ) );
	}

	// Optional "lang" in the body picks the message language (backward compatible).
	$params = knu_rest_params( $request );
	$lang   = knu_sanitize_lang( knu_rest_str( $params['lang'] ?? '' ) );
	$msg    = knu_rest_messages( $lang ? $lang : knu_lang() );

	$nonce = (string) $request->get_header( 'X-WP-Nonce' );
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'knu_nonce', $msg['nonce'], array( 'status' => 403 ) );
	}

	$number = strtoupper( knu_rest_str( $request->get_param( 'number' ) ) );
	$token  = strtolower( knu_rest_str( $params['token'] ?? '' ) );
	if ( ! preg_match( '/^KNU-\d{4,}$/', $number ) || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
		return new WP_Error( 'knu_forbidden', $msg['token'], array( 'status' => 403 ) );
	}

	$order_id = knu_find_order_id( $number );
	$stored   = $order_id ? (string) get_post_meta( $order_id, '_knu_token', true ) : '';
	if ( ! $order_id || '' === $stored || ! hash_equals( $stored, $token ) ) {
		return new WP_Error( 'knu_forbidden', $msg['token'], array( 'status' => 403 ) );
	}

	$status = (string) get_post_meta( $order_id, '_knu_status', true );
	if ( 'awaiting_payment' === $status ) {
		update_post_meta( $order_id, '_knu_status', 'payment_check' );
		$status = 'payment_check';
	} elseif ( 'payment_check' !== $status ) {
		// Repeated clicks on an already checked order are fine; other states are not.
		return new WP_Error( 'knu_wrong_status', $msg['state'], array( 'status' => 409 ) );
	}

	return new WP_REST_Response(
		array(
			'ok'     => true,
			'status' => $status,
		),
		200
	);
}
