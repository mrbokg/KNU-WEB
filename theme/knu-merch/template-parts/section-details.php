<?php
/**
 * Details: four material facts with line icons.
 */

defined( 'ABSPATH' ) || exit;

$knu_details = array( 'cotton', 'density', 'embroidery', 'fit' );
?>
<section class="section details" id="details" aria-labelledby="details-title">
	<div class="container">
		<header class="section-head">
			<h2 class="section-title" id="details-title"><?php knu_e( 'details.title' ); ?></h2>
			<p class="section-lead"><?php knu_e( 'details.lead' ); ?></p>
		</header>
		<ul class="details__grid" role="list">
			<?php foreach ( $knu_details as $knu_d ) : ?>
				<li class="detail">
					<span class="detail__icon" aria-hidden="true"><?php knu_icon( $knu_d ); ?></span>
					<h3 class="detail__title"><?php knu_e( 'details.' . $knu_d . '.title' ); ?></h3>
					<p class="detail__text"><?php knu_e( 'details.' . $knu_d . '.text' ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
