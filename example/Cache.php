<?php

class CacheExample implements Sqloo_CacheInterface
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

?>