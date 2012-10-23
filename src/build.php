<?php
define( 'DATA_FILE', '*.data.sqlite' );
define( 'DATA_DIR', 'data' );
require_once( "Data.class.php" );

if( strpos( DATA_FILE, '*' ) === false )
	$data_file = DATA_FILE;
else {
	$data_files = glob( DATA_FILE );
	$data_file = array_pop( $data_files );
	//file_put_contents($data_file, "");
	if( !$data_file ) {
		$data_file = str_replace( '*', time(), DATA_FILE );
	}
}
$data = new Data( $data_file );

$dir = glob( DATA_DIR );
$pathList = array();
deep_path_traverse(DATA_DIR);

foreach ($pathList as $item	) {
	$data->set( $item, file_get_contents("data/$item") );
}


function deep_path_traverse( $path ) {
	global $pathList;
	if( !is_dir($path) ) {
		$path = substr( $path, strlen(DATA_DIR) );
		$path = trim($path);
		$path = trim($path,"/");
		$pathList[] = $path;
	}
	else {
		$list = glob( "$path/*" );
		foreach ( $list as $key => $item) {
			deep_path_traverse( $item );
		}
	}
}