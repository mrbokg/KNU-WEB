<?php
/**
 * Seed data for the KNU merch catalogue.
 *
 * Used by inc/seed.php to create knu_product posts. After seeding, prices and
 * texts are edited in wp-admin; a seed version bump re-syncs only the
 * colors and images from this file.
 * Image values are file names inside assets/img/products/.
 */

defined( 'ABSPATH' ) || exit;

$knu_sizes = array( 'XS', 'S', 'M', 'L', 'XL', 'XXL' );

$knu_garment_colors = array(
	array(
		'key'  => 'grey',
		'hex'  => '#B9B9B6',
		'name' => array( 'ru' => 'Серый меланж', 'kg' => 'Боз меланж' ),
	),
	array(
		'key'  => 'navy',
		'hex'  => '#1B2A44',
		'name' => array( 'ru' => 'Тёмно-синий', 'kg' => 'Кочкул көк' ),
	),
);

$knu_set_composition = array(
	'ru' => '80% хлопок, 20% полиэстер. Футер с начёсом, 320 г/м². Логотип КНУ — вышивка. Стирка при 30 °C, наизнанку.',
	'kg' => '80% пахта, 20% полиэстер. Ичи жумшак футер, 320 г/м². КУУнун логотиби — сайма. 30 °C жууңуз, тескерисинен.',
);

return array(
	array(
		'slug'           => 'set-hoodie',
		'type'           => 'set',
		'order'          => 1,
		'price'          => 4900,
		'price_separate' => 5300,
		'badge'          => array( 'ru' => 'Хит', 'kg' => 'Хит' ),
		'title'          => array( 'ru' => 'Двойка «Худи + джоггеры»', 'kg' => 'Комплект «Худи + джоггер»' ),
		'desc'           => array(
			'ru' => 'Худи с капюшоном и джоггеры свободного кроя. Вышитый логотип КНУ на груди и надпись «КНУ · 1925» на штанине.',
			'kg' => 'Капюшону бар худи жана эркин бычылган джоггер. Төшүндө КУУнун сайма логотиби, шымында «КУУ · 1925» жазуусу.',
		),
		'composition'    => $knu_set_composition,
		'sizes'          => $knu_sizes,
		'colors'         => $knu_garment_colors,
		'images'         => array(
			'grey'  => array( 'set-hoodie-grey-1.webp', 'set-hoodie-grey-2.webp' ),
			'navy'  => array( 'set-hoodie-navy-1.webp', 'set-hoodie-navy-2.webp' ),
		),
	),
	array(
		'slug'           => 'set-sweatshirt',
		'type'           => 'set',
		'order'          => 2,
		'price'          => 4500,
		'price_separate' => 5000,
		'badge'          => array( 'ru' => '', 'kg' => '' ),
		'title'          => array( 'ru' => 'Двойка «Свитшот + джоггеры»', 'kg' => 'Комплект «Свитшот + джоггер»' ),
		'desc'           => array(
			'ru' => 'Классический свитшот и джоггеры. Спокойный силуэт на каждый день — на пары, в библиотеку и домой.',
			'kg' => 'Классикалык свитшот жана джоггер. Күн сайын кийүүгө ыңгайлуу — сабакка, китепканага жана үйгө.',
		),
		'composition'    => $knu_set_composition,
		'sizes'          => $knu_sizes,
		'colors'         => $knu_garment_colors,
		'images'         => array(
			'grey'  => array( 'set-sweatshirt-grey-1.webp', 'set-sweatshirt-grey-2.webp' ),
			'navy'  => array( 'set-sweatshirt-navy-1.webp', 'set-sweatshirt-navy-2.webp' ),
		),
	),
	array(
		'slug'           => 'cap',
		'type'           => 'accessory',
		'order'          => 3,
		'price'          => 900,
		'price_separate' => 0,
		'badge'          => array( 'ru' => '', 'kg' => '' ),
		'title'          => array( 'ru' => 'Кепка', 'kg' => 'Кепка' ),
		'desc'           => array(
			'ru' => 'Хлопковая кепка с вышитым логотипом, регулируемый ремешок.',
			'kg' => 'Сайма логотиби бар пахта кепка, тасмасы жөнгө салынат.',
		),
		'composition'    => array( 'ru' => '100% хлопок, one size.', 'kg' => '100% пахта, бир өлчөм.' ),
		'sizes'          => array(),
		'colors'         => array( $knu_garment_colors[1], $knu_garment_colors[0] ),
		'images'         => array(
			'navy'  => array( 'cap-navy-1.webp' ),
			'grey'  => array( 'cap-grey-1.webp' ),
		),
	),
	array(
		'slug'           => 'mug',
		'type'           => 'accessory',
		'order'          => 4,
		'price'          => 600,
		'price_separate' => 0,
		'badge'          => array( 'ru' => '', 'kg' => '' ),
		'title'          => array( 'ru' => 'Кружка', 'kg' => 'Чыны' ),
		'desc'           => array(
			'ru' => 'Керамическая кружка 330 мл с гербом КНУ — белая или тёмно-синяя.',
			'kg' => 'КУУнун гербу бар 330 мл керамикалык чыны — ак же кочкул көк.',
		),
		'composition'    => array( 'ru' => 'Керамика, 330 мл.', 'kg' => 'Керамика, 330 мл.' ),
		'sizes'          => array(),
		'colors'         => array(
			array(
				'key'  => 'white',
				'hex'  => '#FFFFFF',
				'name' => array( 'ru' => 'Белый', 'kg' => 'Ак' ),
			),
			array(
				'key'  => 'navy',
				'hex'  => '#1B2A44',
				'name' => array( 'ru' => 'Тёмно-синий', 'kg' => 'Кочкул көк' ),
			),
		),
		'images'         => array(
			'white' => array( 'mug-white-1.webp' ),
			'navy'  => array( 'mug-navy-1.webp' ),
		),
	),
	array(
		'slug'           => 'keychain',
		'type'           => 'accessory',
		'order'          => 5,
		'price'          => 300,
		'price_separate' => 0,
		'badge'          => array( 'ru' => '', 'kg' => '' ),
		'title'          => array( 'ru' => 'Брелок', 'kg' => 'Брелок' ),
		'desc'           => array(
			'ru' => 'Металлический брелок с эмалью в цветах КНУ.',
			'kg' => 'КУУнун түстөрүндөгү эмалдуу металл брелок.',
		),
		'composition'    => array( 'ru' => 'Металл, эмаль.', 'kg' => 'Металл, эмаль.' ),
		'sizes'          => array(),
		'colors'         => array(
			array(
				'key'  => 'blue',
				'hex'  => '#0067AC',
				'name' => array( 'ru' => 'Синий', 'kg' => 'Көк' ),
			),
		),
		'images'         => array(
			'blue' => array( 'keychain-blue-1.webp' ),
		),
	),
);
