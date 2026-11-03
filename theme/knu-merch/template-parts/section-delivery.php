<?php
/**
 * Delivery and payment. Fees mirror window.KNU.delivery (pickup 0, Bishkek 150, regions 300).
 */

defined( 'ABSPATH' ) || exit;

$knu_options = array(
	'pickup'  => 0,
	'bishkek' => 150,
	'regions' => 300,
);
?>
<section class="section delivery" id="delivery" aria-labelledby="delivery-title">
	<div class="container delivery__grid">
		<header class="section-head section-head--side">
			<h2 class="section-title" id="delivery-title"><?php knu_e( 'delivery.title' ); ?></h2>
			<p class="section-lead"><?php knu_e( 'delivery.lead' ); ?></p>
		</header>
		<div class="delivery__body">
			<ul class="price-list" role="list">
				<?php foreach ( $knu_options as $knu_key => $knu_fee ) : ?>
					<li class="price-list__row">
						<div class="price-list__info">
							<h3 class="price-list__title"><?php knu_e( 'delivery.' . $knu_key . '.title' ); ?></h3>
							<p class="price-list__text"><?php knu_e( 'delivery.' . $knu_key . '.text' ); ?></p>
						</div>
						<p class="price-list__price" data-fee="<?php echo esc_attr( $knu_key ); ?>">
							<?php echo esc_html( $knu_fee ? knu_money( $knu_fee ) : knu_t( 'delivery.free' ) ); ?>
						</p>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="pay">
				<h3 class="pay__title"><?php knu_e( 'delivery.pay.title' ); ?></h3>
				<ul class="pay__methods" role="list">
					<li class="chip chip--strong">ELQR</li>
					<li class="chip">MBank</li>
					<li class="chip">O!Деньги</li>
					<li class="chip">Bakai</li>
					<li class="chip">Optima</li>
					<li class="chip"><?php knu_e( 'delivery.pay.cash' ); ?></li>
				</ul>
				<p class="pay__text"><?php knu_e( 'delivery.pay.text' ); ?></p>
			</div>
		</div>
	</div>
</section>
