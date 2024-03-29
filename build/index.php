<?php
session_start();
error_reporting( E_ALL );

define( 'DATA_FILE', '*.data.sqlite' );

define( 'TADA_DEBUG', false );;
/**
  * Class request.
  * To process the URL, etc
  */
class Req {
	var $request;
	var $base;
	function init() {
		$base = dirname( $_SERVER['SCRIPT_NAME'] );
		$base = trim( $base, '/' );
		$base = "/$base";
		$this->base = $base;
		$request_uri = trim( $_SERVER['REQUEST_URI'], '/' );
		$request = substr( $request_uri, strlen($base) );
		$request = parse_url( $request );
		
		if( !isset($request['query']) )
			$request['query'] = null;

		parse_str( $request['query'], $request['query']  );
		preg_match( "/\.([a-zA-Z0-9]+)$/", $request['path'], $match );

		$request['ext'] = !empty($match[1]) ? $match[1] : null;
		$request['path'] = trim( $request['path'], '/' );
		$request['path-base'] = dirname( $request['path'] );

		$pathParts = explode( '/', $request['path-base'] );
		$request['path-order'] = array();
		$pathPartsOrd = array();
		foreach( $pathParts as $part ) {
			$pathPartsOrd[] = $part;
			$request['path-order'][] = implode( '/', $pathPartsOrd );
		}
		$request['path-order'] = array_reverse( $request['path-order'] );

		// Set some default shortcuts
		if( isset($request['query']['edit']) )
			$request['scheme'] = 'edit';
		else
		if( isset($request['query']['admin']) )
			$request['scheme'] = 'admin';

		$this->request = $request;
	}
	function get( $what ) {
		if( !isset($this->request[ $what ]) )
			return null;
		return $this->request[$what];
	}
	function query($what) {
		if( !isset($this->request['query'][ $what ]))
			return null;
		return $this->request['query'][ $what ];
	}
	function url( $path ) {
		return $this->base . '/' . $path;
	}
};
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
};
/**
  * Basic templating
  * Nothing much really!
  */
class Tpl {
	var $tplCode;
	var $vars;
	function __construct( $tplCode ) {
		$this->tplCode = $tplCode;
		$this->vars = array();
	}
	function set( $var, $val ) {
		$this->vars[ $var ] = $val;
	}
	function getTplVars() {
		$tplVars = array();
		preg_match_all( "/\[\[tpl:([a-zA-Z0-9]+)(:[a-zA-Z]+)?\]\]/", $this->tplCode, $tplVars );
		return $tplVars[1];
	}
	function show() {
		global $req;
		//Set some defaults
		$this->set( 'base', $req->base );
		$this->set( 'path', $req->get('path') );

		//Now go on
		$tpl = $this->tplCode;
		foreach( $this->vars as $key=>$val ) {
			$tpl = str_ireplace( "[[tpl:$key]]", $val, $tpl );
		}
		return $tpl;
	}
}


/**
  * Recursively process page includes if any
  * Any other replacement, etc can be done without recursion globally
  */
function processPageIncludes($path, $isCon=false) {
	global $data, $req;
	//var_dump($pageRec,$path);
	//echo "\n\n\n";
	if( $isCon == false ) {
		$pageRec = $data->get( $path );
		if( empty($pageRec) and stripos($path, 'html') ) {
			$path = str_replace('html', 'txt', $path);
			$pageRec = $data->get( $path );
		}

		if(!empty($pageRec)) { 
			//Fetch the page variables
			$pageVars = unserialize( $pageRec );
			$pageCon = $pageVars['content'];
		}

	}
	else
		$pageRec = $pageCon = $path;

	//If page record is null. exit
	if( empty($pageRec) )
		return '';

	//We allow other pages to be included
	//Whats a templating system without it huh? :)
	$includes_regex = '/\[\[include\s?:\s?([a-zA-Z\/\.0-9-_]+)\s?\]\]/';
	preg_match_all( $includes_regex, $pageCon, $includes );
	$includePaths = $includes[1];
	$includeStrs = $includes[0];

	foreach( $includePaths as $i=>$incPath ) {
		$incCon =  processPageIncludes($incPath);
		$pageCon = str_replace( $includeStrs[$i], $incCon, $pageCon );
	}
	return $pageCon;
};

function fetchDataPath( $path ) {
	global $data;

	$content = '';
	if( defined('TADA_DEV') && TADA_DEV == true ) {
		$filePath = "data/$path";
		if( file_exists($filePath) ) {
			$content = file_get_contents( $filePath );
		}
	}

	if( $data ) {
		$content = $data->get( $path );
	}

	//var_dump($path,$content);
	return $content;
};
/**
  * Initialize
  */
if( strpos( DATA_FILE, '*' ) === false )
	$data_file = DATA_FILE;
else {
	$data_files = glob( DATA_FILE );
	$data_file = array_pop( $data_files );
	if( !$data_file ) {
		$data_file = str_replace( '*', time(), DATA_FILE );
	}
}
$data = new Data( $data_file );
$req = new Req();
$req->init();

if( isset($_GET['debug']) or !is_null($req->query('debug')) or strpos($_SERVER['REQUEST_URI'],"debug")!==false ) {
	echo "<pre>";
	var_dump( $req );
	var_dump( $_SERVER );
	echo "</pre>";
	exit;
}
//$data->set( 'admin::password', $passwd_db = 'tada' );

$scheme = $req->get('scheme');
$pageItem = $req->get( 'path' );
$pageName = basename( $pageItem );
$pathOrder = $req->get( 'path-order' );
$status = '';
$command = '';

if( $scheme == 'edit' ) {
	//Anything starting with a . is a template file	
	if( $pageName[0] == '.' )
		$command = "edit-template";
	else
		$command = "edit-page";

	if( !isset($_SESSION['admin-logged-in']) ) {
		header( "location:$req->base/admin:login.html?cmd=".urlencode( "$scheme:".$req->get('path')) );
	}
}
if( $scheme == 'admin' ) {

	$path = $req->get('path');
	if( $req->query('status') == 'loggedout' )
		$status = 'Logged out';

	$action = $req->query( 'action' );
	if( !is_null($action) and $action=='logout' ) {
		$status = 'Logged out';
		unset($_SESSION['admin-logged-in']);
	}

	if( isset($_POST['admin-login']) ) {
		$passwd = $_POST['password'];
		$passwd_db = $data->get( 'admin::password' );
		if( is_null($passwd_db) )
			$data->set( 'admin::password', $passwd_db = 'tada' );
		if( $passwd_db != $passwd ) {
			$status = 'Error:Wrong password';
		} else {
			$_SESSION['admin-logged-in'] = md5( microtime(1) );
			$cmd = $req->query('cmd');

			if( !empty($cmd) ) {
				$cmd = stripslashes( $req->query('cmd') );
			} else {
				$cmd = "admin:listing.html";
			}
			header( "location:$req->base/$cmd" );
		}
	}
	if( $req->get('path') == 'login.html' ) {
		$tpl = new Tpl( fetchDataPath('admin-tpl/admin-login-tpl.html') );
		$tpl->set( 'status', $status );
		$tpl->set( 'base', $req->base );
		echo $tpl->show();
		exit;
	}
	if( $req->get('path') == 'listing.html' ) {
		if( !isset($_SESSION['admin-logged-in']) ) {
			header( "location:$req->base/admin:login.html?cmd=".urlencode( "$scheme:".$req->get('path')) );
		}

		$status = '';
		$tplCon = fetchDataPath( 'admin-tpl/admin-listing-tpl.html' );
		$tplCon = processPageIncludes( $tplCon, 1 );
		$tpl = new Tpl( $tplCon ) ;//file_get_contents('admin-listing-tpl.html') );
		$tpl->set( 'base', $req->base );
		$tpl->set( 'status', $status );

		$base = $req->base;

		$sql = "SELECT * FROM datastore WHERE name NOT LIKE '%admin::%' ORDER BY name";
		$res = $data->dbh->query( $sql );
		$tplListing = '';
		while( $row=$res->fetchObject() ) {
			preg_match( "/(^.template.html)|(\/\.template.html)/", $row->name, $isTemplate );
			$tplListing .= "
				<tr>
					<td><a href='$base/$row->name'>$row->name</a></td>
					<td>" . (count($isTemplate)==0?'Page':'Template') . "</td>
					<td><a href='$base/edit:$row->name'>Edit</a></td>
				</td>
			";
		}
		$tpl->set( 'base', $req->base );
		$tpl->set( 'listing', $tplListing );
		echo $tpl->show();
		exit;
	}
	if( $req->get('path') == 'settings.html' ) {
		if( !isset($_SESSION['admin-logged-in']) ) {
			header( "location:$req->base/admin:login.html?cmd=".urlencode( "$scheme:".$req->get('path')) );
		}
		$status = '';
		if( isset($_POST['admin-change-password']) ) {
			$pass1 = $_POST['password'];
			$pass2 = $_POST['password2'];
			if( $pass1 != $pass2 ) {
				$status = "Error: Password mismatch. Try again!";
			} else {
				$data->set( 'admin::password', $pass1 );
				$status = "Password updated successfully";
			}
		}
		$tplCon = fetchDataPath( 'admin-tpl/admin-setting-tpl.html' );
		$tplCon = processPageIncludes( $tplCon, 1 );
		$tpl = new Tpl( $tplCon ) ;//file_get_contents('admin-listing-tpl.html') );
		$tpl->set( 'base', $req->base );
		$tpl->set( 'status', $status );
		echo $tpl->show();
	}
	exit;
}

if( $command == 'edit-template' ) {
	$status = '';
	if( isset($_POST['template-code']) ) {
		$_POST = array_map( 'stripslashes', $_POST );
		$isDelete = isset($_POST['delete-template']);
		$isSave = isset($_POST['save-template']);
		$tplCode = $_POST['template-code'];
		$path = $req->get('path');
		if( $isSave ) {
			$status = "<span class='green'>Template Saved at ".date("d-M h:i:s")."</span>";
			$data->set( $path, $tplCode ); 
		}
		if( $isDelete ) {
			$status = "<span class='red'>Template deleted'</span>";
			$data->remove( $path );
		}
	}
	$path = $req->get('path');
	$etCon = fetchDataPath( 'admin-tpl/edit-template-tpl.html' );
	$etCon = processPageIncludes( $etCon, 1 );
	$et = new Tpl( $etCon );
	$et->set( 'status', $status );
	$et->set( 'name', $path );
	$et->set( 'code', htmlentities($data->get($path)) );
	$et->set( 'base', $req->base );
	echo $et->show();
	exit;
}
if( $command == 'edit-page' ) {
	if( isset($_POST['page-content']) ) {
		$_POST = array_map( 'stripslashes', $_POST );
		$isDelete = isset($_POST['delete-page']);
		$isSave = isset($_POST['save-page']);
		$pageContent = $_POST['page-content'];
		$pageTitle = $_POST['page-title'];

		$path = $req->get('path');
		if( $isSave ) {
			$pageData = array(
				'content' => $pageContent,
				'title' => $pageTitle
			);
			$status = "<span class='green'>Page Saved at ".date("d-M h:i:s")."</span>";
			$data->set( $path, serialize($pageData) );  
		}
		if( $isDelete ) {
			$data->remove( $path );
			$status = "<span class='red'>Page has been deleted'</span>";
		}
	}
	$path = $req->get('path');
	$etCon = fetchDataPath( 'admin-tpl/edit-page-tpl.html' );
	$etCon = processPageIncludes( $etCon, 1 );
	$et = new Tpl( $etCon );
	$et->set( 'name', $path );
	$et->set( 'viewurl', $req->url($path) );	
	$pageData = unserialize($data->get($path));
	$et->set( 'status', $status );
	$et->set( 'base', $req->base );
	$et = new Tpl( $et->show() );
	$et->set( 'content', htmlentities($pageData['content']) );
	$et->set( 'title', htmlentities($pageData['title']) );
	echo $et->show();	
	exit;
}
$pageTemplate = null;
$templateOrder = array();
if( !empty($pageName) and $pageName[0] != '.' )  {
	# Priority 1 : .filename.html
	$templateOrder[] = $pathOrder[0] . "/." . $pageName;
	# Priority 2 : .template.html in each of parent directories
	foreach( $pathOrder as $po ) { 
		$templateOrder[] = "$po/.template.html";
	}
	# Priority 3 : .template.html in root directory
	$templateOrder[] = '.template.html';

	//Just get unique
	$templateOrder = array_unique( $templateOrder );
}
else {
	//Just trying to use a template.
	//Default template is nothing
}

function debugh( $con ) {
	echo "<pre>";
	echo htmlentities( $con );
	echo "</pre>";
}


//page.html?edit -> forgot what this was for
if( 1 ) {
	$theTemplate = '';
	$theTemplatePath = null;
	foreach( $templateOrder as $template ) {
		$tcon = $data->get( $template );
		if( !is_null($tcon) ) {
			$theTemplatePath = $template;
			$theTemplate = $tcon;
			break;
		}
	}

	$path = $req->get('path');
	$page = $data->get($path);
	$extn = $req->get('ext');

	//is user asking for a directory? If $data->get($that) of that doesn't exist Give him $that/index.html	
	if( is_null( $extn ) and is_null( $page ) ) {
		$path = !empty($path) ? $path . '/' : $path;
		//var_dump( $req );
		//var_dump( $_SERVER );
		header( "location:$req->base/{$path}index.html" );
	}

	if( $extn == 'text' and !is_null($page2=$data->get( str_replace(".text", "", $path) )) ) {
		header("content-type:text/plain");
		$page_u = unserialize( $page2 );
		if( is_array($page_u) ) {
			echo $page_u['title'] . "\n\n";
			echo $page_u['content'];
		} else
			echo $page2;
		exit;		
	}

	// If no user template exists
	// Print statically the contents of the path variable
	if( $theTemplate == '' ) {
		if( empty($pcon) )
			echo "<h1>Page does not exist !!</h1>";
		else
			echo $pcon;
	}
	// Template exists. But page doesn't.
	elseif( empty($page) ) {
		ob_start();
		var_dump( $req );
		$req_dump = ob_get_clean();

		$errorPage = $data->get("admin/error.template.html");
		if( is_null( $errorPage ) )
			$errorPage = "
				<h1>404 Error : Page '[[tpl:path]]' does not exist</h1>
				<p>Would you like to <a href='[[tpl:base]]/edit:[[tpl:path]]'>create it?</a></p>
				<!--pre style='background:#eee'>[[tpl:trace]]</pre-->
			";
		$errTpl = new Tpl( $errorPage );
		$errTpl->set('trace', trim($req_dump) );
		echo $errTpl->show();
	}
	// Template exists. Page exists
	else {
		//We have a template for the page
		//Process it and print it
		require_once( "markdown.php" );

		$theTemplate = processPageIncludes( $theTemplate, 1 );

		$output = new Tpl( $theTemplate );
		$pagevars = unserialize($page);
		$pagevars['content'] = processPageIncludes( $path );
		
		//Set template variables from within a page
		preg_match_all( "/\[\[set\s([a-zA-Z0-9-_]+)\s?:\s?(.*)\s?\]\]/", $theTemplate . $pagevars['content'], $tplvars );

		//Replace remove redundant stuff ?
		//Set vars should be removed atleast
		$pagevars['content'] = preg_replace(
			array(
				"/\[\[set\s([a-zA-Z0-9-_]+)\s?:\s?(.*)\s?\]\]/",
				//"/\[\[include\s?:\s?(.*)\s?\]\]/",
			),
			array(""),
			$pagevars['content']
		);

		$contentType = $req->get('ext');
		$contentHeaders = array(
			'css' => 'text/css',
			'js' => 'text/javascript',
			'text' => 'text/plain',
			'txt' => 'text/plain'
		);

		if( stripos( $output->show(), "[[tpl-static]]" ) === false and !isset( $contentHeaders[$contentType]) ) {
			$pagevars['content'] = Markdown( $pagevars['content'] );
		}

		foreach( $tplvars[1] as $i=>$varName ) {
			$varVal = $tplvars[2][$i];
			$output->set( $varName, $varVal );
		}

		$output->set( 'base', $req->base );
		foreach( $pagevars as $var=>$val )
			$output->set( $var, $val );

		if( isset($contentHeaders[$contentType]) )
			header( "Content-type:".$contentHeaders[$contentType]);
		$outputHTML = $output->show();
		echo $outputHTML;
	}
}
exit;

echo '<hr />';
var_dump( $req );
print_r( $data->cache );
print_r( $data->track );
?>
