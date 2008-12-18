<?php

class Sqloo_Pool
{

	private $_master_pool_array;
	private $_slave_pool_array;
	private $_selected_master_db_name = NULL;


	function __construct( $master_pool_array, $slave_pool_array )
	{
		$this->_master_pool_array = $master_pool_array;
		$this->_slave_pool_array = $slave_pool_array;
	}
	
	public function getMasterDatabaseName()
	{
		if( $this->_selected_master_db_name === NULL ) $this->getMasterResource();
		return $this->_selected_master_db_name;
	}
	
	public function getTransactionResource()
	{
		static $selected_transaction_resource = NULL;
		if ( $selected_transaction_resource === NULL ) {
			if ( count( $this->_master_pool_array ) === 0 ) trigger_error( "No master db set", E_USER_ERROR );
			$selected_connection = $this->_master_pool_array[ array_rand( $this->_master_pool_array ) ];
			$selected_transaction_resource = $this->_connectToDb( $selected_connection, FALSE );
		}
		return $selected_transaction_resource;
	}
	
	public function getMasterResource()
	{
		static $selected_master_resource = NULL;
		if ( $selected_master_resource === NULL ) {
			if ( count( $this->_master_pool_array ) === 0 ) trigger_error( "No master db set", E_USER_ERROR );
			$selected_connection = $this->_master_pool_array[ array_rand( $this->_master_pool_array ) ];
			$selected_master_resource = $this->_connectToDb( $selected_connection );
			$this->_selected_master_db_name = $selected_connection["database_name"];
		}
		return $selected_master_resource;
	}

	public function getSlaveResource()
	{
		static $selected_slave_resource = NULL;
		if ( $selected_slave_resource === NULL ) {
			if ( count( $this->_slave_pool_array ) === 0 ) return $this->getMasterResource();
			$selected_slave_resource = $this->_connectToDb( $this->_slave_pool_array[ array_rand( $this->_slave_pool_array ) ] );
		}
		return $selected_slave_resource;
	}

	private function _connectToDb( $info_array, $permanent_connection = TRUE )
	{
		$db = $permanent_connection ? mysql_pconnect( $info_array["network_address"], $info_array["username"], $info_array["password"] ) : mysql_connect( $info_array["network_address"], $info_array["username"], $info_array["password"] );
		if ( ( ! $db ) || ( ! mysql_select_db( $info_array["database_name"], $db ) ) ) trigger_error( mysql_error(), E_USER_ERROR );
		return $db;
	}
	
}

?>