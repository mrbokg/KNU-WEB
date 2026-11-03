<?php
/**
 * Size chart XS–XXL. Girths are body measurements (ranges), lengths are garment measurements.
 */

defined( 'ABSPATH' ) || exit;

$knu_rows = array(
	array( 'XS', '82–86', '66', '59', '86–90' ),
	array( 'S', '86–92', '68', '61', '90–96' ),
	array( 'M', '92–98', '70', '62', '96–102' ),
	array( 'L', '98–104', '72', '63', '102–108' ),
	array( 'XL', '104–110', '74', '64', '108–114' ),
	array( 'XXL', '110–118', '76', '65', '114–122' ),
);
$knu_cols = array( 'sizes.size', 'sizes.chest', 'sizes.length', 'sizes.sleeve', 'sizes.hips' );
?>
<section class="section size-guide" id="sizes" aria-labelledby="sizes-title">
	<div class="container size-guide__grid">
		<header class="section-head section-head--side">
			<h2 class="section-title" id="sizes-title"><?php knu_e( 'sizes.title' ); ?></h2>
			<p class="section-lead"><?php knu_e( 'sizes.lead' ); ?></p>
		</header>
		<div class="table-wrap" tabindex="0" role="region" aria-labelledby="sizes-caption">
			<table class="size-table">
				<caption id="sizes-caption" class="sr-only"><?php knu_e( 'sizes.caption' ); ?></caption>
				<thead>
					<tr>
						<?php foreach ( $knu_cols as $knu_col ) : ?>
							<th scope="col"><?php knu_e( $knu_col ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $knu_rows as $knu_row ) : ?>
						<tr>
							<?php foreach ( $knu_row as $knu_j => $knu_cell ) : ?>
								<?php if ( 0 === $knu_j ) : ?>
									<th scope="row"><?php echo esc_html( $knu_cell ); ?></th>
								<?php else : ?>
									<td><?php echo esc_html( $knu_cell ); ?></td>
								<?php endif; ?>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
