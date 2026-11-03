<?php
/**
 * Collection: the two sets, each presented like a product page.
 */

defined( 'ABSPATH' ) || exit;

$knu_sets = knu_get_products( 'set' );
?>
<section class="section collection" id="collection" aria-labelledby="collection-title">
	<div class="container">
		<header class="collection__head">
			<h2 id="collection-title" class="collection__mark">
				<span class="sr-only"><?php knu_e( 'collection.title' ); ?></span>
				<span aria-hidden="true">1925</span>
			</h2>
			<div class="collection__intro">
				<p class="collection__caption"><?php knu_e( 'collection.caption' ); ?></p>
				<p class="collection__lead"><?php knu_e( 'collection.lead' ); ?></p>
			</div>
		</header>

		<?php
		foreach ( $knu_sets as $knu_i => $knu_p ) :
			$knu_slug    = $knu_p['slug'];
			$knu_colors  = (array) $knu_p['colors'];
			$knu_first   = $knu_colors ? $knu_colors[0] : array( 'key' => '', 'name' => '', 'images' => array() );
			$knu_images  = array_values( (array) ( $knu_first['images'] ?? array() ) );
			$knu_sizes   = (array) $knu_p['sizes'];
			$knu_price   = (int) $knu_p['price'];
			$knu_sep     = (int) $knu_p['price_separate'];
			$knu_saving  = $knu_sep > $knu_price ? $knu_sep - $knu_price : 0;
			$knu_title_id = 'product-' . $knu_slug . '-title';
			$knu_thumbs  = max( 2, count( $knu_images ) );
			?>
			<article class="product<?php echo 1 === $knu_i % 2 ? ' product--flip' : ''; ?>" id="product-<?php echo esc_attr( $knu_slug ); ?>" data-product="<?php echo esc_attr( $knu_slug ); ?>" aria-labelledby="<?php echo esc_attr( $knu_title_id ); ?>">
				<div class="product__media gallery" data-gallery>
					<div class="gallery__main media" data-fallback="1925">
						<?php $knu_main_src = $knu_images[0] ?? ''; ?>
						<img data-gallery-main src="<?php echo esc_url( $knu_main_src ); ?>" width="1000" height="1500" alt="<?php echo esc_attr( $knu_p['title'] . ', ' . $knu_first['name'] ); ?>" loading="<?php echo 0 === $knu_i ? 'eager' : 'lazy'; ?>" decoding="async" onerror="this.classList.add('is-missing')"<?php echo $knu_main_src ? '' : ' class="is-missing"'; ?>>
						<?php if ( ! empty( $knu_p['badge'] ) ) : ?>
							<span class="badge badge--corner"><?php echo esc_html( $knu_p['badge'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="gallery__thumbs" role="group" aria-label="<?php echo esc_attr( knu_t( 'product.gallery' ) ); ?>">
						<?php for ( $knu_n = 0; $knu_n < $knu_thumbs; $knu_n++ ) : ?>
							<?php $knu_src = $knu_images[ $knu_n ] ?? ''; ?>
							<button class="gallery__thumb media" type="button" data-thumb="<?php echo (int) $knu_n; ?>" aria-pressed="<?php echo 0 === $knu_n ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( sprintf( knu_t( 'product.photo' ), $knu_n + 1 ) ); ?>">
								<img src="<?php echo esc_url( $knu_src ); ?>" width="1000" height="1500" alt="" loading="lazy" decoding="async" onerror="this.classList.add('is-missing')"<?php echo $knu_src ? '' : ' class="is-missing"'; ?>>
							</button>
						<?php endfor; ?>
					</div>
				</div>

				<div class="product__body">
					<h3 class="product__title" id="<?php echo esc_attr( $knu_title_id ); ?>"><?php echo esc_html( $knu_p['title'] ); ?></h3>

					<p class="price">
						<span class="sr-only"><?php knu_e( 'product.price' ); ?>:</span>
						<span class="price__now"><?php echo esc_html( knu_money( $knu_price ) ); ?></span>
						<?php if ( $knu_saving ) : ?>
							<s class="price__was"><span class="sr-only"><?php knu_e( 'product.separate' ); ?>:</span> <?php echo esc_html( knu_money( $knu_sep ) ); ?></s>
							<span class="badge"><span class="sr-only"><?php knu_e( 'product.save' ); ?>:</span>&minus;<?php echo esc_html( knu_money( $knu_saving ) ); ?></span>
						<?php endif; ?>
					</p>

					<?php if ( ! empty( $knu_p['desc'] ) ) : ?>
						<p class="product__desc"><?php echo esc_html( $knu_p['desc'] ); ?></p>
					<?php endif; ?>

					<form class="product__form" data-product-form data-slug="<?php echo esc_attr( $knu_slug ); ?>" data-has-sizes="<?php echo $knu_sizes ? '1' : '0'; ?>" novalidate>
						<?php if ( count( $knu_colors ) > 0 ) : ?>
							<fieldset class="option">
								<legend class="option__legend"><?php knu_e( 'product.color' ); ?>: <span class="option__value" data-color-name><?php echo esc_html( $knu_first['name'] ); ?></span></legend>
								<div class="swatches">
									<?php foreach ( $knu_colors as $knu_c_i => $knu_c ) : ?>
										<?php $knu_id = $knu_slug . '-color-' . $knu_c['key']; ?>
										<input class="sr-only swatch__input" type="radio" name="color" id="<?php echo esc_attr( $knu_id ); ?>" value="<?php echo esc_attr( $knu_c['key'] ); ?>" data-name="<?php echo esc_attr( $knu_c['name'] ); ?>" data-images="<?php echo esc_attr( wp_json_encode( array_values( (array) ( $knu_c['images'] ?? array() ) ) ) ); ?>"<?php checked( 0, $knu_c_i ); ?>>
										<label class="swatch" for="<?php echo esc_attr( $knu_id ); ?>" title="<?php echo esc_attr( $knu_c['name'] ); ?>">
											<span class="swatch__dot" style="--swatch: <?php echo esc_attr( $knu_c['hex'] ); ?>"></span>
											<span class="sr-only"><?php echo esc_html( $knu_c['name'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endif; ?>

						<?php if ( $knu_sizes ) : ?>
							<fieldset class="option option--sizes" aria-describedby="<?php echo esc_attr( $knu_slug ); ?>-hint">
								<legend class="option__legend option__legend--row">
									<span><?php knu_e( 'product.size' ); ?></span>
								</legend>
								<a class="option__link" href="#sizes"><?php knu_e( 'product.size_guide' ); ?></a>
								<div class="sizes">
									<?php foreach ( $knu_sizes as $knu_s ) : ?>
										<?php $knu_id = $knu_slug . '-size-' . strtolower( $knu_s ); ?>
										<input class="sr-only size__input" type="radio" name="size" id="<?php echo esc_attr( $knu_id ); ?>" value="<?php echo esc_attr( $knu_s ); ?>">
										<label class="size" for="<?php echo esc_attr( $knu_id ); ?>"><?php echo esc_html( $knu_s ); ?></label>
									<?php endforeach; ?>
								</div>
							</fieldset>
						<?php endif; ?>

						<div class="product__actions">
							<button class="btn btn--primary btn--block" type="submit" data-add><span data-add-label><?php knu_e( 'product.add' ); ?></span></button>
							<p class="product__hint" id="<?php echo esc_attr( $knu_slug ); ?>-hint" data-hint aria-live="polite"></p>
						</div>
					</form>

					<?php if ( ! empty( $knu_p['composition'] ) ) : ?>
						<details class="disclosure">
							<summary class="disclosure__summary"><?php knu_e( 'product.composition' ); ?></summary>
							<p class="disclosure__body"><?php echo esc_html( $knu_p['composition'] ); ?></p>
						</details>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>
