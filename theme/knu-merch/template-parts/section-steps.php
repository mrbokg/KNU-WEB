<?php
/**
 * How to order: three sequential steps.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="section steps" id="steps" aria-labelledby="steps-title">
	<div class="container">
		<header class="section-head">
			<h2 class="section-title" id="steps-title"><?php knu_e( 'steps.title' ); ?></h2>
		</header>
		<ol class="steps__list">
			<?php for ( $knu_n = 1; $knu_n <= 3; $knu_n++ ) : ?>
				<li class="step">
					<span class="step__num" aria-hidden="true"><?php echo (int) $knu_n; ?></span>
					<h3 class="step__title"><?php knu_e( 'steps.' . $knu_n . '.title' ); ?></h3>
					<p class="step__text"><?php knu_e( 'steps.' . $knu_n . '.text' ); ?></p>
				</li>
			<?php endfor; ?>
		</ol>
	</div>
</section>
