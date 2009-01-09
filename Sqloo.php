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
	
	//Column Attributes
	const COLUMN_DATA_TYPE = "column_data_type"; //string
	const COLUMN_ALLOW_NULL = "allow_null"; //bool
	const COLUMN_DEFAULT_VALUE = "default_value"; //mixed, (string, int, float, NULL)
	const COLUMN_PRIMARY_KEY = "column_primary_key"; //bool
	const COLUMN_AUTO_INCREMENT = "column_auto_increment"; //bool

	//Parent Attributes
	const PARENT_TABLE_NAME = "parent_table_name"; //string
	const PARENT_ALLOW_NULL = "allow_null"; //bool, alias to COLUMN_ALLOW_NULL
	const PARENT_DEFAULT_VALUE = "default_value"; //mixed, (string, int, float, NULL), alias to COLUMN_DEFAULT_VALUE
	const PARENT_ON_DELETE = "parent_on_delete"; //action - see below
	const PARENT_ON_UPDATE = "parent_on_update"; //action - see below
			
	//Index Attributes
	const INDEX_COLUMN_ARRAY = "index_column_array"; //array
	const INDEX_UNIQUE = "index_unique"; //bool
	
	//Actions
	const ACTION_RESTRICT = "RESTRICT";
	const ACTION_CASCADE = "CASCADE";
	const ACTION_SET_NULL = "SET NULL";
	const ACTION_NO_ACTION = "NO ACTION";
	
	//Order Types
	const ORDER_ASCENDING = "ASC";
	const ORDER_DESCENDING = "DESC";
	
	//Join Types
	const JOIN_INNER = "INNER";
	const JOIN_OUTER = "OUTER";
	const JOIN_LEFT = "LEFT";
	const JOIN_RIGHT = "RIGHT";
	
	//Insert Modifiers
	const INSERT_LOW_PRIORITY = "LOW_PRIORITY";
	const INSERT_HIGH_PRIORITY = "HIGH_PRIORITY";
	const INSERT_DELAYED = "DELAYED";

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
	
	/**
	*	Prevent cloning
	*
	*	@access private
	*/
	
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
	*	@param	mixed	Array with the attributes to be inserted, IE array( "column_name1" => "value1", "column_name2" => "value2" ). It can also be a query, that the output columns are inserted. 
	*	@param	string	Insert modifier: insert_low_priority, insert_high_priority or insert_delayed
	*	@return	int		The id of the inserted row
	*/
	
	public function insert( $table_name, $insert_array_or_query, $modifier = NULL )
	{		
		$insert_string = "INSERT ";
		if( $modifier !== NULL ) $insert_string .= $modifier." ";
		$insert_string .= "INTO `".$table_name."`\n";
		if( is_array( $insert_array_or_query ) ) {
			$table_column_array = $this->_getTable($table_name)->column;
			//check if we have a "magic" added/modifed field
			if( array_key_exists( "added", $table_column_array ) ) $insert_array_or_query["added"] = "CURRENT_TIMESTAMP";
			if( array_key_exists( "modified", $table_column_array ) ) $insert_array_or_query["modified"] = "CURRENT_TIMESTAMP";
			$insert_string .= "SET ".self::processKeyValueArray( $insert_array_or_query )."\n";	
		} else if( is_string( $insert_array_or_query ) ) {
			$insert_string .= $insert_array_or_query;
		} else if( is_object( $insert_array_or_query ) ) {
			if( get_class( $insert_array_or_query ) === "Sqloo_Query" ) {
				//check if we have a "magic" added/modifed field
				if( array_key_exists( "added", $table_column_array ) ) $insert_array_or_query->column = array_merge( $insert_array_or_query->column, array( "added" => "CURRENT_TIMESTAMP" ) );
				if( array_key_exists( "modified", $table_column_array ) ) $insert_array_or_query->column = array_merge( $insert_array_or_query->column, array( "modified" => "CURRENT_TIMESTAMP" ) );
			}
			$insert_string .= $insert_array_or_query;
		} else {
			trigger_error( "bad input type", E_USER_ERROR );
		}
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
	*	@param	mixed	Array of positive int values that are the id's for the rows you want to update or where string
	*/
	
	public function update( $table_name, $update_array, $id_array_or_where_string )
	{			
		//check if we have a "magic" modifed field
		if( array_key_exists( "modified", $this->_getTable($table_name)->column ) ) $update_array["modified"] = "CURRENT_TIMESTAMP";
				
		/* create update string */
		$update_string = "UPDATE `".$table_name."`\n";
		$update_string .= "SET ".self::processKeyValueArray( $update_array )."\n";
		
		if( is_array( $id_array_or_where_string ) ) {
			$id_array_count = count( $id_array );
			if( $id_array_count === 0 ) trigger_error( "id_array of 0 size", E_USER_ERROR );
			$update_string .= "WHERE id IN (".self::processValueArray( $id_array )."(\n";				
			$update_string .= "LIMIT ".$id_array_count."\n";
		} else if( is_string( $id_array_or_where_string ) ) {
			$update_string .= "WHERE ".$id_array_or_where_string.";";
		} else {
			trigger_error( "bad input type", E_USER_ERROR );
		}
		$this->query( $update_string );
	}
	
	/**
	*	Delete a list of rows
	*
	*	@param	string	Name of the table
	*	@param	array	Array of positive int values that are the id's for the rows you want to delete or where string
	*/
	
	public function delete( $table_name, $id_array_or_where_string )
	{
		$delete_string = "DELETE FROM `".$table_name."`\n";
		if( is_array( $id_array_or_where_string ) ) {
			$id_array_count = count( $id_array );
			if ( $id_array_count === 0 ) trigger_error( "id_array of 0 size", E_USER_ERROR );
			$delete_string .= "WHERE id IN (".self::processValueArray( $id_array ).")\n";
			$delete_string .= "LIMIT ".$id_array_count.";";
		} else if( is_string( $id_array_or_where_string ) ) {
			$delete_string .= "WHERE ".$id_array_or_where_string.";";
		} else {
			trigger_error( "bad input type", E_USER_ERROR );
		}

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
				Sqloo::PARENT_TABLE_NAME => $sqloo_table_class_1->name, 
				Sqloo::PARENT_ALLOW_NULL => FALSE, 
				Sqloo::PARENT_ON_DELETE => Sqloo::ACTION_CASCADE, 
				Sqloo::PARENT_ON_UPDATE => Sqloo::ACTION_CASCADE
			),
			$sqloo_table_class_2->name => array(
				Sqloo::PARENT_TABLE_NAME => $sqloo_table_class_2->name, 
				Sqloo::PARENT_ALLOW_NULL => FALSE, 
				Sqloo::PARENT_ON_DELETE => Sqloo::ACTION_CASCADE, 
				Sqloo::PARENT_ON_UPDATE => Sqloo::ACTION_CASCADE
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
	
	/**
	*	Escapes values for a query
	*	
	*	@param	mixed	The value to be escaped, if a class object is passed it will attempt to be transformed into a string.
	*	@return	string	Processed value
	*/
	
	static public function processVariable( $value )
	{
		if( is_bool( $value ) ) return "'".(int)$value."'";
		else if( is_null( $value ) ) return "NULL";
		else if( is_numeric( $value ) ) return $value; 
		else if( is_string( $value ) ) {
			switch( $value ) {
			case "CURRENT_TIMESTAMP": return $value;
			default: return "'".mysql_escape_string( $value )."'";
			}
		} else if( is_object( $value ) ) return "(".(string)$value.")";
		else trigger_error( "bad imput: ".var_export( $value, TRUE ), E_USER_ERROR );
	}
	
	/**
	*	Concatinate and process each item in the array with the commas
	*
	*	@param	array 	value array
	*	@return string	concatinated and processed output string
	*/
	
	static public function processValueArray( $value_array )
	{
		$string = "";
		foreach( $value_array as $value ) $string .= self::processVariable( $value ).",";
		return rtrim( $string, "," );
	}
	
	/**
	*	Concatinate and process each item in the array with the commas
	*
	*	@param	array 	key value array
	*	@return string	concatinated and processed output string
	*/
	
	static public function processKeyValueArray( $key_value_array )
	{
		$string = "";
		foreach( $key_value_array as $key => $value ) $string .= $key."=".self::processVariable( $value ).",";
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