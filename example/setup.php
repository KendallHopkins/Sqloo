<?php

/* CONFIGURE */
	//include our headers
	require_once( "../Sqloo/Connection.php" );


/* CONNECTION */
	function master_pool()
	{
		return array(
			"address" => "127.0.0.1",
			"username" => "sqloo",
			"password" => "password",
			"name" => "sqloo",
			"type" => 1 ? "pgsql" : "mysql"
		);
	}
	
	//simple local cache
	class CacheLocal implements Sqloo_CacheInterface
	{
	
		private $_data = array();
		
		function set( $key, $data )
		{
			$this->_data[$key] = $data;
		}
		
		function get( $key, &$data )
		{
			if( array_key_exists( $key, $this->_data ) ) {
				$data = $this->_data[$key];
				return TRUE;
			} else {
				return FALSE;
			}
		}
		
		function remove( $key )
		{
			if( array_key_exists( $key, $this->_data ) ) {
				unset( $this->_data[$key] );
			}
		}
	
	}
	
	$cache_class = new CacheLocal();
	
	//We init Sqloo with functions to get database configuration and load tables dynamically
	$sqloo_connection = new Sqloo_Connection( "master_pool", NULL, $cache_class );

?>