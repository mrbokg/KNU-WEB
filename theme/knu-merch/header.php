<?php
/**
 * Site header: announce bar, sticky header with anchors, language switch, cart.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'knu_icon' ) ) {
	/**
	 * Print an inline SVG icon from assets/img/icons/ (theme-owned files only).
	 *
	 * @param string $name Icon file name without extension.
	 */
	function knu_icon( $name ) {
		$file = get_theme_file_path( 'assets/img/icons/' . sanitize_file_name( $name ) . '.svg' );
		if ( is_readable( $file ) ) {
			echo file_get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput -- theme-owned static SVG.
		}
	}
}

$knu_cur_lang = knu_lang();
$knu_announce = knu_setting( 'announce_' . $knu_cur_lang );
if ( '' === trim( (string) $knu_announce ) ) {
	$knu_announce = knu_t( 'announce' );
}
// On inner pages (legal pages) anchors must point back to the landing.
$knu_anchor_base = is_front_page() ? '' : esc_url( home_url( '/' ) );
$knu_nav         = array(
	'collection' => 'nav.collection',
	'sizes'      => 'nav.sizes',
	'delivery'   => 'nav.delivery',
	'faq'        => 'nav.faq',
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="theme-color" content="#ffffff">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'knu lang-' . $knu_cur_lang ); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php knu_e( 'skip' ); ?></a>

<div class="announce" data-inert-root>
	<p class="container announce__text"><?php echo esc_html( $knu_announce ); ?></p>
</div>

<header class="site-header" data-header data-inert-root>
	<div class="container site-header__bar">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( knu_t( 'brand.home' ) ); ?>">
			<?php
			// 96 px file shown at 36-40 px (sharp on 2x screens); fall back to the 512 px original.
			$knu_logo = file_exists( get_theme_file_path( 'assets/img/logo-96.png' ) )
				? array( 'assets/img/logo-96.png', 96 )
				: array( 'assets/img/logo.png', 512 );
			?>
			<span class="brand__mark">
				<img class="brand__logo" src="<?php echo esc_url( get_theme_file_uri( $knu_logo[0] ) ); ?>" width="<?php echo (int) $knu_logo[1]; ?>" height="<?php echo (int) $knu_logo[1]; ?>" alt="" decoding="async" onerror="this.classList.add('is-missing')">
			</span>
			<span class="brand__text">
				<span class="brand__name"><?php knu_e( 'brand.name' ); ?></span>
				<span class="brand__sub"><?php knu_e( 'brand.sub' ); ?></span>
			</span>
		</a>

		<nav class="site-nav" aria-label="<?php echo esc_attr( knu_t( 'nav.label' ) ); ?>">
			<ul class="site-nav__list">
				<?php foreach ( $knu_nav as $knu_anchor => $knu_key ) : ?>
					<li><a class="site-nav__link" href="<?php echo esc_attr( $knu_anchor_base ); ?>#<?php echo esc_attr( $knu_anchor ); ?>"><?php knu_e( $knu_key ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="site-header__tools">
			<div class="lang-switch" role="group" aria-label="<?php echo esc_attr( knu_t( 'header.lang' ) ); ?>">
				<a class="lang-switch__link" href="<?php echo esc_url( knu_lang_url( 'kg' ) ); ?>" hreflang="ky" lang="ky"<?php echo 'kg' === $knu_cur_lang ? ' aria-current="true"' : ''; ?>>KG</a>
				<a class="lang-switch__link" href="<?php echo esc_url( knu_lang_url( 'ru' ) ); ?>" hreflang="ru" lang="ru"<?php echo 'ru' === $knu_cur_lang ? ' aria-current="true"' : ''; ?>>RU</a>
			</div>
			<button class="cart-btn" type="button" data-cart-open aria-controls="cart-drawer" aria-haspopup="dialog" aria-expanded="false" aria-label="<?php echo esc_attr( knu_t( 'header.cart' ) ); ?>" data-label="<?php echo esc_attr( knu_t( 'header.cart' ) ); ?>">
				<svg class="cart-btn__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M5 8h14l-1 12H6L5 8Z" stroke-linejoin="round"/><path d="M9 10V7a3 3 0 0 1 6 0v3" stroke-linecap="round"/></svg>
				<span class="cart-btn__count" data-cart-count hidden>0</span>
			</button>
		</div>
	</div>
</header>
