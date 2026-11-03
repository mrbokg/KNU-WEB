<?php
/**
 * Fallback template: renders the landing page (front-page.php).
 *
 * If front-page.php is not available yet, outputs a minimal document
 * that still calls wp_head()/wp_footer() so enqueued assets load.
 *
 * @package knu-merch
 */

defined( 'ABSPATH' ) || exit;

$knu_front = locate_template( 'front-page.php' );

if ( $knu_front ) {
	require $knu_front;
	return;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="main">
	<h1><?php bloginfo( 'name' ); ?></h1>
	<p><?php bloginfo( 'description' ); ?></p>
</main>
<?php wp_footer(); ?>
</body>
</html>
