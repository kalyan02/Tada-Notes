<?php
define( 'BUILD', 'build/' );
define( 'SOURCE', 'src/' );
define( 'DATA_DIR', 'data/' );
define( 'CONF', 'conf/' );

define( 'DATA_FILE', '*.data.sqlite' );
define( 'CONF_DEV', 'dev.config.php');
define( 'CONF_BUILD', 'build.config.php');
define( 'CONF_DEFAULTS', 'defaults.config.php');

require_once( SOURCE . "Data.class.php" );

if( strpos( DATA_FILE, '*' ) === false )
	$data_file = BUILD . DATA_FILE;
else {
	$data_files = glob( BUILD . DATA_FILE );
	$data_file = array_pop( $data_files );
	//file_put_contents($data_file, "");
	if( !$data_file ) {
		$data_file = str_replace( '*', time(), BUILD . DATA_FILE );
	}
}

build_log( "Creating data file : $data_file" );

	$data = new Data( $data_file );

	$dir = glob( DATA_DIR );
	$pathList = array();
	deep_path_traverse( SOURCE . DATA_DIR );

build_log( "Setting up DB" );
	$data->dbh->query( "DELETE from datastore" );
	foreach ($pathList as $item	) {
		build_log( '   setting: ' . $item );
		$data->set( $item, file_get_contents( SOURCE . DATA_DIR . $item ) );
	}
	require( SOURCE . CONF . CONF_DEFAULTS );
	foreach ( (array)$TADA as $item => $value) {
		build_log( '   setting: ' . $item );
		$data->set( $item, $value );
	}

build_log( "Copying files" );

	$source_lib_dir = SOURCE . 'lib';
	$source_dir = SOURCE;
	$dest_dir = BUILD;
	`cp $source_lib_dir/* $dest_dir`;

build_log( "building php into single file" );

	$php_list = glob( SOURCE . "*.php" );
	$php_contents = array();
	$php_index = file_get_contents( SOURCE . "index.php" );
	preg_match_all( '/(require_once|include|require)\s?\((.*?)\)/', $php_index, $matches );
	foreach ( $matches[2] as $i=>$php_include ) {
		$php_include = trim($php_include);
		$php_include = trim($php_include, '\'",. ');
		$php_include_raw = trim($matches[0][$i]);

		// Don't replace anything in lib. These would be drop in includes because they are externals.
		if( strpos($php_include, 'lib/')!==false ) {
			$php_include_raw_new = str_replace( 'lib/', '', $php_include_raw );
			$php_index = str_replace( $php_include_raw, $php_include_raw_new, $php_index );

			continue;
		}
		// These are to be replaced inline
		else {
			// If the include is a Dev.Conf; replace it with build.conf's contents
			if( strpos($php_include, CONF_DEV) ) {
				$php_include = str_replace( CONF_DEV, CONF_BUILD, $php_include);
			}
			$php_inc = file_get_contents( SOURCE . $php_include);
			$php_inc = trim($php_inc);
			$php_inc = explode( "\n", $php_inc );

			$php_start = '<?php';
			$php_start_line = 0;
			$php_end = '?>';
			$php_end_line = count($php_inc) - 1;

			if( strpos( $php_inc[$php_start_line], $php_start)!==false )
				unset( $php_inc[$php_start_line] );
			if( strpos( $php_inc[$php_end_line], $php_end)!==false )
				unset( $php_inc[$php_end_line] );

			$php_inc = join( "\n", $php_inc );
			//$php_inc = substr( $php_inc, strpos($php_inc, needle))

			// Perform inline rpelacement of all includes/requires etc
			$php_index = str_replace( $php_include_raw, $php_inc, $php_index );
		}
	}

	file_put_contents( BUILD . 'index.php', $php_index );

build_log( "Copying htaccess" );
	`cp $source_dir.htaccess $dest_dir`;

// Deep traversal function.
function deep_path_traverse( $path ) {
	global $pathList;
	$ignoreList = array( '.git', '..', '.', '.DS_Store' );
	if( !is_dir($path) ) {
		$path = substr( $path, strpos( $path, DATA_DIR ) + strlen(DATA_DIR) );
		$path = trim($path);
		$path = trim($path,"/");
		$pathList[] = $path;
	}
	else {
		$path = trim( $path, '/,"');
		// We need hidden files too
		$list = glob( "$path/{,.}*", GLOB_BRACE );

		foreach ( $list as $key => $item) {
			if( in_array( basename($item), $ignoreList) )
				continue;
			deep_path_traverse( $item );
		}

	}
}
// Logging
function build_log( $str, $doDump=false ) {
	if($doDump) {
		echo '<pre>';
		var_dump($str);
		echo '</pre>';
	}
	else
		echo " >> $str\n";
}