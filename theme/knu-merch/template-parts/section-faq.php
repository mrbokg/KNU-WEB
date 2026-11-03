<?php
/**
 * FAQ on native <details>.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="section faq" id="faq" aria-labelledby="faq-title">
	<div class="container faq__grid">
		<header class="section-head section-head--side">
			<h2 class="section-title" id="faq-title"><?php knu_e( 'faq.title' ); ?></h2>
		</header>
		<div class="faq__list">
			<?php for ( $knu_n = 1; $knu_n <= 6; $knu_n++ ) : ?>
				<details class="faq__item">
					<summary class="faq__q"><?php knu_e( 'faq.' . $knu_n . '.q' ); ?></summary>
					<p class="faq__a"><?php knu_e( 'faq.' . $knu_n . '.a' ); ?></p>
				</details>
			<?php endfor; ?>
		</div>
	</div>
</section>
