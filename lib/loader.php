<?php

$cartridges = list_dir_files(ABSPATH.'/cartridges');
if($cartridges){
	foreach($cartridges as $c){
		if($c[0] == '.') continue;
		require ABSPATH .'/cartridges/'. $c;
	}
}


function list_dir_files($dir){
	$filelist = array();
	$handle=opendir ($dir);
	while ($file = readdir ($handle)) {
		if($file != '.' && $file != '..')
			$filelist[] = $file;
	}
	closedir($handle);
	return (sizeof($filelist) == 0) ? false : $filelist;
}