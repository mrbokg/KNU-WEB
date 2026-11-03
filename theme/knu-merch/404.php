<?php
/**
 * 404: a quiet page in the site style with a way back to the landing.
 */

defined( 'ABSPATH' ) || exit;

// Localized document title (WordPress core says "Page not found" in the site locale).
add_filter(
	'document_title_parts',
	static function ( $parts ) {
		$parts['title'] = knu_t( '404.title' );
		return $parts;
	},
	20
);

get_header();
?>
<main id="main" class="site-main page-404" tabindex="-1" data-inert-root>
	<div class="container">
		<div class="page-404__inner">
			<p class="page-404__code" aria-hidden="true">404</p>
			<h1 class="page-404__title"><?php knu_e( '404.title' ); ?></h1>
			<p class="page-404__text"><?php knu_e( '404.text' ); ?></p>
			<div class="page-404__actions">
				<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php knu_e( '404.cta' ); ?></a>
				<a class="btn btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>#collection"><?php knu_e( '404.collection' ); ?></a>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();
