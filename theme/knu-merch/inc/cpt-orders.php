<?php
/**
 * Orders: CPT knu_order (created only via REST), admin list and detail screen.
 *
 * Meta: _knu_number, _knu_name, _knu_phone, _knu_delivery, _knu_address,
 * _knu_comment, _knu_items (array), _knu_subtotal, _knu_delivery_fee,
 * _knu_total, _knu_status, _knu_lang, _knu_payment (qr|cash),
 * _knu_consent_at (ISO date), _knu_token (32 hex).
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order statuses (admin labels, RU).
 *
 * @return array<string,string>
 */
function knu_order_statuses() {
	return array(
		'new'              => 'Новый',
		'awaiting_payment' => 'Ожидает оплаты',
		'payment_check'    => 'Оплата на проверке',
		'confirmed'        => 'Подтверждён',
		'done'             => 'Выдан',
		'cancelled'        => 'Отменён',
	);
}

/**
 * Payment methods (admin labels).
 *
 * @return array<string,string>
 */
function knu_payment_labels() {
	return array(
		'qr'   => 'QR (ELQR) онлайн',
		'cash' => 'При получении',
	);
}

/**
 * Delivery methods (admin labels).
 *
 * @return array<string,string>
 */
function knu_delivery_labels() {
	return array(
		'pickup'  => 'Самовывоз (КНУ)',
		'bishkek' => 'Доставка по Бишкеку',
		'regions' => 'Доставка по регионам',
	);
}

/**
 * Register the CPT.
 */
function knu_register_order_cpt() {
	register_post_type(
		'knu_order',
		array(
			'labels'          => array(
				'name'               => 'Заказы',
				'singular_name'      => 'Заказ',
				'menu_name'          => 'Заказы',
				'edit_item'          => 'Заказ',
				'view_item'          => 'Просмотр заказа',
				'search_items'       => 'Найти заказ',
				'not_found'          => 'Заказов пока нет',
				'not_found_in_trash' => 'В корзине заказов нет',
				'all_items'          => 'Все заказы',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_position'   => 26,
			'menu_icon'       => 'dashicons-clipboard',
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
			'hierarchical'    => false,
			'supports'        => false,
			'rewrite'         => false,
			'query_var'       => false,
		)
	);
}
add_action( 'init', 'knu_register_order_cpt' );

/**
 * Read one order meta value.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Key without the _knu_ prefix.
 * @return mixed
 */
function knu_order_meta( $post_id, $key ) {
	return get_post_meta( $post_id, '_knu_' . $key, true );
}

/**
 * Status label.
 *
 * @param string $status Status key.
 * @return string
 */
function knu_order_status_label( $status ) {
	$statuses = knu_order_statuses();
	return $statuses[ $status ] ?? $statuses['new'];
}

/**
 * List columns.
 *
 * @param array $columns Columns.
 * @return array
 */
function knu_order_columns( $columns ) {
	return array(
		'cb'         => $columns['cb'] ?? '<input type="checkbox">',
		'knu_number' => 'Номер',
		'knu_client' => 'Клиент',
		'knu_phone'  => 'Телефон',
		'knu_total'   => 'Сумма',
		'knu_payment' => 'Оплата',
		'knu_status'  => 'Статус',
		'date'       => 'Дата',
	);
}
add_filter( 'manage_knu_order_posts_columns', 'knu_order_columns' );

/**
 * Column values.
 *
 * @param string $column  Column.
 * @param int    $post_id Post ID.
 */
function knu_order_column_value( $column, $post_id ) {
	switch ( $column ) {
		case 'knu_number':
			$number = (string) knu_order_meta( $post_id, 'number' );
			printf(
				'<strong><a class="row-title" href="%1$s">%2$s</a></strong>',
				esc_url( (string) get_edit_post_link( $post_id ) ),
				esc_html( $number ? $number : get_the_title( $post_id ) )
			);
			break;
		case 'knu_client':
			echo esc_html( (string) knu_order_meta( $post_id, 'name' ) );
			break;
		case 'knu_phone':
			$phone = (string) knu_order_meta( $post_id, 'phone' );
			$tel   = preg_replace( '/[^\d+]/', '', $phone );
			printf( '<a href="%1$s">%2$s</a>', esc_url( 'tel:' . $tel ), esc_html( $phone ) );
			break;
		case 'knu_total':
			echo esc_html( knu_money_lang( (int) knu_order_meta( $post_id, 'total' ), 'ru' ) );
			break;
		case 'knu_payment':
			$payment  = (string) knu_order_meta( $post_id, 'payment' );
			$payments = knu_payment_labels();
			echo esc_html( $payments[ $payment ] ?? '—' );
			break;
		case 'knu_status':
			$status = (string) knu_order_meta( $post_id, 'status' );
			printf(
				'<span class="knu-status knu-status--%1$s">%2$s</span>',
				esc_attr( sanitize_key( $status ) ),
				esc_html( knu_order_status_label( $status ) )
			);
			break;
	}
}
add_action( 'manage_knu_order_posts_custom_column', 'knu_order_column_value', 10, 2 );

/**
 * Number is the primary column (row actions go there).
 *
 * @param string $default Default column.
 * @param string $screen  Screen ID.
 * @return string
 */
function knu_order_primary_column( $default, $screen ) {
	return ( 'edit-knu_order' === $screen ) ? 'knu_number' : $default;
}
add_filter( 'list_table_primary_column', 'knu_order_primary_column', 10, 2 );

/**
 * Remove Quick Edit for orders.
 *
 * @param array   $actions Row actions.
 * @param WP_Post $post    Post.
 * @return array
 */
function knu_order_row_actions( $actions, $post ) {
	if ( 'knu_order' === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'], $actions['view'] );
	}
	return $actions;
}
add_filter( 'post_row_actions', 'knu_order_row_actions', 10, 2 );

/**
 * Small admin CSS for status pills.
 */
function knu_order_admin_css() {
	$screen = get_current_screen();
	if ( ! $screen || 'knu_order' !== $screen->post_type ) {
		return;
	}
	?>
	<style>
		.knu-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;background:#f0f0f1;color:#50575e}
		.knu-status--new{background:#fecc00;color:#111}
		.knu-status--awaiting_payment{background:#fff3cd;color:#664d03}
		.knu-status--payment_check{background:#cfe2ff;color:#052c65}
		.knu-status--confirmed{background:#0067ac;color:#fff}
		.knu-status--done{background:#d1e7dd;color:#0f5132}
		.knu-status--cancelled{background:#f8d7da;color:#842029}
		.knu-order-table td,.knu-order-table th{padding:6px 10px;text-align:left}
		.knu-order-table{border-collapse:collapse;width:100%}
		.knu-order-table tr+tr td{border-top:1px solid #e5e5e2}
		.column-knu_number{width:110px}.column-knu_status{width:150px}.column-knu_payment{width:140px}.column-knu_total{width:110px}
	</style>
	<?php
}
add_action( 'admin_head', 'knu_order_admin_css' );

/**
 * Register meta boxes.
 */
function knu_order_add_meta_boxes() {
	add_meta_box( 'knu_order_details', 'Детали заказа', 'knu_order_details_box', 'knu_order', 'normal', 'high' );
	add_meta_box( 'knu_order_status', 'Статус заказа', 'knu_order_status_box', 'knu_order', 'side', 'high' );
}
add_action( 'add_meta_boxes_knu_order', 'knu_order_add_meta_boxes' );

/**
 * Read-only details box.
 *
 * @param WP_Post $post Post.
 */
function knu_order_details_box( $post ) {
	$id        = $post->ID;
	$items     = knu_order_meta( $id, 'items' );
	$items     = is_array( $items ) ? $items : array();
	$delivery  = (string) knu_order_meta( $id, 'delivery' );
	$labels    = knu_delivery_labels();
	$phone     = (string) knu_order_meta( $id, 'phone' );
	$tel       = preg_replace( '/[^\d+]/', '', $phone );
	$wa_digits = preg_replace( '/\D+/', '', $phone );
	$lang      = (string) knu_order_meta( $id, 'lang' );
	$payment   = (string) knu_order_meta( $id, 'payment' );
	$payments  = knu_payment_labels();
	$consent   = (string) knu_order_meta( $id, 'consent_at' );
	?>
	<h3 style="margin-top:0;"><?php echo esc_html( (string) knu_order_meta( $id, 'number' ) ); ?></h3>
	<table class="knu-order-table" role="presentation">
		<tr><th scope="row">Клиент</th><td><?php echo esc_html( (string) knu_order_meta( $id, 'name' ) ); ?></td></tr>
		<tr>
			<th scope="row">Телефон</th>
			<td>
				<a href="<?php echo esc_url( 'tel:' . $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php if ( $wa_digits ) : ?>
					· <a href="<?php echo esc_url( 'https://wa.me/' . $wa_digits ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
				<?php endif; ?>
			</td>
		</tr>
		<tr><th scope="row">Получение</th><td><?php echo esc_html( $labels[ $delivery ] ?? $delivery ); ?></td></tr>
		<?php if ( 'pickup' !== $delivery ) : ?>
			<tr><th scope="row">Адрес</th><td><?php echo esc_html( (string) knu_order_meta( $id, 'address' ) ); ?></td></tr>
		<?php endif; ?>
		<tr><th scope="row">Оплата</th><td><?php echo esc_html( $payments[ $payment ] ?? '—' ); ?></td></tr>
		<tr><th scope="row">Согласие с офертой</th><td><?php echo esc_html( $consent ? mysql2date( 'd.m.Y H:i', $consent ) : '—' ); ?></td></tr>
		<tr><th scope="row">Комментарий</th><td><?php echo nl2br( esc_html( (string) knu_order_meta( $id, 'comment' ) ) ); ?></td></tr>
		<tr><th scope="row">Язык</th><td><?php echo esc_html( 'kg' === $lang ? 'Кыргызский' : 'Русский' ); ?></td></tr>
		<tr><th scope="row">Дата</th><td><?php echo esc_html( get_the_date( 'd.m.Y H:i', $post ) ); ?></td></tr>
	</table>

	<h4>Состав заказа</h4>
	<table class="knu-order-table widefat striped">
		<thead>
			<tr><th>Товар</th><th>Цвет</th><th>Размер</th><th>Кол-во</th><th>Цена</th><th>Сумма</th></tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></td>
					<td><?php echo esc_html( (string) ( $item['color'] ?? '' ) ); ?></td>
					<td><?php echo esc_html( '' !== (string) ( $item['size'] ?? '' ) ? (string) $item['size'] : '—' ); ?></td>
					<td><?php echo esc_html( (string) (int) ( $item['qty'] ?? 0 ) ); ?></td>
					<td><?php echo esc_html( knu_money_lang( (int) ( $item['price'] ?? 0 ), 'ru' ) ); ?></td>
					<td><?php echo esc_html( knu_money_lang( (int) ( $item['sum'] ?? 0 ), 'ru' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr><td colspan="5">Товары</td><td><?php echo esc_html( knu_money_lang( (int) knu_order_meta( $id, 'subtotal' ), 'ru' ) ); ?></td></tr>
			<tr><td colspan="5">Доставка</td><td><?php echo esc_html( knu_money_lang( (int) knu_order_meta( $id, 'delivery_fee' ), 'ru' ) ); ?></td></tr>
			<tr><th colspan="5">Итого</th><th><?php echo esc_html( knu_money_lang( (int) knu_order_meta( $id, 'total' ), 'ru' ) ); ?></th></tr>
		</tfoot>
	</table>
	<?php
}

/**
 * Status select box.
 *
 * @param WP_Post $post Post.
 */
function knu_order_status_box( $post ) {
	wp_nonce_field( 'knu_order_status_save', 'knu_order_status_nonce' );
	$current = (string) knu_order_meta( $post->ID, 'status' );
	?>
	<label for="knu_status" class="screen-reader-text">Статус</label>
	<select id="knu_status" name="knu_status" style="width:100%;">
		<?php foreach ( knu_order_statuses() as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<p class="description">Сохраните кнопкой «Обновить» (Update) справа.</p>
	<?php
}

/**
 * Save status.
 *
 * @param int $post_id Post ID.
 */
function knu_order_save_status( $post_id ) {
	if ( ! isset( $_POST['knu_order_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['knu_order_status_nonce'] ) ), 'knu_order_status_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$status = isset( $_POST['knu_status'] ) ? sanitize_key( wp_unslash( $_POST['knu_status'] ) ) : '';
	if ( array_key_exists( $status, knu_order_statuses() ) ) {
		update_post_meta( $post_id, '_knu_status', $status );
	}
}
add_action( 'save_post_knu_order', 'knu_order_save_status' );

/**
 * Number of orders that need attention: "new" and "payment_check".
 *
 * @return int
 */
function knu_count_new_orders() {
	$query = new WP_Query(
		array(
			'post_type'      => 'knu_order',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_knu_status',
					'value'   => array( 'new', 'payment_check' ),
					'compare' => 'IN',
				),
			),
		)
	);
	return (int) $query->found_posts;
}

/**
 * Count bubble on the «Заказы» menu item.
 */
function knu_order_menu_bubble() {
	global $menu;
	if ( ! is_array( $menu ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$count = knu_count_new_orders();
	if ( $count < 1 ) {
		return;
	}
	foreach ( $menu as $index => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=knu_order' === $item[2] ) {
			$menu[ $index ][0] .= sprintf( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				' <span class="awaiting-mod count-%1$d"><span class="pending-count">%2$s</span></span>',
				(int) $count,
				esc_html( number_format_i18n( $count ) )
			);
			break;
		}
	}
}
add_action( 'admin_menu', 'knu_order_menu_bubble', 99 );
