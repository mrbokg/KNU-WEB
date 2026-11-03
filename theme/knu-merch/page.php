<?php
/**
 * Inner page (legal documents): a single typographic text column.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$knu_page_id = get_the_ID();
	$knu_title   = function_exists( 'knu_page_title' ) ? knu_page_title( $knu_page_id ) : get_the_title( $knu_page_id );
	?>
	<main id="main" class="site-main page-doc" tabindex="-1" data-inert-root>
		<article class="container">
			<div class="page-doc__inner">
				<a class="page-doc__back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php knu_e( 'page.back' ); ?></a>
				<h1 class="page-doc__title"><?php echo esc_html( $knu_title ); ?></h1>
				<div class="prose">
					<?php
					if ( function_exists( 'knu_page_content' ) ) {
						echo knu_page_content( $knu_page_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized with wp_kses_post by the backend.
					} else {
						the_content();
					}
					?>
				</div>
			</div>
		</article>
	</main>
	<?php
endwhile;

get_footer();
