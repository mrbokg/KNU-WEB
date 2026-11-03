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
 * Delivery fees in som.
 *
 * @return array<string,int>
 */
function knu_delivery_fees() {
	return array(
		'pickup'  => 0,
		'bishkek' => 150,
		'regions' => 300,
	);
}

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
 * Next order number: KNU-0001, KNU-0002, …
 *
 * @return string
 */
function knu_next_order_number() {
	$seq = (int) get_option( 'knu_order_seq', 0 ) + 1;
	update_option( 'knu_order_seq', $seq, false );
	return sprintf( 'KNU-%04d', $seq );
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

		$price   = (int) $product['price'];
		$items[] = array(
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
	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = $request->get_body_params();
	}
	if ( ! is_array( $params ) ) {
		$params = array();
	}

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

	// Honeypot: pretend success, store nothing.
	if ( '' !== knu_rest_str( $params['website'] ?? '' ) || ( isset( $params['website'] ) && ! is_string( $params['website'] ) ) ) {
		return new WP_REST_Response(
			array(
				'ok'     => true,
				'number' => 'KNU-0000',
			),
			200
		);
	}

	$fields = array();

	$name = sanitize_text_field( knu_rest_str( $params['name'] ?? '' ) );
	$len  = mb_strlen( $name );
	if ( $len < 2 || $len > 60 ) {
		$fields['name'] = $msg['name'];
	}

	$phone  = sanitize_text_field( knu_rest_str( $params['phone'] ?? '' ) );
	$digits = preg_replace( '/\D+/', '', $phone );
	$dlen   = strlen( $digits );
	if ( $dlen < 9 || $dlen > 15 || mb_strlen( $phone ) > 30 ) {
		$fields['phone'] = $msg['phone'];
	}

	$delivery = sanitize_key( knu_rest_str( $params['delivery'] ?? '' ) );
	$fees     = knu_delivery_fees();
	if ( ! array_key_exists( $delivery, $fees ) ) {
		$fields['delivery'] = $msg['delivery'];
	}

	$address = sanitize_text_field( knu_rest_str( $params['address'] ?? '' ) );
	$address = mb_substr( $address, 0, 200 );
	if ( 'pickup' === $delivery ) {
		$address = '';
	} elseif ( array_key_exists( $delivery, $fees ) && mb_strlen( $address ) < 3 ) {
		$fields['address'] = $msg['address'];
	}

	$comment = mb_substr( sanitize_textarea_field( knu_rest_str( $params['comment'] ?? '' ) ), 0, 500 );

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
	$params = $request->get_json_params();
	if ( ! is_array( $params ) ) {
		$params = $request->get_body_params();
	}
	if ( ! is_array( $params ) ) {
		$params = array();
	}

	$lang = knu_sanitize_lang( knu_rest_str( $params['lang'] ?? '' ) );
	$msg  = knu_rest_messages( $lang ? $lang : knu_lang() );

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
