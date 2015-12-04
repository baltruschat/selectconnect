<?php
	
	class Categories extends Cartridge{

		private $categories;


		/**
		 *
		 * 	ToDos:
		 *
		 * 	Wie kann ich alle GOSCH-Produkte aus Selectline bekommen
		 * 
		 */


		/**
		 * [import description]
		 * @return [type] [description]
		 */
		public function import(){
			$this->get_from_selectline();
			if($this->categories):
				foreach($this->categories as $category){
					$this->set_to_oxid($category);
				}
				return '<p class="alert alert-success">SelectConnect hat '.$this->imports.' Kategorien importiert/geupdated.</p>';
			endif;
			return '<p class="alert alert-warning">SelectConnect konnte keine Kategorien in SelectLine finden.</p>';
		}

		/**
		 *  Holt die Kategorien aus der Selectline-DB
		 *  
		 *  ToDos:
		 * @return [type] [description]
		 */
		private function get_from_selectline(){
			global $oxid, $selectline;
			$this->categories = $selectline['db']->get_results("SELECT * FROM ".$selectline['table_categories']." WHERE  ".$selectline['filter_categories_col']." = '".$selectline['filter_categories_value']."' ORDER BY [Nummer]");

		}


		/**
		 * [get_from_oxid description]
		 * @return [type] [description]
		 */
		public function get_id_array_from_oxid(){
			global $oxid;
			$cats =  $oxid['db']->get_results("SELECT OXSORT FROM ".$oxid['table_categories']." WHERE OXSHOPID = '".$oxid['oxid_shop']."'");
			$ret = array();
			foreach($cats as $cat){
				$ret[] = $cat->OXSORT;
			}
			return $ret;
		}

		/**
		 * [set_category_to_oxid description]
		 * @param [type] $category [description]
		 */
		private function set_to_oxid($category){
			global $oxid;

			//print_r($category);

			if($category->Parent == NULL)
				$category->Parent = 'oxrootid';

			$category->left = 2;
			$category->right = 2;
			$category->active = 1;

			if($category->Parent == 'oxrootid'){
				$category->rootid = $category->Parent;
			}else{
				$category->rootid = $this->get_oxrootid_walker($category->GRUPPEN_ID, $category->Parent);
			}

			$category_exsits = $oxid['db']->get_row("SELECT * FROM ".$oxid['table_categories']." WHERE OXID = '".$category->GRUPPEN_ID."'");

			if($category_exsits){
				$update_array = array(
					'OXPARENTID' => $category->Parent,
					'OXLEFT' => $category->right, 
					'OXRIGHT' => $category->right, 
					'OXROOTID' => $category->rootid,
					'OXSORT' => $category->Nummer,
					'OXACTIVE' => $category->active,
					'OXHIDDEN' => 0, 
					'OXSHOPID' => $oxid['oxid_shop'],
					'OXTITLE' => $category->Bezeichnung
				);

				$results = $oxid['db']->query($this->format_update_query($oxid['table_categories'],$update_array, array('OXID' => $category->GRUPPEN_ID)));
			}else{
				$insert_array = array(
					'OXID' => $category->GRUPPEN_ID, 
					'OXPARENTID' => $category->Parent, 
					'OXLEFT' => $category->left, 
					'OXRIGHT' => $category->right, 
					'OXROOTID' => $category->rootid, 
					'OXSORT' => $category->Nummer, 
					'OXACTIVE' => $category->active, 
					'OXHIDDEN' => 0, 
					'OXSHOPID' => $oxid['oxid_shop'], 
					'OXTITLE' => $category->Bezeichnung
				);
				$this->format_insert_query($oxid['table_categories'],$insert_array);
				$results = $oxid['db']->query($this->format_insert_query($oxid['table_categories'],$insert_array));	
			}

			if($results)
				$this->imports++;
			else{
				$this->imports_fails++;
			}

		}

		/**
		 * [get_oxrootid_walker description]
		 * @param  [type] $category_id [description]
		 * @param  [type] $parent_id   [description]
		 * @return [type]              [description]
		 */
		private function get_oxrootid_walker($category_id, $parent_id){
			global $oxid;

			$category = $db->get_row("SELECT DISTINCT OXPARENTID FROM ".$oxid['db']." WHERE OXID = '".$parent_id."' ");
			if($category && $category->OXPARENTID != 'oxrootid'){
				$oxrootid = $category->OXPARENTID;
				$this->get_oxrootid_walker($category_id, $category->OXPARENTID);
			}else{
				$oxrootid = $category_id;
			}
			
			return $oxrootid;
		}

		public function get_category_id_from_number($no){
			global $selectline;
			return $selectline['db']->get_var("SELECT [GRUPPEN_ID] FROM ".$selectline['table_categories']." WHERE [Nummer] = '".$no."' ");
		}
	}
	
?>