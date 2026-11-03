<?php
/**
 * Accessories: compact cards; color swatches only when a product has more than one color.
 */

defined( 'ABSPATH' ) || exit;

$knu_items = knu_get_products( 'accessory' );
if ( ! $knu_items ) {
	return;
}
?>
<section class="section accessories" id="accessories" aria-labelledby="accessories-title">
	<div class="container">
		<header class="section-head">
			<h2 class="section-title" id="accessories-title"><?php knu_e( 'accessories.title' ); ?></h2>
			<p class="section-lead"><?php knu_e( 'accessories.lead' ); ?></p>
		</header>

		<ul class="acc-grid" role="list">
			<?php
			foreach ( $knu_items as $knu_p ) :
				$knu_slug   = $knu_p['slug'];
				$knu_colors = (array) $knu_p['colors'];
				$knu_first  = $knu_colors ? $knu_colors[0] : array( 'key' => '', 'name' => '', 'images' => array() );
				$knu_images = array_values( (array) ( $knu_first['images'] ?? array() ) );
				$knu_src    = $knu_images[0] ?? '';
				$knu_tid    = 'product-' . $knu_slug . '-title';
				?>
				<li class="acc" id="product-<?php echo esc_attr( $knu_slug ); ?>" data-product="<?php echo esc_attr( $knu_slug ); ?>">
					<div class="acc__media media" data-fallback="1925">
						<img data-gallery-main src="<?php echo esc_url( $knu_src ); ?>" width="1200" height="1500" alt="<?php echo esc_attr( trim( $knu_p['title'] . ', ' . $knu_first['name'], ', ' ) ); ?>" loading="lazy" decoding="async" onerror="this.classList.add('is-missing')"<?php echo $knu_src ? '' : ' class="is-missing"'; ?>>
						<?php if ( ! empty( $knu_p['badge'] ) ) : ?>
							<span class="badge badge--corner"><?php echo esc_html( $knu_p['badge'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="acc__body">
						<div class="acc__top">
							<h3 class="acc__title" id="<?php echo esc_attr( $knu_tid ); ?>"><?php echo esc_html( $knu_p['title'] ); ?></h3>
							<p class="acc__price"><span class="sr-only"><?php knu_e( 'product.price' ); ?>:</span> <?php echo esc_html( knu_money( (int) $knu_p['price'] ) ); ?></p>
						</div>
						<?php if ( ! empty( $knu_p['desc'] ) ) : ?>
							<p class="acc__desc"><?php echo esc_html( $knu_p['desc'] ); ?></p>
						<?php endif; ?>

						<form class="acc__form" data-product-form data-slug="<?php echo esc_attr( $knu_slug ); ?>" data-has-sizes="0" aria-labelledby="<?php echo esc_attr( $knu_tid ); ?>" novalidate>
							<?php if ( count( $knu_colors ) > 1 ) : ?>
								<fieldset class="option option--compact">
									<legend class="option__legend"><?php knu_e( 'product.color' ); ?>: <span class="option__value" data-color-name><?php echo esc_html( $knu_first['name'] ); ?></span></legend>
									<div class="swatches">
										<?php foreach ( $knu_colors as $knu_c_i => $knu_c ) : ?>
											<?php $knu_id = $knu_slug . '-color-' . $knu_c['key']; ?>
											<input class="sr-only swatch__input" type="radio" name="color" id="<?php echo esc_attr( $knu_id ); ?>" value="<?php echo esc_attr( $knu_c['key'] ); ?>" data-name="<?php echo esc_attr( $knu_c['name'] ); ?>" data-images="<?php echo esc_attr( wp_json_encode( array_values( (array) ( $knu_c['images'] ?? array() ) ) ) ); ?>"<?php checked( 0, $knu_c_i ); ?>>
											<label class="swatch swatch--sm" for="<?php echo esc_attr( $knu_id ); ?>" title="<?php echo esc_attr( $knu_c['name'] ); ?>">
												<span class="swatch__dot" style="--swatch: <?php echo esc_attr( $knu_c['hex'] ); ?>"></span>
												<span class="sr-only"><?php echo esc_html( $knu_c['name'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</fieldset>
							<?php else : ?>
								<input type="hidden" name="color" value="<?php echo esc_attr( $knu_first['key'] ); ?>">
							<?php endif; ?>
							<button class="btn btn--outline btn--block" type="submit" data-add><span data-add-label><?php knu_e( 'product.add' ); ?></span></button>
						</form>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
