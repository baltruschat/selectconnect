<?php
	class Cartridge{

		public $items = 0;
		public $imports = 0;
		public $imports_fail = 0;

		public function format_insert_query($table, $insertArray, $format = false){
			$col = implode(", ",array_keys($insertArray));
			if(!is_array($format)){
				$escaped_val = array_map('mysql_real_escape_string', array_values($insertArray));
				$val  = implode("', '", $escaped_val);
				return "INSERT INTO ".$table." (".$col.") VALUES ('".$val."')";
			}else{
				$valueArray = array_values($insertArray);
				$val = '';
				foreach($valueArray as $key => $value){
					switch($format[$key]):
						case '%i':
							$val .= $value;
						break;
						case '%f':
							$val .= $value;
						break;
						case '%b':
							$val .= $value;
						break;
						default:
							$val .= "'".$value."'";
						break;
					endswitch;
					if($key < sizeof($valueArray)-1){
						$val .= ", ";
					}
				}
				return "INSERT INTO ".$table." (".$col.") VALUES (".$val.")";
			}

		}

		public function format_update_query($table, $update_array, $where){
			//$update_array = array_map('mysql_real_escape_string', $update_array);
			$array= array();
			foreach($update_array as $key => $item){
				if($item === NULL){

				}else if(is_int($item)){
					$array[] = $key ." = ".mysql_real_escape_string($item);
				}else{
					$array[] = $key ." = '".mysql_real_escape_string($item)."'";
				}
			}
			$p_string = implode($array,", ");

			if(is_int(current($where))){
				$where_query = current($wheress);
			}else{
				$where_query = "'".current($where)."'";
			}
			return "UPDATE ".$table." SET ".$p_string." WHERE ".key($where)." = $where_query";
		}


		public function guid36($include_braces = false) {
		    if (function_exists('com_create_guid')) {
		        if ($include_braces === true) {
		            return com_create_guid();
		        } else {
		            return substr(com_create_guid(), 1, 36);
		        }
		    } else {
		        mt_srand((double) microtime() * 10000);
		        $charid = strtoupper(md5(uniqid(rand(), true)));

		        $guid = substr($charid,  0, 8) . '-' .
		                substr($charid,  8, 4) . '-' .
		                substr($charid, 12, 4) . '-' .
		                substr($charid, 16, 4) . '-' .
		                substr($charid, 20, 12);

		        if ($include_braces) {
		            $guid = '{' . $guid . '}';
		        }

		        return $guid;
		    }
		}

		public function get_last_update(){
			global $conf;
			$update_file = file_get_contents($conf['updatefile']);
			$last_update = DateTime::createFromFormat('d.m.Y H:i:s', $update_file);
			return $last_update;
		}

		public function set_last_update(){
			global $conf;
			file_put_contents( $conf['updatefile'],date('d.m.Y H:i:s') );
		}

		public function is_current_update_process(){
			global $conf;
			return file_exists($conf['lockfile']);
		}

		public function set_lock_file(){
			global $conf;
			fopen($conf['lockfile'],'w');
		}

		public static function set_log($text){
			global $conf;
			file_put_contents( $conf['logfile'],$text,FILE_APPEND);
			echo $text;
		}

		public function remove_lock_file(){
			global $conf;
			@unlink($conf['lockfile']);
		}
	}
?>
