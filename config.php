<?php

$oxid = array(
	'db_host' => 'dedi507.your-server.de',
	'db_user' => 'goschj_3',
	'db_password' => 'r2kSvKjt34BQX3xd',
	'db_name' => 'db_gosch_oxid',
	'oxid_user' => 'baltruschat@wortbildton.de',
	'oxid_password' => 'X4W=35+8',
	'oxid_shop' => 'oxbaseshop',

	// FTP Daten Oxid
	'ftp_host' => 'wortbildton.de',
	'ftp_user' => 'wortbild',
	'ftp_password' => 'X4W=35+8',
	'ftp_path' => '/gosch/',

	'delivery' => array(
		'0' => array(
			'oxidstandard'					   => '9907',
			'f7e26964789048cd408cec0f01127c5a' => '9907', 	// DHL Standart
			'5c6c20dd22e8d1e7300096624b309048' => '9807',	// Express
			'b1aee27e1e139011ac35d4173e353ca9' => '9906',	// Express Sa
			'e1b7fcbab7439a4ab5424b4d693c67b2' => '9900',	// DHL EU
			'f6ed7bfd30ec1855401e0554a8b042ec' => '9908'    // DHL Versandkostenfrei
		),
		'7' => array(
			'oxidstandard'					   => '9907',
			'f7e26964789048cd408cec0f01127c5a' => '9907', 	// DHL Standart
			'5c6c20dd22e8d1e7300096624b309048' => '9807',	// Express
			'b1aee27e1e139011ac35d4173e353ca9' => '9906',	// Express Sa
			'e1b7fcbab7439a4ab5424b4d693c67b2' => '9900',	// DHL EU
			'f6ed7bfd30ec1855401e0554a8b042ec' => '9908'    // DHL Versandkostenfrei
		),
		'19' => array(
			'oxidstandard'					   => '9919',
			'f7e26964789048cd408cec0f01127c5a' => '9919', 	// DHL Standart
			'5c6c20dd22e8d1e7300096624b309048' => '9819',	// Express
			'b1aee27e1e139011ac35d4173e353ca9' => '9909',	// Express Sa
			'e1b7fcbab7439a4ab5424b4d693c67b2' => '9900',	// DHL EU
			'f6ed7bfd30ec1855401e0554a8b042ec' => '9908'    // DHL Versandkostenfrei
		)	
	),

	'vouchers' => array(
		'61739677b8926324982d44436713508a'	=> array('type' => 'delivery', 'artnum' => '9908'), // Newsletter Versandkostenfrei
		'a96c1ff7b300c4c713a4c6388c76f802'	=> array('type' => 'discount', 'discountGroup' => '6000'), // Gutschein Neukunden 10%
		'075b0c58634542628832c9977d55b59d'	=> array('type' => 'discount', 'discountGroup' => '6000'), // Gutschein Newsletter 10%
		'41d2e46db38f050262795bfbe4ff051f'	=> array('type' => 'discount', 'discountGroup' => '6000'), // Gutschein Weihnachten Grußkarte 10%
		'416320be16f3d49171b222a6bd3006ed'  => array('type' => 'delivery_article', 'delivery' => '9908', 'discount_article' => '22903') // Guscheine Herzstiftung 12/2015
	),

	'payment' => array(
		'oxidpaypal' => 'PayPal',
		'oxidpayadvance' => 'Vorkasse',
		'oxidcreditcard' => 'Kreditkarte',
		'oxidinvoice' => 'Rechnung'
	)
);


$selectline = array(
	'db_host' => '10.120.1.210',
	//'db_host' => '\\\\HEINE-WAWI',
	'db_user' => 'sa',
	'db_password' => '%3u5AZ!#q7',
	'db_name' => 'SL_MHEINE3'
);
