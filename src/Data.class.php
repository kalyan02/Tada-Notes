<?php
/**
  * The Data() class
  * Handles data storage for the tada-notes
  * Data is stored as key value pairs in an sqlite database
  *
  * @Author: Kalyan
  * @Dependencies: None
  */

class Data {
	var $dbh;
	var $dbn;
	var $cache;
	var $track;

	function __setupDB( $fname ) {
		$this->dbh = new PDO( "sqlite:$fname" );
		$sql = array("
			CREATE TABLE datastore (
				id INTEGER PRIMARY KEY,
				name VARCHAR(100),
				value TEXT,
				autoload INTEGER(1)
			)",
			"CREATE INDEX datastore_name ON datastore(name)",
			"CREATE INDEX datastore_autoload ON datastore(autoload)",
		);
		foreach( $sql as $query ) {
			$res = $this->dbh->query( $query );
			if( !$res ) {
				echo "<pre>";
				echo "DB : $fname\n";
				print_r( $this->dbh->errorInfo() );
				exit;
			}
		}

	}
	function __construct( $fname="notes.data.sqlite" ) {
		$this->track = array();
		$this->cache = array();

		$this->dbn = $fname;
		if( !file_exists( $fname ) || filesize($fname)==0 ) {
			fclose( fopen( $fname, "wb+" ) );
			$this->__setupDB($fname);
		}
		else
			$this->dbh = new PDO( "sqlite:$fname" );

		if( !$this->dbh )
			die( "Fatal error with db" );
	}
	function get( $what, $fresh=0 ) {
		$this->track[] = $what;

		//$this->set( 'admin::password', 'tada');

		//Simple caching
		if( !empty($this->cache[$what]) and !$fresh )
			return $this->cache[$what];
	
		//If it doesn't exist then you know what to do;
		$sql = sprintf ( "SELECT * FROM datastore WHERE name=%s", $this->dbh->quote($what) );
		$res = $this->dbh->query( $sql );
		$row = $res->fetchObject();
		if( $row == false ) 
			return null;
		else {
			$this->cache[ $row->name ] = $row->value;
			return $row->value;
		}
	}
	function set( $key, $val, $auto=null ) {

		$autoload = is_null($auto) ? 0 : (int)$auto;

		if( !is_null($val) ) {
			if( !is_null($this->get( $key, 1 )) ) {
				// Different type of update based on autoload status
				// for __future__
				if( is_null($auto) ) {
					$sql = sprintf(
						"UPDATE datastore SET value=%s WHERE name=%s",
						$this->dbh->quote($val),
						$this->dbh->quote($key)
					);
				} else {
					$sql = sprintf(
						"UPDATE datastore SET value=%s, autoload=%d WHERE name=%s",
						$this->dbh->quote($val),
						$this->dbh->quote($key),
						$this->dbh->quote($autoload)
					);
				}
			} else {
				// Insert if it doesn't exist
				$sql = sprintf( 
					"INSERT INTO datastore (name,value,autoload) VALUES(%s,%s,%d)",
					$this->dbh->quote($key),
					$this->dbh->quote($val),
					$this->dbh->quote($autoload)
				);
			}
			#echo $sql;
			$res = $this->dbh->exec( $sql );
			$this->cache[ $key ] = $val;			
			if( !$res ) {
				print_r( $this->dbh->errorInfo() );
			}
		} else {
			//Purge cache also delete variable
			unset( $this->cache[ $key ] );
			$sql = sprintf(
				"DELETE FROM datastore WHERE name=%s",
				$this->dbh->quote($key)
			);
			$this->dbh->query( $sql );
		}
	}
	function remove( $key ) {
		$this->set( $key, null );
	}
}

