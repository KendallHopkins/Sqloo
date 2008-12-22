<?php

/*
The MIT License

Copyright (c) 2008 Kendall Hopkins

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

class Sqloo
{
	
	//Table Consts
	//Parent & Column attributes
	const allow_null = "allow_null"; //bool
	const default_value = "default_value"; //mixed, (string, int, float, NULL)
	
	//Parent Attributes
	const parent_table_name = "parent_table_name"; //string
	const on_delete = "on_delete"; //action - see below
	const on_update = "on_update"; //action - see below
	
	//Column Attributes
	const data_type = "data_type"; //string
	const primary_key = "primary_key"; //bool
	const auto_increment = "auto_increment"; //bool
	
	//Index Attributes
	const column_array = "column_array"; //array
	const unique = "unique"; //bool
	
	//Actions
	const action_restrict = "RESTRICT";
	const action_cascade = "CASCADE";
	const action_set_null = "SET NULL";
	const action_no_action = "NO ACTION";
	
	//Query Consts
	//Order Types
	const order_ascending = "ASC";
	const order_descending = "DESC";
	
	//Join Types
	const join_inner = "INNER";
	const join_outer = "OUTER";
	const join_left = "LEFT";
	const join_right = "RIGHT";
	
	//Insert Modifiers
	const insert_low_priority = "LOW_PRIORITY";
	const insert_high_priority = "HIGH_PRIORITY";
	const insert_delayed = "DELAYED";

	private $_table_array = array();
	private $_in_transaction = 0;
	private $_master_db_function;
	private $_slave_db_function;
	private $_load_table_function
	private $_load_all_tables_function;
	private $_selected_master_db_name = NULL;
	
	public function __construct( $master_db_function, $slave_db_function = NULL, $load_table_function = NULL, $load_all_tables_function = NULL ) 
	{
		$this->_master_db_function = $master_db_function;
		$this->_slave_db_function = $slave_db_function;
		$this->_load_table_function = $load_table_function;
		$this->_load_all_tables_function = $load_all_tables_function;
	}
	
	public function __destruct()
	{
		if( $this->_in_transaction > 0 ) {
			for( $i = 0; $i < $this->_in_transaction; $i++ ) $this->rollbackTransaction();
			trigger_error( $i." transaction was not close and was rolled back", E_USER_ERROR );
		}
	}
	
	public function __clone() { trigger_error( "Clone is not allowed.", E_USER_ERROR ); }
		
	public function beginTransaction()
	{
		$this->_in_transaction++;
		$this->query( "BEGIN" );
	}
	
	public function rollbackTransaction()
	{
		if( $this->_in_transaction === 0 ) trigger_error( "not in a transaction, didn't rollback", E_USER_ERROR );
		$this->query( "ROLLBACK" );
		$this->_in_transaction--;
	}
	
	public function commitTransaction()
	{
		if( $this->_in_transaction === 0 ) trigger_error( "not in a transaction, didn't commit", E_USER_ERROR );
		$this->query( "COMMIT" );
		$this->_in_transaction--;
	}
	
	public function newQuery()
	{
		require_once( "Sqloo/Query.php" );
		return new Sqloo_Query( $this );
	}
	
	public function insert( $table_name, $insert_array, $modifier = NULL )
	{		
		//check if we have a "magic" added/modifed field
		$table_column_array = $this->getTable($table_name)->column;
		if( array_key_exists( "added", $table_column_array ) ) $insert_array["added"] = "CURRENT_TIMESTAMP";
		if( array_key_exists( "modified", $table_column_array ) ) $insert_array["modified"] = "CURRENT_TIMESTAMP";
		
		$insert_string = "INSERT ";
		if( $modifier !== NULL ) $insert_string .= $modifier." ";
		$insert_string .= "INTO `".$table_name."`\n";
		$insert_string .= "SET ".self::processKeyValueArray( $insert_array )."\n";
		$this->query( $insert_string );
		return mysql_insert_id( $this->_getMasterResource() );
	}
	
	public function update( $table_name, $update_array, $id_array )
	{
		$id_array_count = count( $id_array );
		if( $id_array_count === 0 ) trigger_error( "id_array of 0 size", E_USER_ERROR );
				
		//check if we have a "magic" modifed field
		if( array_key_exists( "modified", $this->getTable($table_name)->column ) ) $update_array["modified"] = "CURRENT_TIMESTAMP";
				
		/* create update string */
		$update_string = "UPDATE `".$table_name."`\n";
		$update_string .= "SET ".self::processKeyValueArray( $update_array )."\n";
		$update_string .= "WHERE id IN ".self::arrayToIn( $id_array )."\n";				
		$update_string .= "LIMIT ".$id_array_count."\n";
		$this->query( $update_string );
	}
	
	public function delete( $table_name, $id_array )
	{
		$id_array_count = count( $id_array );
		if ( $id_array_count === 0 ) trigger_error( "id_array of 0 size", E_USER_ERROR );
		
		$delete_string = "DELETE FROM `".$table_name."`\n";
		$delete_string .= "WHERE id IN ".self::arrayToIn( $id_array )."\n";
		$delete_string .= "LIMIT ".$id_array_count.";";
		$this->query( $delete_string );
	}
	
	public function union( $array_of_queries )
	{
		if( count( $array_of_queries ) < 2 ) trigger_error( "union must have more than 1 query objects", E_USER_ERROR );
		require_once( "Sqloo/Query.php" );
		return new Sqloo_Query( $this, $array_of_queries );
	}
	
	/* Schema Setup */
	
	public function newTable( $table_name )
	{
		require_once( "Sqloo/Table.php" );
		return $this->_table_array[ $table_name ] = new Sqloo_Table( $table_name );
	}
	
	public function newNMTable( $table1, $table2 )
	{
		$many_to_many_table = $this->newTable( self::computeNMTableName( $table1->name, $table2->name ) );
		$many_to_many_table->parent = array(
			$table1->name => array(
				Sqloo::parent_table_name => $table1->name, 
				Sqloo::allow_null => FALSE, 
				Sqloo::on_delete => Sqloo::action_cascade, 
				Sqloo::on_update => Sqloo::action_cascade
			),
			$table2->name => array(
				Sqloo::parent_table_name => $table2->name, 
				Sqloo::allow_null => FALSE, 
				Sqloo::on_delete => Sqloo::action_cascade, 
				Sqloo::on_update => Sqloo::action_cascade
			)
		);
		return $many_to_many_table;
	}
	
	/* Utilities */
	
	public function query( $query_string, $on_slave = FALSE, $buffered = TRUE )
	{
		if( $this->_in_transaction > 0 )
			$db = $this->_getTransactionResource();
		else if( ! $on_slave )
			$db = $this->_getMasterResource();
		else
			$db = $this->_getSlaveResource();
		
		$resource = $buffered ? mysql_query( $query_string, $db ) : mysql_unbuffered_query( $query_string, $db );
		if ( ! $resource ) trigger_error( mysql_error( $db )."<br>\n".$query_string, E_USER_ERROR );
		return $resource;
	}
	
	public function nextId( $tableName )
	{
		$query = "SHOW TABLE STATUS WHERE name = '".$tableName."';";
		$resource = $this->query( $query );
		$array = @mysql_fetch_assoc( $resource );
		if( $array == FALSE ) trigger_error( "bad table name", E_USER_ERROR );
		return $array[ "Auto_increment" ];
	}
	
	static public function processVariable( $value )
	{
		switch ( gettype( $value ) ) {
		case "boolean": return "'".(int)$value."'";
		case "NULL": return "NULL";
		case "integer":
		case "double":
		case "float": return $value;
		case "string":
			switch( $value ) {
			case "CURRENT_TIMESTAMP": return $value;
			default: return "'".mysql_escape_string( $value )."'";
			}
		case "object": return "(".(string)$value.")";
		default: trigger_error( "bad imput: ".var_export( $value, TRUE ), E_USER_ERROR );
		}
	}
	
	static public function computeNMTableName( $first_Table, $second_table )
	{
		return ( $first_Table < $second_table ) ? $first_Table."-".$second_table : $second_table."-".$first_Table;
	}
	
	static public function arrayToIn( $value_array )
	{
		$in_string = "(";
		foreach( $value_array as $value ) $in_string .= self::processVariable( $value ).",";
		return rtrim( $in_string, "," ).")";
	}
	
	static public function processKeyValueArray( $key_value_array )
	{
		$string = "";
		foreach( $key_value_array as $key => $value ) $string .= $key."=".$this->processVariable( $value ).",";
		return rtrim( $string, "," );
	}
	
	public function checkSchema()
	{
		require_once( "Sqloo/Schema.php" );
		static $schema = NULL;
		if( $schema === NULL ) $schema = new Sqloo_Schema( $this );
		return $schema->checkSchema();
	}
	
	public function getTableSchemaData()
	{
		if( $this->_load_all_tables_function !== NULL ) call_user_func( $this->_load_all_tables_function, $this );
		return $this->_table_array;
	}
	
	private function getTable( $table_name )
	{
		if( array_key_exists( $table_name, $this->_table_array ) ) return $this->_table_array[$table_name];
		if( $this->_load_tables_function !== NULL ) call_user_func( $this->_load_tables_function, $table_name, $this );
		if( array_key_exists( $table_name, $this->_table_array ) ) return $this->_table_array[$table_name];
		if( $this->_load_all_tables_function !== NULL ) call_user_func( $this->_load_all_tables_function, $this );
		if( array_key_exists( $table_name, $this->_table_array ) ) return $this->_table_array[$table_name];
		trigger_error( "could not load table: ".$table_name, E_USER_ERROR );
	}
	
	/* Database Management */
	
	public function getMasterDatabaseName()
	{
		if( $this->_selected_master_db_name === NULL ) $this->_getMasterResource();
		return $this->_selected_master_db_name;
	}
	
	private function _getTransactionResource()
	{
		static $selected_transaction_resource = NULL;
		if( $selected_transaction_resource === NULL ) {
			$selected_connection_array = call_user_func( $this->_master_db_function );
			if( $selected_connection_array === NULL ) trigger_error( "No master db set", E_USER_ERROR );
			$selected_transaction_resource = $this->_connectToDb( $selected_connection_array, FALSE );
			$this->_selected_master_db_name = $selected_connection_array["database_name"];
		}
		return $selected_transaction_resource;
	}
	
	private function _getMasterResource()
	{
		static $selected_master_resource = NULL;
		if( $selected_master_resource === NULL ) {
			$selected_connection_array = call_user_func( $this->_master_db_function );
			if( $selected_connection_array === NULL ) trigger_error( "No master db set", E_USER_ERROR );
			$selected_master_resource = $this->_connectToDb( $selected_connection_array );
			$this->_selected_master_db_name = $selected_connection_array["database_name"];
		}
		return $selected_master_resource;
	}
	
	private function _getSlaveResource()
	{
		static $selected_slave_resource = NULL;
		if ( $selected_slave_resource === NULL ) {
			if( $this->_slave_db_function === NULL ) return $this->_getMasterResource();
			$selected_connection_array = call_user_func( $this->_slave_db_function );
			if( $selected_connection_array === NULL ) return $this->_getMasterResource();
			$selected_slave_resource = $this->_connectToDb( $selected_connection_array );
		}
		return $selected_slave_resource;
	}
	
	private function _connectToDb( $info_array, $permanent_connection = TRUE )
	{
		$db = $permanent_connection ? mysql_pconnect( $info_array["network_address"], $info_array["username"], $info_array["password"] ) : mysql_connect( $info_array["network_address"], $info_array["username"], $info_array["password"] );
		if( ( ! $db ) || ( ! mysql_select_db( $info_array["database_name"], $db ) ) ) trigger_error( mysql_error(), E_USER_ERROR );
		return $db;
	}
	
}

?>