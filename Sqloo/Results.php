<?php

class Sqloo_Results
{
    private $_resource;
    
    function __construct( $resource )
    {
    	if( ! $resource ){
    		trigger_error( "Created sqloo_results class without valid resource", E_USER_ERROR );
    	}
        $this->_resource = $resource;
    }

    public function fetchRow()
    {
        return mysql_fetch_assoc( $this->_resource );
    }
    
    public function fetchArray()
    {
    	$row_array = array();
        while ( $row = mysql_fetch_assoc( $this->_resource ) ) { $row_array[] = $row; }
        return $row_array;
    }
    
    public function countRows()
    {
    	return mysql_num_rows( $this->_resource );
    }
}

?>