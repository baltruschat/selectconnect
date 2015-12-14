<?php

class Orders extends Cartridge
{

	private $customerInt = 410000;
	private $customerIntMax = 510000;

	/**
	 * Importiert Bestellungen in Selectline, wird von der index.php manuell aufgerufen
	 * @return array
	 */
	public function import(){
		global $oxid;

		$orders = $oxid['db']->get_results("SELECT * FROM ". $oxid['table_order'] ." WHERE OXFOLDER  = '".$oxid['get_order_status']."' ORDER BY OXORDERNR");
		if($orders){
			foreach($orders as $order){
				$order_products = $oxid['db']->get_results("SELECT * FROM ".$oxid['table_orderarticles']." WHERE OXORDERID = '".$order->OXID."' ");
				$order->products = $order_products;

				$order_payment = $oxid['db']->get_row("SELECT * FROM " . $oxid['table_payments'] . " WHERE ('".$order->OXPAYMENTTYPE."' = OXID)");
				$order->payment = $order_payment;
				$this->set_order_to_selectline($order);
				$this->items++;
				$oxid['db']->query("UPDATE ".$oxid['table_order']." SET OXFOLDER = '".$oxid['set_orders_status']."', OXEXPORT = 1 WHERE `OXID` = '".$order->OXID."' ");
			}
		}
		return array('items' => $this->items, 'imports' => $this->imports, 'fails' => $this->imports_fail);
	}

	/**
	 * Ist für den Abgleich der Bestellungen im Cronjob.
	 */
	public function update(){
		global $selectline, $oxid, $ftp;
		$this->set_log( 'Cronjob: '. date('d.m.Y H:i:s') ."\r\n" );

		try{
			$ftp = new Ftp;
			$ftp->connect($oxid['ftp_host']);
			$ftp->login($oxid['ftp_user'],$oxid['ftp_password']);
			$ftp->close();
		}catch(Exception $e){
			$this->set_log('SelectConnect konnte keine Verbindung zum FTP-Server herstellen!');
			exit();
		}

		if(!oxid_userlogin()){
			$this->set_log('SelectConnect konnte sich nicht bei Oxid einloggen!');
			exit();
		}

		if(!$this->is_current_update_process()):

			$this->set_log( "Cronjob wurde gestartet \r\n");
			$this->set_lock_file();
			$last_update = $this->get_last_update();

			$this->set_log( "Bestellungen werden in Selectline importiert \r\n");
			$orders = $oxid['db']->get_results("SELECT * FROM ". $oxid['table_order'] ." WHERE OXFOLDER  = '".$oxid['get_order_status']."' ORDER BY OXORDERNR");

			if($orders){
				foreach($orders as $order){
					$order_products = $oxid['db']->get_results("SELECT * FROM ".$oxid['table_orderarticles']." WHERE OXORDERID = '".$order->OXID."' ");
					$order->products = $order_products;
					$order_payment = $oxid['db']->get_row("SELECT * FROM " . $oxid['table_payments'] . " WHERE ('".$order->OXPAYMENTTYPE."' = OXID)");
					$order->payment = $order_payment;
					$this->set_order_to_selectline($order, true);
					$oxid['db']->query("UPDATE ".$oxid['table_order']." SET OXFOLDER = '".$oxid['set_order_status']."', OXEXPORT = 1 WHERE `OXID` = '".$order->OXID."' ");
				}
			}

			$this->set_log( "Bestellungstatus wird abgeglichen \r\n");
			$orders = $selectline['db']->get_results("SELECT * FROM ".$selectline['table_orders']." WHERE [Belegtyp] = '".$selectline['filter_order_invoice']."' AND [BearbeitetAm] >= CONVERT(datetime, '".$last_update->format('d.m.Y H:i:s')."',104)");

			if($orders){
				foreach($orders as $order){
					$tracking_id = $this->get_tracking_id_from_selectline($order);
					$updateArray = array(
							'OXBILLNR' => $order->Belegnummer,
							'OXBILLDATE' => $order->Datum->format('Y-m-d'),
							'OXSENDDATE' => $order->Datum->format('Y-m-d H:i:s'),
							'OXFOLDER' => $oxid['order_status_finish']
						);
					if($tracking_id !== NULL){
						$updateArray['OXTRACKCODE'] = $tracking_id;
					}
					//print_r($this->format_update_query($oxid['table_order'], $updateArray, array('OXORDERNR' => str_replace('OX', '', $order->IhrAuftrag))));
					$oxid['db']->query($this->format_update_query($oxid['table_order'], $updateArray, array('OXORDERNR' => str_replace('OX', '', $order->IhrAuftrag))));
					$this->set_log( "Bestellung ".$order->IhrAuftrag."/".$order->Belegnummer." - Status: FINISHED - Tracking: ".$tracking_id." \r\n");
				}
			}

			$this->set_last_update();
			$this->remove_lock_file();
			$this->set_log( "Cron wurde beendet \r\n");
			$this->set_log( "\r\n##################################\r\n\r\n");
		else:
			print_r('Error: Es läuft bereits Update-Prozess.');
		endif;
	}

	/**
	 * Einzelne Bestellungen wird in die Selectline-Db geschrieben. Dabei wird überprüft ob der Kunde schon in Selecline exsitiert oder nicht
	 * @param object $order DB-Object aus Oxid
	 * @param boolean $echo print
	 */
	private function set_order_to_selectline($order, $echo = false){
		global $selectline, $oxid;

		$customer = $this->exsits_bill_customer_in_selectline($order);
		if($customer == NULL){
			$order->customer = $this->create_bill_customer_in_selectline($order);
		}else{
			$order->customer = $customer;
			$this->update_bill_customer_in_selectline($order);
		}


		$zusatz = $this->mssql_escape($order->OXBILLCOMPANY);
		if($zusatz == ''){
			$zusatz = $this->mssql_escape($order->OXBILLADDINFO);
		}else{
			$zusatz = $zusatz .' - '. $this->mssql_escape($order->OXBILLADDINFO);
		}

		$queryData = array(
			'[Belegtyp]' => 'D', // Muss evtl. individuell angepasst werden
			'[Belegnummer]' => $selectline['order_prefix'].$order->OXORDERNR,
			'[Datum]' => 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)',
			'[Adressnummer]' => $order->customer,
			'[Name]' => $this->mssql_escape($order->OXBILLLNAME),
			'[Anrede]' => ($order->OXBILLSAL == 'MR') ? 'Herrn' : 'Frau',
			'[Vorname]' => $this->mssql_escape($order->OXBILLFNAME),
			'[Strasse]' => $this->mssql_escape($order->OXBILLSTREET) ." ". $order->OXBILLSTREETNR,
			'[Land]' => $oxid['db']->get_var("SELECT OXISOALPHA2 FROM ".$oxid['table_country']." WHERE OXID = '".$order->OXBILLCOUNTRYID."'"),
			'[Zusatz]' => ($zusatz == '') ? NULL : $zusatz,
			'[Plz]' => $order->OXBILLZIP,
			'[Ort]' =>$this->mssql_escape($order->OXBILLCITY),
			'[Preisgruppe]' => 3,
			'[PreisTyp]' => 'B',
			'[Zahlungsbedingung]' => $this->format_payment_to_selectline($order->payment->OXID),
			'[Zahlungsziel]' => $this->format_payment_termn_to_selectline($order->payment->OXID),
			'[Waehrungscode]' => 'EUR',
			'[Waehrungsfaktor]' => 1,
			'[Brutto]' => ($order->OXTOTALBRUTSUM + $order->OXDELCOST),
			'[Netto]' => ($order->OXTOTALNETSUM + $order->OXDELCOST),
			'[Steuer]' => ($order->OXARTVATPRICE1 + $order->OXARTVATPRICE2),
			'[FremdwaehrungBrutto]' => ($order->OXTOTALBRUTSUM + $order->OXDELCOST),
			'[FremdwaehrungNetto]' => ($order->OXTOTALNETSUM + $order->OXDELCOST),
			'[FremdwaehrungSteuer]' => ($order->OXARTVATPRICE1 + $order->OXARTVATPRICE2),
			'[WIRArt]' => 'B',
			'[Liefertermin]' => 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)',
			'[Konto]' => $order->customer,
			'[Status]' => 0,
			//'[RechAdresse]' => $order->customer,
			'[EuroNetto]' => ($order->OXTOTALBRUTSUM + $order->OXDELCOST),
			'[EuroSteuer]' => ($order->OXARTVATPRICE1 + $order->OXARTVATPRICE2),
			'[EuroBrutto]' => ($order->OXTOTALNETSUM + $order->OXDELCOST),
			'[Verkehrszweig]' => 3, // Lassen wir bei drei
			'[Gewicht]' => $oxid['db']->get_var("SELECT SUM(OXWEIGHT) AS WEIGHT FROM ".$oxid['table_orderarticles']." WHERE OXORDERID = '".$order->OXID."' "),
			'[Orignummer]' => $order->customer,
			'[Standort]' => 1,
			'[IhrZeichen]' => (strtotime($order->OXDELDATE) == 0) ? 'kein Datum angegeben' : date('d.m.Y', strtotime($order->OXDELDATE)),
			'[AngelegtAm]' => 'CONVERT(datetime, \''.date('d.m.Y H:i:s',strtotime($order->OXORDERDATE)).'\',104)',
			'[AngelegtVon]' => 'TS',
			'[RefAdresse]' => $order->customer,
			'[IhrAuftrag]' => 'OX'.$order->OXORDERNR,
			'[FreierText1]' => $oxid['payment'][$order->OXPAYMENTTYPE],
			'[Lager]'	=> '3'
		);
		$arr = array('%s','%s','%b','%i','%s','%s','%s','%s','%s','%s','%s','%s','%i','%s','%i','%i','%s','%i','%f','%f','%f','%f','%f','%f','%s','%b','%i','%i','%f','%f','%f','%i','%f','%i','%i','%s', '%b', '%s', '%i', '%s', '%s', '%s');

		if($order->OXDELSTREET){
			$order->deliveryCustomer = $this->create_delivery_customer_in_selectline($order);
		}

		if($this->exsits_order_in_selectline($order) === NULL){

			$selectline['db']->query($this->format_insert_query($selectline['table_orders'], $queryData, $arr));
			if($this->exsits_order_in_selectline($order) ){

				$this->set_log( "Bestellung ".$selectline['order_prefix'].$order->OXORDERNR." wird in die DB geschrieben \r\n");

				// Mitteilungstext wird in die DB geschrieben
				if($order->OXREMARK){
					$textData = array(
						'[BLOBKEY]' => $selectline['prefix_delivery_address'].utf8_decode('°').$selectline['order_prefix'].$order->OXORDERNR,
						'[TEXT]' => $this->mssql_escape($order->OXREMARK)
					);
					$iarr = array('%s', '%s');
					$selectline['db']->query($this->format_insert_query($selectline['table_product_text'], $textData, $iarr));
				}

				// Artikel der Bestellung werden in die DB geschieben
				$this->set_products_to_order_to_selectline($order);

				$this->imports++;
				if($echo){
					$this->set_log( "\r\n\r\n");
				}
				return;
			}
		}
		$this->imports_fail++;
		return;
	}

	/**
	 * Artikelpositionen werden überprüft und dann mit add_product_to_selectline in die DB geschrieben.
	 * @param object $order DB-Object aus Oxid
	 */
	private function set_products_to_order_to_selectline($order){
		global $selectline, $oxid, $taxCodes;

		$pos = 1;
		$HauptKennung = false;
		$removeDefaultDelivery = false;

		if(is_array($order->products)){

			// Beleg Positionen
			foreach($order->products as $product):

				$Kennung = $this->guid36();
				if($pos == 1){
					$HauptKennung = $Kennung;
					$this->add_product_to_selectline($product->OXARTNUM, $product, $order, $pos, $Kennung, false, false);
				}else{
					$this->add_product_to_selectline($product->OXARTNUM, $product, $order, $pos, $Kennung, $HauptKennung, false);
				}

				$pos++;
			endforeach;

			// Überprüfung ob ein Gutscheincode eingesetzt wurde
			if($voucher = $this->get_voucher_artnum($order)){

				if($voucher['type'] == 'delivery'){
					$removeDefaultDelivery = true;
					if(isset($voucher['discount_article'])){
						$this->add_product_to_selectline($voucher['discount_article'], false, $order, $pos, $this->guid36(), $HauptKennung, false, true);
					}
					$this->add_product_to_selectline($voucher['delivery'], false, $order, $pos, $this->guid36(), $HauptKennung, true);
				}else if($voucher['type'] == 'discount'){
					$this->add_discount_to_order_selctline($order, $voucher);
				}elseif($voucher['type'] == 'delivery_article'){
					$removeDefaultDelivery = true;
					$this->add_product_to_selectline($voucher['discount_article'], false, $order, $pos, $this->guid36(), $HauptKennung, false, true);
					$this->add_product_to_selectline($voucher['delivery'], false, $order, $pos, $this->guid36(), $HauptKennung, true);
				}elseif($voucher['type'] == 'article'){
						$this->add_product_to_selectline($voucher['discount_article'], false, $order, $pos, $this->guid36(), $HauptKennung, false, true);
				}
			}

			// Versandmethode als BelegPosition in die DB schreiben
			if($removeDefaultDelivery == false)
				$this->add_product_to_selectline(0, false, $order, $pos, $this->guid36(), $HauptKennung, true);


		}
	}


	/**
	 * Artikelpositionen werden in Selectline geschrieben
	 * @param int $artnum       	 Artikelnummer
	 * @param object $product      DB-Object von Oxid-Article
	 * @param object $order 			 DB-Object aus Oxid
	 * @param string $pos          Artikelpositions Nummer
	 * @param guid36 $Kennung      Interne Kennunsnummer in Selectline
	 * @param guid36 $HauptKennung Interne Kennunsnummer in Selectline
	 * @param boolean $delivery    Ist die Artikelposition die Versandmethode
	 * @param boolean $addonArticle Ist die Position ein Addon-Artikel
	 */
	private function add_product_to_selectline($artnum, $product, $order, $pos, $Kennung, $HauptKennung = false, $delivery = false, $addonArticle = false){
		global $oxid, $selectline, $taxCodes;

		if($delivery){

			if($artnum == 0){
				// Versand wenn kein Gutschein-Code eingelöst wurde, abhänig von der MWST
				$productSelectline = Products::get_product_from_artnum_selectline( $oxid['delivery'][$order->OXARTVAT1][$order->OXDELTYPE]);
				$productSelectlineLager = Products::get_product_lager_from_artnum_selectline($oxid['delivery'][$order->OXARTVAT1][$order->OXDELTYPE]);
			}else{
				// Versand wenn Gutschein-Code eingelöst wurde
				$productSelectline = Products::get_product_from_artnum_selectline($artnum);
				$productSelectlineLager = Products::get_product_lager_from_artnum_selectline($artnum);
			}
			$Zeilentyp = 'E';
		}else{
			$productSelectline = Products::get_product_from_artnum_selectline($artnum);
			$productSelectlineLager = Products::get_product_lager_from_artnum_selectline($artnum);

			if($productSelectline->Stueckliste == 'H'){
				$Zeilentyp = 'H';
			}else{
				$Zeilentyp = 'A';
			}
		}

		if($productSelectline === NULL){
			$id = ($artnum == 0) ? $product->OXARTNUM : $artnum;
			$msg = "Artikel ".$id." konnte nicht in die DB geschieben werden, da der Artikel nicht in Selectline gefunden wurde \r\n";
			error_handler(E_ERROR, $msg, 'order.php', '251','');
		}

		$this->set_log( "Beleg Artikel: ".$productSelectline->Artikelnummer .' / '. $productSelectline->Bezeichnung ." wird in die DB geschrieben \r\n");


		$insertArray = array(
			'Belegtyp' => 'D',
			'Belegnummer' => $selectline['order_prefix'].$order->OXORDERNR,
			'Posnummer' => $pos,
			'Postext' => $pos,
			'Zeilentyp' => $Zeilentyp,
			'Menge' => ($delivery || $product->OXAMOUNT == 0) ? 1 : $product->OXAMOUNT,
			'Eingabemenge' => ($delivery || $product->OXAMOUNT == 0) ? 1 : $product->OXAMOUNT,
			'EditMenge' => ($delivery || $product->OXAMOUNT == 0) ? 1 : $product->OXAMOUNT,
			'Artikelnummer' => $productSelectline->Artikelnummer,
			'Bezeichnung' => $this->mssql_escape($productSelectline->Bezeichnung),
			'Zusatz' => $productSelectline->Zusatz,
			'Mengeneinheit' => $productSelectline->Mengeneinheit,
			'Gewicht' => ($productSelectline->Gewicht == '') ? 0 : $productSelectline->Gewicht,
			'Einzelpreis' => ($delivery || $addonArticle) ? floatval(str_replace(',','.',$productSelectline->_BRUTTO)) : $product->OXPRICE,
			'Preiseinheit' => $productSelectline->Preiseinheit,
			'Gesamtpreis' => ($delivery || $addonArticle) ? floatval(str_replace(',','.',$productSelectline->_BRUTTO)) : $product->OXBRUTPRICE,
			'Steuercode' => $productSelectline->SSVerkauf,
			'Steuerprozent' => $selectline['tax'][$productSelectline->SSVerkauf],
			'Konto' => $productSelectline->KontoVerkauf,
			'AdressNr' => $order->customer,
			'RefAdresse' => $order->customer,
			'Datum' => 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)',
			'AngelegtAm' => 'GETDATE()',
			'AngelegtVon' => 'TS',
			'Lagerartikel' =>  $productSelectline->Lagerartikel,
			'SerieCharge' =>  $productSelectline->SerieCharge,
			'Kennung' => $Kennung,
			'Lager' => ($delivery || $addonArticle) ? '' : $productSelectlineLager->Lager,
			'Stueckliste' => $productSelectline->Stueckliste,
			'Termin' => (strtotime($order->OXDELDATE) == 0) ? 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)' : 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXDELDATE)).'\',104)'
		);


		$arr = array('%s','%s','%i','%i','%s','%i','%i','%i','%i','%s','%s','%s','%f','%f','%i','%f','%i','%i','%i','%i','%i','%b','%b','%s','%i','%s','%s', '%s', '%h', '%b');

		if($HauptKennung){
			//$insertArray['Hauptkennung'] = $HauptKennung;
			//$arr[] = '%s';
		}


		$selectline['db']->query($this->format_insert_query($selectline['table_orders_position'], $insertArray, $arr));


		// Artikelsets to Selectline
		if($Zeilentyp == 'H'){
			$this->add_product_sets_to_selectline($product, $order, $pos, $Kennung);
		}else{
			$this->add_product_tools_to_selectline($product, $order, $pos, $Kennung);
		}
	}

	/**
	 * [add_product_sets_to_selectline description]
	 * @param object $product      DB-Object von Oxid-Article
	 * @param object $order 			 DB-Object aus Oxid
	 * @param string $pos          Artikelpositions Nummer
	 * @param guid36 $Kennung      Interne Kennunsnummer in Selectline
	 */
	private function add_product_sets_to_selectline($product, $order, $pos, $Kennung){
		global $selectline, $oxid, $taxCodes;

		$productSets = $selectline['db']->get_results("SELECT * FROM ".$selectline['table_products_sets']." WHERE [Artikelnummer] = '".$product->OXARTNUM."'");
		$productSelectlineLager = Products::get_product_lager_from_artnum_selectline($product->OXARTNUM);

		if($productSets){
			$i = 0;
			foreach($productSets as $set){
				$productSetData = Products::get_product_from_artnum_selectline($set->SetArtikelnummer);
				$i++;


				$insertArray = array(
					'Belegtyp' => 'D',
					'Belegnummer' => $selectline['order_prefix'].$order->OXORDERNR,
					'Posnummer' => $pos,
					'Postext' => $pos.'.'.$i,
					'Zeilentyp' => 'G',
					'Menge' => ($product->OXAMOUNT * $set->Menge),
					'Eingabemenge' => $set->Menge,
					'EditMenge' => $set->Menge,
					'Artikelnummer' => $set->SetArtikelnummer,
					'Bezeichnung' => $productSetData->Bezeichnung,
					'Zusatz' => $productSetData->Zusatz,
					'Mengeneinheit' => $productSetData->Mengeneinheit,
					'Gewicht' => ($productSetData->Gewicht == '') ? 0 : $productSetData->Gewicht,
					'Einzelpreis' => str_replace(',','.',($product->OXBRUTPRICE / $set->Menge)),
					'Preiseinheit' => $productSetData->Preiseinheit,
					'Gesamtpreis' => str_replace(',','.',$product->OXBRUTPRICE),
					'Steuercode' => $productSetData->SSVerkauf,
					'Steuerprozent' => $selectline['tax'][$productSetData->SSVerkauf],
					'Konto' => $productSetData->KontoVerkauf,
					'AdressNr' => $order->customer,
					'RefAdresse' => $order->customer,
					'Datum' => 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)',
					'AngelegtAm' => 'GETDATE()',
					'AngelegtVon' => 'TS',
					'Lagerartikel' =>  $productSetData->Lagerartikel,
					'SerieCharge' =>  $productSetData->SerieCharge,
					'Kennung' => $this->guid36(),
					'Lager' => $productSelectlineLager->Lager,
					'Stueckliste' => $productSetData->Stueckliste,
					'Termin' => (strtotime($order->OXDELDATE) == 0) ? 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)' : 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXDELDATE)).'\',104)',
					'Hauptkennung' => $Kennung
				);
				$arr = array('%s','%s','%i','%i','%s','%i','%i','%i','%i','%s','%s','%s','%f','%f','%i','%f','%i','%i','%i','%i','%i','%b','%b','%s','%i','%s','%s', '%s', '%s', '%b','%s');
				$selectline['db']->query($this->format_insert_query($selectline['table_orders_position'], $insertArray, $arr));
			}
		}

	}


	/**
	 * [add_product_tools_to_selectline description]
	 * @param [type] $product [description]
	 * @param [type] $order   [description]
	 * @param [type] $pos     [description]
	 * @param [type] $Kennung [description]
	 */
	private function add_product_tools_to_selectline($product, $order, $pos, $Kennung){
		global $selectline, $oxid, $taxCodes;

		if(isset($product->OXARTNUM)){

			$productSets = $selectline['db']->get_results("SELECT * FROM ".$selectline['table_products_tools']." WHERE [ArtArtikelnummer] = '".$product->OXARTNUM."'");
			$productSelectlineLager = Products::get_product_lager_from_artnum_selectline($product->OXARTNUM);

			//print_r($productSets);

			if($productSets){
				$i = 0;
				foreach($productSets as $set){

					$productSetData = Products::get_product_from_artnum_selectline($set->Artikelnummer);
					$i++;

					$insertArray = array(
						'Belegtyp' => 'D',
						'Belegnummer' => $selectline['order_prefix'].$order->OXORDERNR,
						'Posnummer' => $pos,
						'Postext' => $pos.'.'.$i,
						'Zeilentyp' => 'A',
						'Menge' => ($product->OXAMOUNT * $set->Mengenformel),
						'Eingabemenge' => $set->Mengenformel,
						'EditMenge' => $set->Mengenformel,
						'Artikelnummer' => $set->Artikelnummer,
						'Bezeichnung' => $productSetData->Bezeichnung,
						'Zusatz' => $productSetData->Zusatz,
						'Mengeneinheit' => $productSetData->Mengeneinheit,
						'Gewicht' => ($productSetData->Gewicht == '') ? 0 : $productSetData->Gewicht,
						'Einzelpreis' => str_replace(',','.',($product->OXBRUTPRICE / $set->Mengenformel)),
						'Preiseinheit' => $productSetData->Preiseinheit,
						'Gesamtpreis' => str_replace(',','.',$product->OXBRUTPRICE),
						'Steuercode' => $productSetData->SSVerkauf,
						'Steuerprozent' => $selectline['tax'][$productSetData->SSVerkauf],
						'Konto' => $productSetData->KontoVerkauf,
						'AdressNr' => $order->customer,
						'RefAdresse' => $order->customer,
						'Datum' => 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)',
						'AngelegtAm' => 'GETDATE()',
						'AngelegtVon' => 'TS',
						'Lagerartikel' =>  $productSetData->Lagerartikel,
						'SerieCharge' =>  $productSetData->SerieCharge,
						'Kennung' => $this->guid36(),
						'Lager' => $productSelectlineLager->Lager,
						'Stueckliste' => $productSetData->Stueckliste,
						'Termin' => (strtotime($order->OXDELDATE) == 0) ? 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXORDERDATE)).'\',104)' : 'CONVERT(datetime, \''.date('d.m.Y',strtotime($order->OXDELDATE)).'\',104)',
						'Hauptkennung' => $Kennung
					);
					$arr = array('%s','%s','%i','%i','%s','%i','%i','%i','%s','%s','%s','%s','%f','%f','%i','%f','%i','%i','%i','%i','%i','%b','%b','%s','%i','%s','%s', '%s', '%s', '%b','%s');
					$selectline['db']->query($this->format_insert_query($selectline['table_orders_position'], $insertArray, $arr));
				}
			}
		}
	}

	/**
	 * [get_tracking_id_from_selectline description]
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	private function get_tracking_id_from_selectline($order){
		global $selectline;
		return $selectline['db']->get_var("SELECT [Paketnummer] FROM ".$selectline['table_delivery_tracking']." WHERE [Belegnummer] = '".$order->Belegnummer."' ");
	}

	/**
	 * [exsits_bill_customer_in_selectline description]
	 * @param  [type] $customer [description]
	 * @return [type]           [description]
	 */
	private function exsits_bill_customer_in_selectline($customer){
		global $selectline, $oxid;
		return $selectline['db']->get_var("SELECT [Nummer]  FROM ".$selectline['table_address']." WHERE LOWER([Email]) = '".strtolower($customer->OXBILLEMAIL)."' AND [_HGC] = 'Gosch'");
	}
	/**
	 * [exsits_order_in_selectline description]
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	private function exsits_order_in_selectline($order){
		global $selectline, $oxid;
		return $selectline['db']->get_var("SELECT [BELEG_ID]  FROM ".$selectline['table_orders']." WHERE [Belegnummer] = '".$selectline['order_prefix'].$order->OXORDERNR."'");
	}

	/**
	 * [create_bill_customer_in_selectline description]
	 * @param  [type] $customer [description]
	 * @return [type]           [description]
	 */
	private function create_bill_customer_in_selectline($customer){
		global $selectline, $oxid;

		/*
		- Nummerkreis muss noch angepasst werden
		- Aktualiesierung der Daten wenn vorhanden
		*/
		$id = $this->get_next_customer_id_in_selectline();

		$zusatz = $this->mssql_escape($customer->OXBILLCOMPANY);
		if($zusatz == ''){
			$zusatz = $this->mssql_escape($customer->OXBILLADDINFO);
		}else{
			$zusatz = $zusatz .' - '. $this->mssql_escape($customer->OXBILLADDINFO);
		}

		$insertArray = array(
			'Nummer' => $id,
			'Fibukonto' => $id,
			'Zahlungsbedingung' => 71,
			'Preisgruppe' => 3,
			'PreisTyp' => 'B',
			'Waehrung' => 'EUR',
			'WirArt' => 'B',
			'AngelegtAm' => 'GETDATE()',
			'BearbeitetAm' => 'GETDATE()',
			'AngelegtVon' => 'TS',
			'BearbeitetVon' => 'TS',
			'ShopPasswort' => 'asd',
			'ShopAktiv' => 1,
			'Verkehrszweig' => 3,
			'Anrede' => ($customer->OXBILLSAL == 'MR') ? 'Herrn' : 'Frau',
			'Briefanrede' => ($customer->OXBILLSAL == 'MR') ? 'Herrn' : 'Frau',
			'Vorname' => $this->mssql_escape($customer->OXBILLFNAME),
			'Name' => $this->mssql_escape($customer->OXBILLLNAME),
			'Zusatz' => ($zusatz == '') ? NULL : $zusatz,
			'Strasse' => $this->mssql_escape($customer->OXBILLSTREET) .' '. $customer->OXBILLSTREETNR,
			'Ort' =>$this->mssql_escape( $customer->OXBILLCITY),
			'PLZ' =>  $customer->OXBILLZIP,
			'Land' => $oxid['db']->get_var("SELECT OXISOALPHA2 FROM ".$oxid['table_country']." WHERE OXID = '".$customer->OXBILLCOUNTRYID."'"),
			//'Land' => 'D',
			'Telefon1' => ($customer->OXBILLFON == '') ? NULL : $customer->OXBILLFON,
			'Email' => $customer->OXBILLEMAIL,
			'_HGC' => 'Gosch',
			'FreiesKennzeichen2' => '1', // Steht dafür das der Kunde ein ShopKunde ist
			'[_KEINFREMDWER]' => 0,
			'[_KEINWERB]' => 0,
			'[_KEINDATEN]' => 0,
			'[_INTERESSENT]' => 0,
			'[_LIEFERSCH]' => 0

			//'FreiesKennzeichen3' => '1' // Wenn der Kunde einen Newsletter haben will
		);

		$format = array('%i','%i','%i','%i','%s','%s','%s','%b','%b','%s','%s','%s','%i','%i','%s','%s','%s','%s','%s','%n','%s','%s','%s','%s','%n','%s','%s','%i','%i','%i','%i','%i', '%i');
		$selectline['db']->query($this->format_insert_query($selectline['table_address'], $insertArray, $format));
		$this->set_log( "Kunde wurde neu angelegt \r\n");
		return $this->exsits_bill_customer_in_selectline($customer);
	}


	/**
	 * [update_bill_customer_in_selectline description]
	 * @param  [type] $customer [description]
	 * @return [type]           [description]
	 */
	private function update_bill_customer_in_selectline($customer){
		global $selectline, $oxid;

		$zusatz = $this->mssql_escape($customer->OXBILLCOMPANY);
		if($zusatz == ''){
			$zusatz = $this->mssql_escape($customer->OXBILLADDINFO);
		}else{
			$zusatz = $zusatz .' - '. $this->mssql_escape($customer->OXBILLADDINFO);
		}

		$updateArray = array(
			'[Anrede]' => ($customer->OXBILLSAL == 'MR') ? 'Herrn' : 'Frau',
			'[Briefanrede]' => ($customer->OXBILLSAL == 'MR') ? 'Herrn' : 'Frau',
			'[Vorname]' => $this->mssql_escape($customer->OXBILLFNAME),
			'[Name]' => $this->mssql_escape($customer->OXBILLLNAME),
			'[Zusatz]' => ($zusatz == '') ? NULL : $zusatz,
			'[Strasse]' => $this->mssql_escape($customer->OXBILLSTREET) .' '. $customer->OXBILLSTREETNR,
			'[Ort]' => $this->mssql_escape($customer->OXBILLCITY),
			'[PLZ]' => $customer->OXBILLZIP,
			'[Land]' => $oxid['db']->get_var("SELECT OXISOALPHA2 FROM ".$oxid['table_country']." WHERE OXID = '".$customer->OXBILLCOUNTRYID."'"),
			'[Telefon1]' => ($customer->OXBILLFON == '') ? NULL : $customer->OXBILLFON,
			'[Email]' => $customer->OXBILLEMAIL,
		);

		$query = $this->format_update_query($selectline['table_address'], $updateArray, array('[Nummer]' => $customer->customer));

		$data = $selectline['db']->query_format($query, array($customer->customer,NULL,NULL,SQLSRV_SQLTYPE_INT));
		$this->set_log( "Kunde wurde überschrieben \r\n");
	}



	/**
	 * [create_delivery_customer_in_selectline description]
	 * @param  [type] $customer [description]
	 * @return [type]           [description]
	 */
	private function create_delivery_customer_in_selectline($customer){
		global $selectline, $oxid;

		/*
		- Nummerkreis muss noch angepasst werden
		- Aktualiesierung der Daten wenn vorhanden
		*/

		$id = $this->get_next_customer_id_in_selectline();
		$id++;
		$zusatz = $this->mssql_escape($customer->OXDELCOMPANY);
		if($zusatz == ''){
			$zusatz = $this->mssql_escape($customer->OXDELADDINFO);
		}else{
			$zusatz = $zusatz .' - '. $this->mssql_escape($customer->OXDELADDINFO);
		}


		$insertArray = array(
			//'Nummer' => $id,
			'Adresstyp' => $selectline['prefix_delivery_address'].$selectline['order_prefix'].$customer->OXORDERNR,
			'Anrede' => ($customer->OXDELSAL == 'MR') ? 'Herr' : 'Frau',
			'Briefanrede' => ($customer->OXDELSAL == 'MR') ? 'Herr' : 'Frau',
			'Vorname' => $customer->OXDELFNAME,
			'Name' => $customer->OXDELLNAME,
			'Zusatz' => ($zusatz == '') ? NULL : $zusatz,
			'Strasse' => $customer->OXDELSTREET .' '. $customer->OXDELSTREETNR,
			'Ort' => $customer->OXDELCITY,
			'PLZ' =>  $customer->OXDELZIP,
			'Land' => $oxid['db']->get_var("SELECT OXISOALPHA2 FROM ".$oxid['table_country']." WHERE OXID = '".$customer->OXDELCOUNTRYID."'"),
		);

		$format = array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s');
		$selectline['db']->query($this->format_insert_query($selectline['table_delivery_address'], $insertArray, $format));
		return;
	}


	/**
	 * [add_discount_to_order_selctline description]
	 * @param [type] $order   [description]
	 * @param [type] $voucher [description]
	 */
	private function add_discount_to_order_selctline($order, $voucher){
		global $oxid, $selectline;

		$updateArray = array(
			'Rabattgruppe' => $voucher['discountGroup']
		);

		$selectline['db']->query($this->format_update_query($selectline['table_orders'], $updateArray, array('Belegnummer' => $selectline['order_prefix'].$order->OXORDERNR)));
		$this->set_log("Rabattgruppe ".$voucher['discountGroup']."% \r\n");
	}

	/**
	 * Formatiert die Bezahlmethode aus Oxid in Selectline
	 *
	 * Zuordnung IDs
	 * 71 = Rechnung > 14 Tage
	 * 59 = Paypal > 30 Tage
	 * 52 = Vorkasse > 0 Tage
	 * 55 = Kreditkarte > 21 Tage
	 * 88 = Sofortüberweißung > 27 Tage
	 *
	 * ToDos:
	 * - Es fehlt noch sofortüberweißung + heidelpay
	 * @return int ID der Bezahlmethode in Oxid
	 */

	private function format_payment_to_selectline($payment){
		switch($payment):
			default:
				return 0;
			break;

			case 'oxidcreditcard':
				return 55;
			break;
			case 'oxidpayadvance':
				return 52;
			break;
			case 'oxidinvoice':
				return 71;
			break;
			case 'oxidpaypal':
				return 59;
			break;
		endswitch;
	}

	/**
	 * [format_payment_termn_to_selectline description]
	 * @param  [type] $payment [description]
	 * @return [type]          [description]
	 */
	private function format_payment_termn_to_selectline($payment){
		switch($payment):
			default:
				return 14;
			break;

			case 'oxidcreditcard':
				return 21;
			break;
			case 'oxidpayadvance':
				return 0;
			break;
			case 'oxidinvoice':
				return 14;
			break;
			case 'oxidpaypal':
				return 30;
			break;
		endswitch;
	}


	/**
	 * [get_vatcode description]
	 * @param  [type] $vat [description]
	 * @return [type]      [description]
	 */
	private function get_vatcode($vat){
		switch($vat){
			case 19:
			default:
				return 3;
			break;

			case 7:
				return 2;
			break;
		}
	}

	/**
	 * [count_users description]
	 * @return [type] [description]
	 */
	private function count_users(){
		global $oxid;
		return $oxid['db']->get_var("SELECT COUNT(*) FROM ".$oxid['table_users']."");
	}

	/**
	 * [get_next_customer_id_in_selectline description]
	 * @return [type] [description]
	 */
	private function get_next_customer_id_in_selectline(){
		global $conf;
		$result = file_get_contents($conf['nextCutomer']);
		$next = $result +1;
		file_put_contents( $conf['nextCutomer'], $next);
		return $result;
	}


	/**
	 * [get_voucher_artnum description]
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	private function get_voucher_artnum($order){
		global $oxid;
		// Checken ob der Code angelegt ist
		$voucher_serial_id = $oxid['db']->get_var("SELECT OXVOUCHERSERIEID FROM ".$oxid['table_vouchers']." WHERE OXORDERID = '".$order->OXID."'");
		if(isset($oxid['vouchers'][$voucher_serial_id])){
			$voucher = $oxid['vouchers'][$voucher_serial_id];
			if(is_array($voucher)){
				return $voucher;
			}
		}
		return false;
	}


	/**
	 * [mssql_escape description]
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	private function mssql_escape($str){
	   if(get_magic_quotes_gpc()){
	    $str= stripslashes($str);
	   }
	   return str_replace("'", "''", $str);
	}


}
