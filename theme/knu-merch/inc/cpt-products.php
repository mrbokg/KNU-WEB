<?php
/**
 * Products: CPT knu_product, admin meta box, public helpers.
 *
 * Post fields: post_title = RU title, post_content = RU description,
 * post_name = slug, menu_order = sort order. Everything else is meta.
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the CPT.
 */
function knu_register_product_cpt() {
	register_post_type(
		'knu_product',
		array(
			'labels'          => array(
				'name'               => 'Товары',
				'singular_name'      => 'Товар',
				'menu_name'          => 'Товары КНУ',
				'add_new'            => 'Добавить товар',
				'add_new_item'       => 'Новый товар',
				'edit_item'          => 'Редактировать товар',
				'new_item'           => 'Новый товар',
				'view_item'          => 'Просмотр товара',
				'search_items'       => 'Найти товар',
				'not_found'          => 'Товары не найдены',
				'not_found_in_trash' => 'В корзине товаров нет',
				'all_items'          => 'Все товары',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_position'   => 25,
			'menu_icon'       => 'dashicons-cart',
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'hierarchical'    => false,
			'supports'        => array( 'title', 'editor', 'page-attributes', 'thumbnail' ),
			'rewrite'         => false,
			'query_var'       => false,
		)
	);
}
add_action( 'init', 'knu_register_product_cpt' );

/**
 * Meta keys handled by the product meta box.
 *
 * @return string[]
 */
function knu_product_text_meta() {
	return array( 'title_kg', 'badge_ru', 'badge_kg' );
}

/**
 * Read one product meta value.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Key without the _knu_ prefix.
 * @return mixed
 */
function knu_product_meta( $post_id, $key ) {
	return get_post_meta( $post_id, '_knu_' . $key, true );
}

/**
 * Pretty JSON for admin textareas.
 *
 * @param mixed $value Value.
 * @return string
 */
function knu_pretty_json( $value ) {
	if ( empty( $value ) ) {
		return '';
	}
	return (string) wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * Register the meta box.
 */
function knu_product_add_meta_box() {
	add_meta_box( 'knu_product_data', 'Данные товара', 'knu_product_meta_box', 'knu_product', 'normal', 'high' );
}
add_action( 'add_meta_boxes_knu_product', 'knu_product_add_meta_box' );

/**
 * Render the meta box.
 *
 * @param WP_Post $post Post.
 */
function knu_product_meta_box( $post ) {
	wp_nonce_field( 'knu_product_save', 'knu_product_nonce' );

	$type   = knu_product_meta( $post->ID, 'type' );
	$sizes  = knu_product_meta( $post->ID, 'sizes' );
	$sizes  = is_array( $sizes ) ? implode( ', ', $sizes ) : '';
	$colors = knu_product_meta( $post->ID, 'colors' );
	$images = knu_product_meta( $post->ID, 'images' );
	?>
	<p class="description">Название и описание на русском — в основных полях выше. Порядок — в блоке «Атрибуты» (поле «Порядок»). Слаг (адрес) товара используется в корзине — не меняйте его без необходимости: <code><?php echo esc_html( $post->post_name ); ?></code></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="knu_type">Тип</label></th>
			<td>
				<select id="knu_type" name="knu_type">
					<option value="set" <?php selected( $type, 'set' ); ?>>Двойка (комплект)</option>
					<option value="accessory" <?php selected( $type, 'accessory' ); ?>>Аксессуар</option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_price">Цена, сом</label></th>
			<td><input type="number" min="0" step="1" id="knu_price" name="knu_price" value="<?php echo esc_attr( (int) knu_product_meta( $post->ID, 'price' ) ); ?>" class="small-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_price_separate">Цена «по отдельности», сом</label></th>
			<td><input type="number" min="0" step="1" id="knu_price_separate" name="knu_price_separate" value="<?php echo esc_attr( (int) knu_product_meta( $post->ID, 'price_separate' ) ); ?>" class="small-text"> <span class="description">0 — не показывать</span></td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_title_kg">Название (KG)</label></th>
			<td><input type="text" id="knu_title_kg" name="knu_title_kg" value="<?php echo esc_attr( knu_product_meta( $post->ID, 'title_kg' ) ); ?>" class="large-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_desc_kg">Описание (KG)</label></th>
			<td><textarea id="knu_desc_kg" name="knu_desc_kg" rows="3" class="large-text"><?php echo esc_textarea( knu_product_meta( $post->ID, 'desc_kg' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_badge_ru">Бейдж (RU / KG)</label></th>
			<td>
				<input type="text" id="knu_badge_ru" name="knu_badge_ru" value="<?php echo esc_attr( knu_product_meta( $post->ID, 'badge_ru' ) ); ?>" placeholder="RU">
				<input type="text" id="knu_badge_kg" name="knu_badge_kg" value="<?php echo esc_attr( knu_product_meta( $post->ID, 'badge_kg' ) ); ?>" placeholder="KG" aria-label="Бейдж (KG)">
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_composition_ru">Состав и уход (RU)</label></th>
			<td><textarea id="knu_composition_ru" name="knu_composition_ru" rows="2" class="large-text"><?php echo esc_textarea( knu_product_meta( $post->ID, 'composition_ru' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_composition_kg">Состав и уход (KG)</label></th>
			<td><textarea id="knu_composition_kg" name="knu_composition_kg" rows="2" class="large-text"><?php echo esc_textarea( knu_product_meta( $post->ID, 'composition_kg' ) ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_sizes">Размеры</label></th>
			<td><input type="text" id="knu_sizes" name="knu_sizes" value="<?php echo esc_attr( $sizes ); ?>" class="regular-text"> <span class="description">Через запятую: XS, S, M. Пусто — без размеров.</span></td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_colors">Цвета (JSON)</label></th>
			<td>
				<textarea id="knu_colors" name="knu_colors" rows="10" class="large-text code"><?php echo esc_textarea( knu_pretty_json( $colors ) ); ?></textarea>
				<p class="description">Формат: <code>[{"key":"navy","hex":"#1B2A44","name":{"ru":"Тёмно-синий","kg":"Кочкул көк"}}]</code>. При ошибке в JSON сохраняется прежнее значение.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="knu_images">Фото по цветам (JSON)</label></th>
			<td>
				<textarea id="knu_images" name="knu_images" rows="6" class="large-text code"><?php echo esc_textarea( knu_pretty_json( $images ) ); ?></textarea>
				<p class="description">Формат: <code>{"navy":["set-hoodie-navy-1.webp","https://…/photo.webp"]}</code>. Имя файла — из папки темы assets/img/products/, либо полный URL.</p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Sanitize a colors array (data-products.php format).
 *
 * @param mixed $colors Raw.
 * @return array
 */
function knu_sanitize_colors( $colors ) {
	$out = array();
	if ( ! is_array( $colors ) ) {
		return $out;
	}
	foreach ( $colors as $color ) {
		if ( ! is_array( $color ) || empty( $color['key'] ) || ! is_string( $color['key'] ) ) {
			continue;
		}
		$key = sanitize_key( $color['key'] );
		if ( '' === $key ) {
			continue;
		}
		$name = isset( $color['name'] ) ? $color['name'] : '';
		if ( ! is_array( $name ) ) {
			$name = array(
				'ru' => $name,
				'kg' => $name,
			);
		}
		$hex   = isset( $color['hex'] ) && is_string( $color['hex'] ) ? sanitize_hex_color( $color['hex'] ) : '';
		$out[] = array(
			'key'  => $key,
			'hex'  => $hex ? $hex : '#CCCCCC',
			'name' => array(
				'ru' => isset( $name['ru'] ) && is_scalar( $name['ru'] ) ? sanitize_text_field( (string) $name['ru'] ) : $key,
				'kg' => isset( $name['kg'] ) && is_scalar( $name['kg'] ) ? sanitize_text_field( (string) $name['kg'] ) : '',
			),
		);
	}
	return $out;
}

/**
 * Sanitize an images map: color key => list of file names or URLs.
 *
 * @param mixed $images Raw.
 * @return array<string,string[]>
 */
function knu_sanitize_images( $images ) {
	$out = array();
	if ( ! is_array( $images ) ) {
		return $out;
	}
	foreach ( $images as $key => $files ) {
		$key = sanitize_key( (string) $key );
		if ( '' === $key || ! is_array( $files ) ) {
			continue;
		}
		$list = array();
		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || '' === trim( $file ) ) {
				continue;
			}
			$file   = trim( $file );
			$list[] = preg_match( '~^https?://~i', $file ) ? esc_url_raw( $file ) : sanitize_file_name( $file );
		}
		$out[ $key ] = array_values( array_filter( $list ) );
	}
	return $out;
}

/**
 * Sanitize a sizes value (array or comma separated string).
 *
 * @param mixed $sizes Raw.
 * @return string[]
 */
function knu_sanitize_sizes( $sizes ) {
	if ( is_string( $sizes ) ) {
		$sizes = explode( ',', $sizes );
	}
	if ( ! is_array( $sizes ) ) {
		return array();
	}
	$out = array();
	foreach ( $sizes as $size ) {
		if ( ! is_scalar( $size ) ) {
			continue;
		}
		$size = strtoupper( sanitize_text_field( (string) $size ) );
		if ( '' !== $size && ! in_array( $size, $out, true ) ) {
			$out[] = substr( $size, 0, 10 );
		}
	}
	return $out;
}

/**
 * A POSTed field as an unslashed string; arrays and other non-strings → ''.
 * Prevents TypeErrors (e.g. trim() on knu_colors[]=…) from crafted requests.
 *
 * @param string $key $_POST key.
 * @return string
 */
function knu_post_str( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.
	if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
		return '';
	}
	return (string) wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by callers.
}

/**
 * Save the meta box.
 *
 * @param int $post_id Post ID.
 */
function knu_product_save( $post_id ) {
	if ( ! wp_verify_nonce( sanitize_text_field( knu_post_str( 'knu_product_nonce' ) ), 'knu_product_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$type = sanitize_key( knu_post_str( 'knu_type' ) );
	update_post_meta( $post_id, '_knu_type', in_array( $type, array( 'set', 'accessory' ), true ) ? $type : 'set' );

	foreach ( array( 'price', 'price_separate' ) as $key ) {
		$value = absint( knu_post_str( 'knu_' . $key ) );
		update_post_meta( $post_id, '_knu_' . $key, wp_slash( $value ) );
	}

	foreach ( knu_product_text_meta() as $key ) {
		$value = sanitize_text_field( knu_post_str( 'knu_' . $key ) );
		update_post_meta( $post_id, '_knu_' . $key, wp_slash( $value ) );
	}

	foreach ( array( 'desc_kg', 'composition_ru', 'composition_kg' ) as $key ) {
		$value = sanitize_textarea_field( knu_post_str( 'knu_' . $key ) );
		update_post_meta( $post_id, '_knu_' . $key, wp_slash( $value ) );
	}

	$sizes = sanitize_text_field( knu_post_str( 'knu_sizes' ) );
	update_post_meta( $post_id, '_knu_sizes', wp_slash( knu_sanitize_sizes( $sizes ) ) );

	// JSON fields: keep the previous value when JSON is invalid.
	if ( isset( $_POST['knu_colors'] ) && is_string( $_POST['knu_colors'] ) ) {
		$raw     = trim( knu_post_str( 'knu_colors' ) ); // Decoded and sanitized below.
		$decoded = ( '' === $raw ) ? array() : json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			update_post_meta( $post_id, '_knu_colors', wp_slash( knu_sanitize_colors( $decoded ) ) );
		}
	}
	if ( isset( $_POST['knu_images'] ) && is_string( $_POST['knu_images'] ) ) {
		$raw     = trim( knu_post_str( 'knu_images' ) ); // Decoded and sanitized below.
		$decoded = ( '' === $raw ) ? array() : json_decode( $raw, true );
		if ( is_array( $decoded ) ) {
			update_post_meta( $post_id, '_knu_images', wp_slash( knu_sanitize_images( $decoded ) ) );
		}
	}

	knu_flush_products_cache();
}
add_action( 'save_post_knu_product', 'knu_product_save' );

/**
 * Admin list columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function knu_product_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['knu_type']  = 'Тип';
			$new['knu_price'] = 'Цена';
			$new['knu_order'] = 'Порядок';
		}
	}
	return $new;
}
add_filter( 'manage_knu_product_posts_columns', 'knu_product_columns' );

/**
 * Admin list column values.
 *
 * @param string $column  Column.
 * @param int    $post_id Post ID.
 */
function knu_product_column_value( $column, $post_id ) {
	switch ( $column ) {
		case 'knu_type':
			echo esc_html( 'accessory' === knu_product_meta( $post_id, 'type' ) ? 'Аксессуар' : 'Двойка' );
			break;
		case 'knu_price':
			echo esc_html( knu_money_lang( (int) knu_product_meta( $post_id, 'price' ), 'ru' ) );
			break;
		case 'knu_order':
			echo esc_html( (string) get_post_field( 'menu_order', $post_id ) );
			break;
	}
}
add_action( 'manage_knu_product_posts_custom_column', 'knu_product_column_value', 10, 2 );

/**
 * Sort admin list by menu_order by default.
 *
 * @param WP_Query $query Query.
 */
function knu_product_admin_order( $query ) {
	if ( is_admin() && $query->is_main_query() && 'knu_product' === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'knu_product_admin_order' );

/**
 * URL of a product image.
 *
 * @param string $file File name in assets/img/products/ (or a full URL).
 * @return string
 */
function knu_img( $file ) {
	$file = (string) $file;
	if ( preg_match( '~^https?://~i', $file ) ) {
		return esc_url_raw( $file );
	}
	return get_theme_file_uri( 'assets/img/products/' . ltrim( $file, '/' ) );
}

/**
 * Format a price in som for a language.
 *
 * @param int    $som  Amount.
 * @param string $lang Language.
 * @return string e.g. "4 900 сом" with non-breaking spaces.
 */
function knu_money_lang( $som, $lang ) {
	$nbsp = "\u{00A0}";
	// Same currency word in RU and KG.
	unset( $lang );
	return number_format( (int) $som, 0, '.', $nbsp ) . $nbsp . 'сом';
}

/**
 * Format a price in som for the current language.
 *
 * @param int $som Amount.
 * @return string
 */
function knu_money( $som ) {
	return knu_money_lang( (int) $som, knu_lang() );
}

/**
 * Drop the per-request products cache.
 */
function knu_flush_products_cache() {
	$GLOBALS['knu_products_cache'] = array();
}

/**
 * Localised products for a given language.
 *
 * @param string      $lang Language ('ru' | 'kg').
 * @param string|null $type 'set' | 'accessory' | null for all.
 * @return array[]
 */
function knu_query_products( $lang, $type = null ) {
	$lang = knu_sanitize_lang( $lang );
	if ( ! $lang ) {
		$lang = 'ru';
	}

	if ( ! isset( $GLOBALS['knu_products_cache'] ) || ! is_array( $GLOBALS['knu_products_cache'] ) ) {
		$GLOBALS['knu_products_cache'] = array();
	}

	if ( ! isset( $GLOBALS['knu_products_cache'][ $lang ] ) ) {
		$posts = get_posts(
			array(
				'post_type'        => 'knu_product',
				'post_status'      => 'publish',
				'posts_per_page'   => 100,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		$list = array();
		foreach ( $posts as $post ) {
			$list[] = knu_format_product( $post, $lang );
		}
		$GLOBALS['knu_products_cache'][ $lang ] = $list;
	}

	$products = $GLOBALS['knu_products_cache'][ $lang ];
	if ( null === $type ) {
		return $products;
	}
	return array_values(
		array_filter(
			$products,
			function ( $product ) use ( $type ) {
				return $product['type'] === $type;
			}
		)
	);
}

/**
 * Build the public product array (contract C2).
 *
 * @param WP_Post $post Post.
 * @param string  $lang Language.
 * @return array
 */
function knu_format_product( $post, $lang ) {
	$id = $post->ID;
	$kg = ( 'kg' === $lang );

	$title_kg = (string) knu_product_meta( $id, 'title_kg' );
	$desc_kg  = (string) knu_product_meta( $id, 'desc_kg' );
	$comp_ru  = (string) knu_product_meta( $id, 'composition_ru' );
	$comp_kg  = (string) knu_product_meta( $id, 'composition_kg' );
	$badge_ru = (string) knu_product_meta( $id, 'badge_ru' );
	$badge_kg = (string) knu_product_meta( $id, 'badge_kg' );

	$type   = knu_product_meta( $id, 'type' );
	$sizes  = knu_product_meta( $id, 'sizes' );
	$colors = knu_product_meta( $id, 'colors' );
	$images = knu_product_meta( $id, 'images' );
	$images = is_array( $images ) ? $images : array();

	$out_colors = array();
	foreach ( ( is_array( $colors ) ? $colors : array() ) as $color ) {
		if ( empty( $color['key'] ) ) {
			continue;
		}
		$key   = (string) $color['key'];
		$names = isset( $color['name'] ) && is_array( $color['name'] ) ? $color['name'] : array();
		$name  = ( $kg && ! empty( $names['kg'] ) ) ? $names['kg'] : ( $names['ru'] ?? $key );
		$files = isset( $images[ $key ] ) && is_array( $images[ $key ] ) ? $images[ $key ] : array();

		$out_colors[] = array(
			'key'    => $key,
			'hex'    => isset( $color['hex'] ) ? (string) $color['hex'] : '',
			'name'   => (string) $name,
			'images' => array_values( array_map( 'knu_img', $files ) ),
		);
	}

	$desc_ru = trim( wp_strip_all_tags( (string) $post->post_content ) );

	return array(
		'id'             => (int) $id,
		'slug'           => (string) $post->post_name,
		'type'           => ( 'accessory' === $type ) ? 'accessory' : 'set',
		'title'          => ( $kg && '' !== $title_kg ) ? $title_kg : (string) $post->post_title,
		'desc'           => ( $kg && '' !== $desc_kg ) ? $desc_kg : $desc_ru,
		'composition'    => ( $kg && '' !== $comp_kg ) ? $comp_kg : $comp_ru,
		'badge'          => ( $kg && '' !== $badge_kg ) ? $badge_kg : $badge_ru,
		'price'          => (int) knu_product_meta( $id, 'price' ),
		'price_separate' => (int) knu_product_meta( $id, 'price_separate' ),
		'sizes'          => is_array( $sizes ) ? array_values( array_map( 'strval', $sizes ) ) : array(),
		'colors'         => $out_colors,
	);
}

/**
 * Localised products for the current language, sorted by menu_order.
 *
 * @param string|null $type 'set' | 'accessory' | null for all.
 * @return array[]
 */
function knu_get_products( $type = null ) {
	return knu_query_products( knu_lang(), $type );
}
