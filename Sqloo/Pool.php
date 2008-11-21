<?php

class Sqloo_Pool
{
    private $_enabled_transaction;
    private $_selected_master_resource = NULL;
    private $_selected_slave_resource = NULL;
    private $_selected_master_db_name = NULL;
    
    private $_master_pool_array;
    private $_slave_pool_array;

    function __construct( $enabled_transaction, $master_pool_array, $slave_pool_array )
    {
        $this->_enabledTransaction = $enabled_transaction;
		$this->_master_pool_array = $master_pool_array;
		$this->_slave_pool_array = $slave_pool_array;
    }
	
	public function getMasterDatabaseName()
	{
		if( $this->_selected_master_db_name === NULL ) {
			getMasterResource();
		}
		return $this->_selected_master_db_name;
	}
	
    public function getMasterResource()
    {
        if ( $this->_selected_master_resource == NULL ) {
            if ( count( $this->_master_pool_array ) == 0 ) { 
            	trigger_error( "No master db set", E_USER_ERROR );
            }
            $selected_connection = $this->_master_pool_array[ array_rand( $this->_master_pool_array ) ];
            $this->_selected_master_resource = $this->_connectToDb( $selected_connection );
            $this->_selected_master_db_name = $selected_connection["database_name"];
        }
        return $this->_selected_master_resource;
    }

    public function getSlaveResource()
    {
        if ( $this->_selected_slave_resource == NULL ) {
            if ( count( $this->_slave_pool_array ) == 0 ) { 
                return $this->getMasterResource(); //if no slaves are in array, try using a master
            }
            $this->_selected_slave_resource = $this->_connectToDb( $this->_slave_pool_array[ array_rand( $this->_slave_pool_array ) ] );
        }
        return $this->_selected_slave_resource;
    }

    private function _connectToDb( $info_array )
    {
    	$db = $this->_enabledTransaction ? mysql_connect( $info_array["network_address"], $info_array["username"], $info_array["password"] ) : $db = mysql_pconnect( $info_array["network_address"], $info_array["username"], $info_array["password"] );
        if ( ( ! $db ) || ( ! mysql_select_db( $info_array["database_name"], $db ) ) ) { 
        	trigger_error( mysql_error(), E_USER_ERROR );
        }

        return $db;
    }
    
}

?>