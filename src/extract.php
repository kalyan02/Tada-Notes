<?php
include "Data.class.php";
define( 'DATA_FILE', '*.data.sqlite' );
$data_files = glob( DATA_FILE );
$data_file = array_pop( $data_files );
//file_put_contents($data_file, "");
if( !$data_file ) {
	$data_file = str_replace( '*', time(), DATA_FILE );
}

$data = new Data( $data_file );
$sql = "SELECT * FROM datastore WHERE name NOT LIKE '%admin::%' ORDER BY name";
$res = $data->dbh->query( $sql );
while( $row = $res->fetchObject() ) {
	if( !is_dir('data') )
		mkdir('data');

	echo "Extracting {$row->name}\n";
	$dn = "data/".dirname($row->name);
	$dn = trim($dn,"./");
	$fn = basename($row->name);
	if( !is_dir($dn) ) 
		mkdir( $dn, 0777, true );

	//$fn = str_ireplace( "html", "txt", $fn );
	file_put_contents( "$dn/$fn", $row->value );
}

