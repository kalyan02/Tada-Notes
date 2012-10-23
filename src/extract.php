<pre><?php
include "Data.class.php";

$data = new Data();
$sql = "SELECT * FROM datastore WHERE name NOT LIKE '%admin::%' ORDER BY name";
$res = $data->dbh->query( $sql );
while( $row = $res->fetchObject() ) {
	$dn = "extracts/".dirname($row->name);
	$dn = trim($dn,"./");
	$fn = basename($row->name);
	if( !is_dir($dn) ) 
		mkdir( $dn, 0777, true );

	$fn = str_ireplace( "html", "txt", $fn );
	
	file_put_contents( "$dn/$fn", $row->value );
	echo  "$dn\t\t\t$fn \n";
}

