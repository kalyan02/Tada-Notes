<?php
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
}