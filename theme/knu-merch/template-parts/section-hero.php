<?php
/**
 * Hero: headline, lead, primary actions, collection image.
 */

defined( 'ABSPATH' ) || exit;

$knu_sets      = knu_get_products( 'set' );
$knu_first     = $knu_sets ? $knu_sets[0]['slug'] : '';
$knu_min_price = 0;
foreach ( $knu_sets as $knu_p ) {
	$knu_min_price = $knu_min_price ? min( $knu_min_price, (int) $knu_p['price'] ) : (int) $knu_p['price'];
}
$knu_title_lines = preg_split( '/(?<=\.)\s+/u', trim( knu_t( 'hero.title' ) ), -1, PREG_SPLIT_NO_EMPTY );
?>
<section class="hero" aria-labelledby="hero-title">
	<div class="container hero__grid">
		<div class="hero__text">
			<h1 id="hero-title" class="hero__title">
				<?php foreach ( $knu_title_lines as $knu_line ) : ?>
					<span class="hero__line"><?php echo esc_html( $knu_line ); ?></span>
				<?php endforeach; ?>
			</h1>
			<p class="hero__lead"><?php knu_e( 'hero.lead' ); ?></p>
			<div class="hero__actions">
				<a class="btn btn--primary" href="#<?php echo esc_attr( $knu_first ? 'product-' . $knu_first : 'collection' ); ?>"><?php knu_e( 'hero.cta' ); ?></a>
				<a class="btn btn--ghost" href="#collection"><?php knu_e( 'hero.cta2' ); ?></a>
			</div>
			<p class="hero__note">
				<?php if ( $knu_min_price ) : ?>
					<span class="hero__price"><?php echo esc_html( sprintf( knu_t( 'hero.from' ), knu_money( $knu_min_price ) ) ); ?></span>
				<?php endif; ?>
				<span><?php knu_e( 'hero.note' ); ?></span>
			</p>
		</div>
		<figure class="hero__media media media--hero" data-fallback="1925">
			<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/hero.webp' ) ); ?>" width="1600" height="1200" alt="<?php echo esc_attr( knu_t( 'hero.img_alt' ) ); ?>" fetchpriority="high" decoding="async" onerror="this.classList.add('is-missing')">
		</figure>
	</div>
</section>
