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

	private $_master_db_function;
	private $_slave_db_function;
	private $_load_table_function;
	private $_list_all_tables_function;
	private $_table_array = array();
	private $_transaction_depth = 0;
	
	/**
	*	Construct Function
	*	@param string Function that is called when Sqloo needs a master db configuration, should return a random master configuration
	*	@param string Function that is called when Sqloo needs a slave db configuration, should return a random slave configuration
	*	@param string Function that is called when Sqloo needs to access a table that isn't loaded, allows dynamically loaded tables
	*	@param string Function that is called when Sqloo needs a list of all the available tables
	*/
	
	public function __construct( $master_db_function, $slave_db_function = NULL, $load_table_function = NULL, $list_all_tables_function = NULL ) 
	{
		$this->_master_db_function = $master_db_function;
		$this->_slave_db_function = $slave_db_function;
		$this->_load_table_function = $load_table_function;
		$this->_list_all_tables_function = $list_all_tables_function;
	}
	
	/**
	*	Destruct Function
	*
	*	@access private
	*/
	
	public function __destruct()
	{
		if( $this->_transaction_depth > 0 ) {
			for( $i = 0; $i < $this->_transaction_depth; $i++ ) $this->rollbackTransaction();
			trigger_error( $i." transaction was not close and was rolled back", E_USER_ERROR );
		}
	}
	
	private function __clone() { trigger_error( "Clone is not allowed.", E_USER_ERROR ); }
	
	/**
	*	Opens a new transaction layer
	*
	*	This can be nested to allow multiable layers of transactions
	*/
	
	public function beginTransaction()
	{
		$this->_transaction_depth++;
		$this->query( "BEGIN" );
	}
	
	/**
	*	Rollbacks the outer transaction layer
	*/
	
	public function rollbackTransaction()
	{
		if( $this->_transaction_depth === 0 ) trigger_error( "not in a transaction, didn't rollback", E_USER_ERROR );
		$this->query( "ROLLBACK" );
		$this->_transaction_depth--;
	}
	
	/**
	*	Commits the outer transaction layer
	*/
	
	public function commitTransaction()
	{
		if( $this->_transaction_depth === 0 ) trigger_error( "not in a transaction, didn't commit", E_USER_ERROR );
		$this->query( "COMMIT" );
		$this->_transaction_depth--;
	}
	
	/**
	*	Get a new Sqloo_Query object
	*
	*	@returns Sqloo_Query
	*/
	
	public function newQuery()
	{
		require_once( "Sqloo/Query.php" );
		return new Sqloo_Query( $this );
	}
	
	/**
	*	Insert a new row into a table
	*
	*	This function will set the columns "added" and "modified" to the current date if they exist in the table
	*
	*	@param	string	Name of the table
	*	@param	array	Array with the attributes to be inserted, IE array( "column_name1" => "value1", "column_name2" => "value2" )
	*	@param	string	Insert modifier: insert_low_priority, insert_high_priority or insert_delayed
	*	@return	int		The id of the inserted row
	*/
	
	public function insert( $table_name, $insert_array, $modifier = NULL )
	{		
		//check if we have a "magic" added/modifed field
		$table_column_array = $this->_getTable($table_name)->column;
		if( array_key_exists( "added", $table_column_array ) ) $insert_array["added"] = "CURRENT_TIMESTAMP";
		if( array_key_exists( "modified", $table_column_array ) ) $insert_array["modified"] = "CURRENT_TIMESTAMP";
		
		$insert_string = "INSERT ";
		if( $modifier !== NULL ) $insert_string .= $modifier." ";
		$insert_string .= "INTO `".$table_name."`\n";
		$insert_string .= "SET ".self::processKeyValueArray( $insert_array )."\n";
		$this->query( $insert_string );
		return mysql_insert_id( $this->_getMasterResource() );
	}
	
	/**
	*	Update a list of rows with new values
	*
	*	This function will set the column "modified" to the current date if they exist in the table
	*
	*	@param	string	Name of the table
	*	@param	array	Array with the attributes to be modified, IE array( "column_name1" => "value1", "column_name2" => "value2" )
	*	@param	array	Array of positive int values that are the id's for the rows you want to update
	*/
	
	public function update( $table_name, $update_array, $id_array )
	{
		$id_array_count = count( $id_array );
		if( $id_array_count === 0 ) trigger_error( "id_array of 0 size", E_USER_ERROR );
				
		//check if we have a "magic" modifed field
		if( array_key_exists( "modified", $this->_getTable($table_name)->column ) ) $update_array["modified"] = "CURRENT_TIMESTAMP";
				
		/* create update string */
		$update_string = "UPDATE `".$table_name."`\n";
		$update_string .= "SET ".self::processKeyValueArray( $update_array )."\n";
		$update_string .= "WHERE id IN ".self::arrayToIn( $id_array )."\n";				
		$update_string .= "LIMIT ".$id_array_count."\n";
		$this->query( $update_string );
	}
	
	/**
	*	Delete a list of rows
	*
	*	@param	string	Name of the table
	*	@param	array	Array of positive int values that are the id's for the rows you want to delete
	*/
	
	public function delete( $table_name, $id_array )
	{
		$id_array_count = count( $id_array );
		if ( $id_array_count === 0 ) trigger_error( "id_array of 0 size", E_USER_ERROR );
		
		$delete_string = "DELETE FROM `".$table_name."`\n";
		$delete_string .= "WHERE id IN ".self::arrayToIn( $id_array )."\n";
		$delete_string .= "LIMIT ".$id_array_count.";";
		$this->query( $delete_string );
	}
	
	/**
	*	Make a union from multiable Sqloo_Query objects
	*
	*	All Sqloo_Query objects must have the same output column names for this to work
	*
	*	@param	array		Array of Sqloo_Query objects
	*	@return	Sqloo_Query	Sqloo_Query object preloaded with a union of the Sqloo_Query objects in $array_of_queries
	*/
	
	public function union( $array_of_queries )
	{
		if( count( $array_of_queries ) < 2 ) trigger_error( "union must have more than 1 query objects", E_USER_ERROR );
		require_once( "Sqloo/Query.php" );
		return new Sqloo_Query( $this, $array_of_queries );
	}
	
	/**
	*	Make a new Sqloo_Table Object and return it.
	*	
	*	@param	string		New table name
	*	@return	Sqloo_Table	Empty Sqloo_Table object
	*/
	
	public function newTable( $table_name )
	{
		require_once( "Sqloo/Table.php" );
		return $this->_table_array[ $table_name ] = new Sqloo_Table( $table_name );
	}
	
	/**
	*	Make a new Sqloo_Table Object, setup the parents and return it.
	*	
	*	@param	Sqloo_Table	Parent table 1
	*	@param	Sqloo_Table	Parent table 2
	*	@return	Sqloo_Table	N:M Sqloo_Table object, used for NMJoins
	*/
	
	public function newNMTable( $sqloo_table_class_1, $sqloo_table_class_2 )
	{
		$many_to_many_table = $this->newTable( self::computeNMTableName( $sqloo_table_class_1->name, $sqloo_table_class_2->name ) );
		$many_to_many_table->parent = array(
			$sqloo_table_class_1->name => array(
				Sqloo::parent_table_name => $sqloo_table_class_1->name, 
				Sqloo::allow_null => FALSE, 
				Sqloo::on_delete => Sqloo::action_cascade, 
				Sqloo::on_update => Sqloo::action_cascade
			),
			$sqloo_table_class_2->name => array(
				Sqloo::parent_table_name => $sqloo_table_class_2->name, 
				Sqloo::allow_null => FALSE, 
				Sqloo::on_delete => Sqloo::action_cascade, 
				Sqloo::on_update => Sqloo::action_cascade
			)
		);
		return $many_to_many_table;
	}
	
	/**
	*	Runs the $query_string on a database determinded by the params
	*	
	*	@param	string		Query string
	*	@param	bool		Set if the query can be run on a slave. If in a transaction, this is ignored.
	*	@param	bool		Set if the query should be buffered. Unbuffered queries have advantages and disadvantages.
	*	@return	resource	Resource from mysql_query or mysql_unbuffered_query.
	*/
	
	public function query( $query_string, $on_slave = FALSE, $buffered = TRUE )
	{
		if( $this->_transaction_depth > 0 )
			$query_type = "transaction";
		else if( ! $on_slave )
			$query_type = "master";
		else
			$query_type = "slave";
		
		$database_resource = $this->_getDatabaseResource( $query_type );
		$query_resource = $buffered ? mysql_query( $query_string, $database_resource ) : mysql_unbuffered_query( $query_string, $database_resource );
		if ( ! $query_resource ) trigger_error( mysql_error( $database_resource )."<br>\n".$query_string, E_USER_ERROR );
		return $query_resource;
	}
	
	/**
	*	Escapes values for a query
	*	
	*	@param	mixed	The value to be escaped, if a class object is passed it will attempt to be transformed into a string.
	*	@return	string	Processed value
	*/
	
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
	
	/**
	*	Used to compute the name of the NM between two tables.
	*
	*	Order they are put in makes no difference to the output.
	*
	*	@param	string	First table name
	*	@param	string	Second table name
	*	@return	string	NM Table name
	*/
	
	static public function computeNMTableName( $table_name_1, $table_name_2 )
	{
		return ( $table_name_1 < $table_name_2 ) ? $table_name_1."-".$table_name_2 : $table_name_2."-".$table_name_1;
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
	
	/**
	*	Checks and correct the database schema to match the table schema setup in code.
	*
	*	@return	string	Log of the queries run.
	*/
	
	public function checkSchema()
	{
		require_once( "Sqloo/Schema.php" );
		return Sqloo_Schema::checkSchema( $this->_getAllTables(), $this->_getDatabaseResource( "master" ), $this->_getDatabaseConfiguration( "master" ) );
	}
	
	/* Private Functions */
	
	private function _getTable( $table_name )
	{
		if( ! array_key_exists( $table_name, $this->_table_array ) ) $this->_loadTable();
		return $this->_table_array[$table_name];
	}
	
	private function _loadTable( $table_name )
	{
		if( ! array_key_exists( $table_name, $this->_table_array ) ) {
			if( $this->_load_tables_function && is_callable( $this->_load_tables_function, TRUE ) ) call_user_func( $this->_load_tables_function, $table_name, $this );
			if( ! array_key_exists( $table_name, $this->_table_array ) ) trigger_error( "could not load table: ".$table_name, E_USER_ERROR );
		}
	}
	
	private function _getAllTables()
	{
		static $all_tables_loaded = FALSE;
		if( ! $all_tables_loaded ) {
			if( is_callable( $this->_list_all_tables_function, TRUE ) ) {
				$table_array = call_user_func( $this->_list_all_tables_function, $this );
				foreach( $table_array as $table_name ) $this->_loadTable( $table_name );
			}
			$all_tables_loaded = TRUE;
		}
		return $this->_table_array;
	}
		
	private function _getDatabaseResource( $type_string )
	{
		static $database_resource_array = array();
		if( ! array_key_exists( $type_string, $database_resource_array ) ) {
			switch( $type_string ) {
			case "transaction":
				$permanent_connection = FALSE;
				break;
			case "master":
				$permanent_connection = TRUE;
				break;
			case "slave":
				$permanent_connection = TRUE;
				break;
			default:
				trigger_error( "Bad type_string: ".$type_string, E_USER_ERROR );
			}
			$database_configuration_array = $this->_getDatabaseConfiguration( $type_string );
			if( $permanent_connection )
				$database_resource = mysql_pconnect( $database_configuration_array["network_address"], $database_configuration_array["username"], $database_configuration_array["password"] );
			else
				$database_resource = mysql_connect( $database_configuration_array["network_address"], $database_configuration_array["username"], $database_configuration_array["password"] );
			
			if( ( ! $database_resource ) || ( ! mysql_select_db( $database_configuration_array["database_name"], $database_resource ) ) ) trigger_error( mysql_error(), E_USER_ERROR );
			$database_resource_array[$type_string] = $database_resource;
		}
		return $database_resource_array[$type_string];
	}
	
	private function _getDatabaseConfiguration( $type_string )
	{
		static $database_configuration_array = array();
		switch( $type_string ) {
		case "transaction":
			$function_name_array = array( $this->_master_db_function );
			break;
		case "master":
			$function_name_array = array( $this->_master_db_function );
			break;
		case "slave":
			$function_name_array = array( $this->_slave_db_function, $this->_master_db_function );
			break;
		default:
			trigger_error( "Bad type_string: ".$type_string, E_USER_ERROR );
		}
		
		while( ! array_key_exists( $type_string, $database_configuration_array ) ) {
			if( count( $function_name_array ) === 0 ) trigger_error( "No good function for setup database", E_USER_ERROR );
			$current_function_name = array_shift( $function_name_array );
			if( is_callable( $current_function_name, TRUE ) ) $database_configuration_array[$type_string] = call_user_func( $current_function_name );
		}
		return $database_configuration_array[$type_string];
	}
	
}

?>