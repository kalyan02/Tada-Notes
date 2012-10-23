<?php
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
}