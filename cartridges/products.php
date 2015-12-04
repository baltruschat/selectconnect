<?php
class Products extends Cartridge{
		
	private $products;


	/**
	 * [import description]
	 * @return [type] [description]
	 */
	public function import($step = 0){
		global $selectline;
		$this->get_from_selectline($step);



		if($this->products){
			
			foreach($this->products as $product){
				$this->items++;
				$this->set_to_oxid($product);
			}
			
			return  (($step+1)*$selectline['limit']) - ($selectline['limit']-$this->items);
		}
		return '<p class="alert alert-warning">SelectConnect konnte keine Produkte in SelectLine finden.</p>';
	}	

	public function count_products_from_selectline(){
		global $oxid, $selectline;
		$categories = Categories::get_id_array_from_oxid();
		return $selectline['db']->get_var("SELECT COUNT(*) AS max FROM ".$selectline['table_products']." WHERE  ".$selectline['filter_products_col']." = '".$selectline['filter_products_value']."' AND [ShopAktiv] = '1'");	
	}

	/**
	 * [get_from_selectline description]
	 * @return [type] [description]
	 */
	private function get_from_selectline($step){
		global $oxid, $selectline;
		$categories = Categories::get_id_array_from_oxid();
		

		$this->products = $selectline['db']->get_results("
			select top ".$selectline['limit']." [ART_ID],[Inaktiv],[Artikelnummer],[EANNummer],[Bezeichnung],[Zusatz],[_BRUTTO],[Gewicht],[Artikelgruppe],[text], [SSVerkauf] from (
      			select *, ROW_NUMBER() over (order by [Artikelnummer]) as r_n_n 
      			from ".$selectline['table_products']."
      			
      			where ".$selectline['filter_products_col']." = '".$selectline['filter_products_value']."'  AND [ShopAktiv] = '1'
			) AS xx
			LEFT JOIN ".$selectline['table_product_text']." ON [BLOBKEY] = 'AR' + [Artikelnummer]
			where r_n_n >= ".$step*$selectline['limit']." ");
	}


	/**
	 * Produkte werden in die OXID-DB geschrieben/geupdatet
	 *
	 * ToDos:
	 *
	 * - Wo wird die MWST definiert
	 * - Artikelvariaten
	 * 
	 * @param [type] $product [description]
	 */
	private function set_to_oxid($product){
		global $oxid, $selectline;

		$array = array(
			'OXID' => $product->ART_ID,
			'OXSHOPID' => $oxid['oxid_shop'],
			'OXACTIVE' => ($product->Inaktiv) ? 0 : 1,
			'OXARTNUM' => $product->Artikelnummer,	
			'OXEAN' => $product->EANNummer,
			'OXTITLE' => $product->Bezeichnung,
			'OXPRICEKG' => $product->Zusatz,
			'OXPRICE' => str_replace(',', '.', $product->_BRUTTO),

			'OXVAT' => $selectline['tax'][$product->SSVerkauf],
			'OXWEIGHT' => $product->Gewicht,
			'OXSTOCK' => 1,
			'OXSTOCKFLAG' => 1,
			'OXISSEARCH' => 1,
			'OXVARMINPRICE' => str_replace(',', '.', $product->_BRUTTO),
			'OXVARMAXPRICE' => str_replace(',', '.', $product->_BRUTTO),
			'OXSUBCLASS' => 'oxarticle',
			'OXSORT' => 1,
			'OXSHOWCUSTOMAGREEMENT' => 1
		);

		$product_exsits = $oxid['db']->get_row("SELECT * FROM ".$oxid['table_products']." WHERE OXID = '".$product->ART_ID."'");
		if($product_exsits){
			if($_GET['overwrite']){
				unset($array['OXID']);
				$result = $oxid['db']->query( $this->format_update_query($oxid['table_products'], $array, array('OXID' => $product->ART_ID)) );
				
			}else{
				$result = true;
			}
		}else{
			$result = $oxid['db']->query( $this->format_insert_query($oxid['table_products'], $array) );
		}
		

		if($result){
			/* Produkt-Text zu OXID hinzufügen */
			$this->update_product_text_oxid($product);

			/* 
			/* Verknüpfung Kategorie */
			//$this->set_product_to_category_oxid($product);

			/* Bilder auf den Serverladen und in der DB speichern */
			if($_GET['images']){
				$this->set_product_images_oxid($product);
			}

			$this->imports++;
		}else{
			$this->imports_fails++;
			
		}
	}


	/**
	 * [set_product_to_category_oxid description]
	 * @param [type] $product [description]
	 */
	private function set_product_to_category_oxid($product){
		global $oxid, $selectline;
		$array = array(
			'OXID' => substr(md5(uniqid('', true) . '|' . microtime()), 0, 32),
			'OXOBJECTID' => $product->ART_ID,
			'OXCATNID' => Categories::get_category_id_from_number($product->Artikelgruppe),
			'OXPOS' => 0,
			'OXTIME' => 0
		);

		$category_object_exsits = $oxid['db']->get_var("SELECT OXID FROM ".$oxid['table_product_to_category']." WHERE OXOBJECTID = '".$product->ART_ID."' AND OXCATNID = '".Categories::get_category_id_from_number($product->Artikelgruppe)."' ");

		if($category_object_exsits){
			unset($array['OXID']);
			$oxid['db']->query( $this->format_update_query($oxid['table_product_to_category'], $array, array('OXID' => $category_object_exsits)) );
		}else{
			$oxid['db']->query( $this->format_insert_query($oxid['table_product_to_category'], $array) );
		}
	}


	/**
	 * [set_product_images_oxid description]
	 * @param [type] $product [description]
	 */
	private function set_product_images_oxid($product){
		global $oxid, $selectline;
		$product->images = $selectline['db']->get_results("SELECT * FROM ".$selectline['table_product_img']." WHERE [Blobkey] = 'AR".$product->Artikelnummer."'");
		if($product->images){
			$i = 1;
			
			$ftp = new Ftp;
			$ftp->connect($oxid['ftp_host']);
			$ftp->login($oxid['ftp_user'],$oxid['ftp_password']);

			foreach($product->images as $image){
				$img = WideImage::load($image->Bild);
				if($i > 1)
					$filename = $product->ART_ID.'_'.$i.'.jpg';
				else
					$filename = $product->ART_ID.'.jpg';

				$img->saveToFile(ABSPATH .'/images/'.$filename);

				$ftp->put($oxid['img_path'].'/master/product/'.$i.'/'.$filename, ABSPATH .'/images/'.$filename, FTP_BINARY);
				@unlink(ABSPATH .'/images/'.$filename);
				
				$oxid['db']->query( $this->format_update_query($oxid['table_products'], array('OXPIC1' => $filename), array('OXID' => $product->ART_ID)) );

				$i++;
			}
			$ftp->close();
		}
	}


	/**
	 * [update_product_text_oxid description]
	 * @param  [type] $product [description]
	 * @return [type]          [description]
	 */
	private function update_product_text_oxid($product){
		global $oxid, $selectline;
		
		$array = array('OXID' => $product->ART_ID, 'OXLONGDESC' => $product->text);
		$product_text_exsits = $oxid['db']->get_row("SELECT * FROM ".$oxid['table_product_extends']." WHERE OXID = '".$product->ART_ID."'");
		if($product_text_exsits){
			unset($array['OXID']);
			$oxid['db']->query( $this->format_update_query($oxid['table_product_extends'], $array, array('OXID' => $product->ART_ID)) );
		}else{
			$oxid['db']->query( $this->format_insert_query($oxid['table_product_extends'], $array) );
		}
	}


	/**
	 * [set_product_xsel_oxid description]
	 * @param [type] $product [description]
	 */
	public function set_product_xsel_oxid(){
		global $oxid, $selectline;

		$products = $oxid['db']->get_results("SELECT * FROM ".$oxid['table_products']." ");
		if($products):
			foreach($products as $product){

				$xsels = $selectline['db']->get_results("SELECT * FROM ".$selectline['table_product_xsel']." WHERE [ArtArtikelnummer] = '".$product->OXARTNUM."' ");
				if($xsels){
					foreach($xsels as $xsel){
						$xsel_exsits = $oxid['db']->get_row("SELECT * FROM ".$oxid['table_product_xsel']." WHERE OXARTICLENID = '".$product->OXARTNUM."' AND OXOBJECTID= '".$xsel->Artikelnummer."' ");
						$xsel_array = array(
							'OXID' => substr(md5(uniqid('', true) . '|' . microtime()), 0, 32),
							'OXOBJECTID' => $this->get_oxid_from_artnum_oxid($xsel->Artikelnummer),
							'OXARTICLENID' => $product->OXID,
							'OXSORT' => $xsel->Pos
						);
						// Produkt nicht aktiv
						if($xsel_array['OXOBJECTID'] == false)
							continue;

						if($xsel_exsits){
							unset($xsel_array['OXID']);
							$oxid['db']->query( $this->format_update_query($oxid['table_product_xsel'], $xsel_array, array('OXID' => $xsel_exsits->OXID)) );
						}else{
							$oxid['db']->query( $this->format_insert_query($oxid['table_product_xsel'], $xsel_array) );	
						}
					}
				}
			}
			return '<p>Cross-Selling Produkte hinzugefügt</p>';
		endif;
	}

	public function get_oxid_from_artnum_oxid($artid){
		global $oxid, $selectline;	
		return $oxid['db']->get_var("SELECT OXID FROM ".$oxid['table_products']." WHERE OXARTNUM = '".$artid."' ");
	}

	public static function get_product_from_artnum_selectline($artnum){
		global $selectline;
		return $selectline['db']->get_row("SELECT * FROM ".$selectline['table_products']." WHERE [Artikelnummer] = '".$artnum."' ");
	}

	public static function get_product_lager_from_artnum_selectline($artnum){
		global $selectline;
		return $selectline['db']->get_row("SELECT * FROM ".$selectline['table_products_lager']." WHERE [Artikelnummer] = '".$artnum."' ");
	}
}
?>