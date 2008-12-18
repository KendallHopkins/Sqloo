<?php

class Sqloo_Query_Results
{

	private $_resource;
	private $_buffered;
	
	function __construct( $resource, $buffered = TRUE )
	{
		$this->_resource = $resource;
		$this->_buffered = $buffered;
	}
	
	function __destruct()
	{
		mysql_free_result( $this->_resource );
	}

	public function count()
	{
		if( $this->_buffered !== TRUE ) trigger_error( "query is unbuffered, doesn't allow counting", E_USER_ERROR );
		return mysql_num_rows( $this->_resource );
	}

	public function fetchRow()
	{
		return mysql_fetch_assoc( $this->_resource );
	}
	
	public function fetchArray()
	{
		if( $this->_buffered !== TRUE ) trigger_error( "query is unbuffered, if your going to use fetch array, use buffered", E_USER_ERROR );
		$row_array = array();
		while( $row = mysql_fetch_assoc( $this->_resource ) ) $row_array[] = $row;
		return $row_array;
	}
		
}

?>