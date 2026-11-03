<?php
/**
 * Theme settings: Appearance → «Настройки КНУ».
 *
 * Stored in option knu_settings (array).
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default settings.
 *
 * @return array<string,string>
 */
function knu_settings_defaults() {
	return array(
		'whatsapp'       => '996700000000',
		'telegram'       => 'knu_merch',
		'announce_ru'    => 'Коллекция 1925 — к 100-летию КНУ · Самовывоз в КНУ бесплатно',
		'announce_kg'    => '«1925» коллекциясы — КУУнун 100 жылдыгына · КУУдан өзүңүз алып кетсеңиз акысыз',
		'seller_name'    => 'Окуу долбоору — демо сатуучу',
		'seller_inn'     => '00000000000000 (демо)',
		'seller_address' => 'Бишкек ш., Фрунзе к., 547',
		'seller_phone'   => '+996 700 000 000',
		'seller_email'   => 'merch@knu.kg',
		'qr_recipient'   => 'KNU Merch (демо)',
	);
}

/**
 * Read a setting.
 *
 * @param string $key whatsapp | telegram | announce_ru | announce_kg | seller_name | seller_inn |
 *                    seller_address | seller_phone | seller_email | qr_recipient.
 * @return string
 */
function knu_setting( $key ) {
	$defaults = knu_settings_defaults();
	$saved    = get_option( 'knu_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	// Default only when the key was never saved: an empty saved value means "cleared".
	$value = array_key_exists( $key, $saved ) ? $saved[ $key ] : ( $defaults[ $key ] ?? '' );
	$value = is_scalar( $value ) ? (string) $value : '';

	if ( 'whatsapp' === $key ) {
		return preg_replace( '/\D+/', '', $value );
	}
	if ( 'telegram' === $key ) {
		return knu_sanitize_telegram( $value );
	}
	// The demo seller name is Kyrgyz; show its Russian twin on Russian pages
	// until the admin enters a real name.
	if ( 'seller_name' === $key && $value === $defaults['seller_name'] && function_exists( 'knu_lang' ) && 'ru' === knu_lang() ) {
		return 'Учебный проект — демо-продавец';
	}
	return $value;
}

/**
 * Telegram nick without @, only [A-Za-z0-9_].
 *
 * @param string $value Raw value.
 * @return string
 */
function knu_sanitize_telegram( $value ) {
	$value = trim( (string) $value );
	$value = preg_replace( '~^(https?://)?(t\.me/|telegram\.me/)~i', '', $value );
	$value = ltrim( $value, '@' );
	return substr( preg_replace( '/[^A-Za-z0-9_]/', '', $value ), 0, 64 );
}

/**
 * Sanitize the settings array.
 *
 * @param mixed $input Raw input.
 * @return array<string,string>
 */
function knu_settings_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	$out   = array();

	$out['whatsapp']    = isset( $input['whatsapp'] ) && is_scalar( $input['whatsapp'] ) ? substr( preg_replace( '/\D+/', '', (string) $input['whatsapp'] ), 0, 15 ) : '';
	$out['telegram']    = isset( $input['telegram'] ) && is_scalar( $input['telegram'] ) ? knu_sanitize_telegram( (string) $input['telegram'] ) : '';
	$out['announce_ru'] = isset( $input['announce_ru'] ) && is_scalar( $input['announce_ru'] ) ? sanitize_text_field( (string) $input['announce_ru'] ) : '';
	$out['announce_kg'] = isset( $input['announce_kg'] ) && is_scalar( $input['announce_kg'] ) ? sanitize_text_field( (string) $input['announce_kg'] ) : '';

	foreach ( array( 'seller_name', 'seller_inn', 'seller_address', 'seller_phone', 'qr_recipient' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : '';
	}
	$out['seller_email'] = isset( $input['seller_email'] ) && is_scalar( $input['seller_email'] ) ? sanitize_email( (string) $input['seller_email'] ) : '';

	return $out;
}

/**
 * Register setting, section and fields.
 */
function knu_settings_register() {
	register_setting(
		'knu_settings_group',
		'knu_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'knu_settings_sanitize',
			'default'           => knu_settings_defaults(),
		)
	);

	add_settings_section( 'knu_contacts', 'Контакты', '__return_false', 'knu-settings' );
	add_settings_section( 'knu_announce', 'Плашка-анонс', '__return_false', 'knu-settings' );
	add_settings_section( 'knu_seller', 'Продавец (футер, закон о защите прав потребителей)', '__return_false', 'knu-settings' );
	add_settings_section( 'knu_payment', 'Оплата по QR', '__return_false', 'knu-settings' );

	$fields = array(
		'whatsapp'       => array( 'knu_contacts', 'WhatsApp (только цифры)', 'Например: 996700000000' ),
		'telegram'       => array( 'knu_contacts', 'Telegram (ник без @)', 'Например: knu_merch' ),
		'announce_ru'    => array( 'knu_announce', 'Текст плашки (RU)', '' ),
		'announce_kg'    => array( 'knu_announce', 'Текст плашки (KG)', '' ),
		'seller_name'    => array( 'knu_seller', 'Наименование продавца', '' ),
		'seller_inn'     => array( 'knu_seller', 'ИНН', '' ),
		'seller_address' => array( 'knu_seller', 'Адрес', '' ),
		'seller_phone'   => array( 'knu_seller', 'Телефон', '' ),
		'seller_email'   => array( 'knu_seller', 'E-mail', '' ),
		'qr_recipient'   => array( 'knu_payment', 'Получатель на экране QR', 'Например: KNU Merch (демо)' ),
	);

	foreach ( $fields as $key => $field ) {
		add_settings_field(
			'knu_' . $key,
			$field[1],
			'knu_settings_field',
			'knu-settings',
			$field[0],
			array(
				'key'       => $key,
				'hint'      => $field[2],
				'label_for' => 'knu_' . $key,
			)
		);
	}
}
add_action( 'admin_init', 'knu_settings_register' );

/**
 * Render a text field.
 *
 * @param array $args Field args.
 */
function knu_settings_field( $args ) {
	$key   = $args['key'];
	$value = knu_setting( $key );
	$class = ( 0 === strpos( $key, 'announce_' ) || 'seller_name' === $key || 'seller_address' === $key ) ? 'large-text' : 'regular-text';
	printf(
		'<input type="text" id="%1$s" name="knu_settings[%2$s]" value="%3$s" class="%4$s">',
		esc_attr( 'knu_' . $key ),
		esc_attr( $key ),
		esc_attr( $value ),
		esc_attr( $class )
	);
	if ( ! empty( $args['hint'] ) ) {
		echo '<p class="description">' . esc_html( $args['hint'] ) . '</p>';
	}
}

/**
 * Add the page under Appearance.
 */
function knu_settings_menu() {
	add_theme_page( 'Настройки КНУ', 'Настройки КНУ', 'manage_options', 'knu-settings', 'knu_settings_page' );
}
add_action( 'admin_menu', 'knu_settings_menu' );

/**
 * Render the settings page.
 */
function knu_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( 'Настройки КНУ' ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'knu_settings_group' );
			do_settings_sections( 'knu-settings' );
			submit_button( 'Сохранить' );
			?>
		</form>
	</div>
	<?php
}
