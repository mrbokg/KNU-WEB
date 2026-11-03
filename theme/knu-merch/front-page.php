<?php
/**
 * One-page merch landing.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main" tabindex="-1" data-inert-root>
	<?php
	$knu_sections = array( 'hero', 'collection', 'accessories', 'details', 'sizes', 'steps', 'delivery', 'faq' );
	foreach ( $knu_sections as $knu_section ) {
		get_template_part( 'template-parts/section', $knu_section );
	}
	?>
</main>
<?php
get_footer();
