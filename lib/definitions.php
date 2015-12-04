<?php
	
	$oxid['img_path'] = '/public_html/gosch/out/pictures/';
	$oxid['get_order_status'] = 'ORDERFOLDER_NEW';
	$oxid['set_order_status']  = 'ORDERFOLDER_EXPORTED';
	$oxid['order_status_finish'] = 'ORDERFOLDER_FINISHED';
	$oxid['table_order']	   = 'oxorder';
	$oxid['table_orderarticles'] = 'oxorderarticles';
	$oxid['table_payments'] = 'oxpayments';
	$oxid['table_users'] = 'oxuser';
	$oxid['table_categories'] = 'oxcategories';
	$oxid['table_products'] = 'oxarticles';
	$oxid['table_product_extends'] = 'oxartextends';
	$oxid['table_product_to_category'] = 'oxobject2category';
	$oxid['table_product_xsel'] = 'oxobject2article';
	$oxid['table_country'] = 'oxcountry';
	$oxid['table_vouchers'] = 'oxvouchers';


	$selectline['table_categories'] = '[dbo].[GRUPPEN]';
	$selectline['table_products'] = '[dbo].[ART]';
	$selectline['table_products_lager'] = '[dbo].[ARTORTLAGER]';
	$selectline['table_products_sets'] = '[dbo].[ARTSET]';
	$selectline['table_products_tools'] = '[dbo].[ZUBEHOER]';
	$selectline['table_product_text'] = '[dbo].[TEXT]';
	$selectline['table_product_img'] = '[dbo].[BILD]';
	$selectline['table_product_xsel'] = '[dbo].[ZUBEHOER]';
	$selectline['table_orders'] = '[dbo].[BELEG]';
	$selectline['table_orders_position'] = '[dbo].[BELEGP]';
	$selectline['table_delivery_tracking'] = '[dbo].[PAKET]';
	
	$selectline['table_address'] = '[dbo].[Kunden]';
	$selectline['table_delivery_address'] = '[dbo].[ADRESS]';

	$selectline['prefix_delivery_address'] = 'QD';


	
	/* Filter für Categorien damit wir die GOSCh daten bekommen */
	$selectline['filter_categories_col'] = '[Zusatz]';
	$selectline['filter_categories_value'] = 'Gosch';

	$selectline['filter_products_col'] = '[FreiesKennzeichen1]';
	$selectline['filter_products_value'] = '1';

	$selectline['filter_order_invoice'] = 'R';

	$selectline['order_prefix'] = '2015_';

	// Steuerschlüssel
	$selectline['tax'] = array( 2 => '7', 3 => '19', 10 => '0', 11 => '0');
	$selectline['taxCodes'] = array('7' => '2', '19' => '3', '0' => '10');
	
	$selectline['limit'] = 10;

	$conf['lockfile'] = ABSPATH .'\lock.pid';
	$conf['updatefile'] = ABSPATH .'\update';
	$conf['logfile'] = ABSPATH .'\log';
	$conf['nextCutomer'] = ABSPATH .'\nextCustomer';

?>