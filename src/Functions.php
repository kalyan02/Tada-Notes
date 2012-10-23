<?php

function fetchDataPath( $path ) {
	global $data;

	$content = '';
	$filePath = "data/$path";
	if( file_exists($filePath) ) {
		$content = file_get_contents( $filePath );
	}

	if( !$data ) {
		$content = $data->get( $path );
	}

	//var_dump($path,$content);
	return $content;
}