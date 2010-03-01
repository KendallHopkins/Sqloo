<?php

/*
The MIT License

Copyright (c) 2009 Kendall Hopkins

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

require( "Sqloo/Query.php" );
require( "Sqloo/Table.php" );
require( "Sqloo/CacheInterface.php" );
require( "Sqloo/Exception.php" );
require( "Sqloo/Datatypes.php" );

class Sqloo
{
	
	//Database Type
	const DB_MYSQL = "mysql";
	const DB_PGSQL = "pgsql";
	
	
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
	
	//Datatypes
	const DATATYPE_BOOLEAN = 1;
	const DATATYPE_INTEGER = 2;
	const DATATYPE_FLOAT = 3;
	const DATATYPE_STRING = 4;
	const DATATYPE_FILE = 5;
	const DATATYPE_TIME = 6;
	const DATATYPE_OVERRIDE = 7;
	
	const SELECT_LOCK_NONE = 0;
	const SELECT_LOCK_SHARE = 1;
	const SELECT_LOCK_UPDATE = 2;
	
	//Query Types (private)
	/** @access private */
	const QUERY_MASTER = 1;
	/** @access private */
	const QUERY_SLAVE = 2;

	private $_master_db_function;
	private $_slave_db_function;
	private $_load_table_function;
	private $_list_all_tables_function;
	private $_caching_class;
	
	private $_table_array = array();
	private $_transaction_depth = 0;
	private $_transaction_cache_array = array();
	
	/**
	*	Construct Function
	*	@param string Function that is called when Sqloo needs a master db configuration, should return a random master configuration
	*	@param string Function that is called when Sqloo needs a slave db configuration, should return a random slave configuration
	*	@param string Function that is called when Sqloo needs to access a table that isn't loaded, allows dynamically loaded tables
	*	@param string Function that is called when Sqloo needs a list of all the available tables
	*	@returns Sqloo
	*/
	
	public function __construct( $master_db_function, $slave_db_function = NULL, $load_table_function = NULL, $list_all_tables_function = NULL, $caching_class = NULL ) 
	{
		$this->_master_db_function = $master_db_function;
		$this->_slave_db_function = $slave_db_function;
		$this->_load_table_function = $load_table_function;
		$this->_list_all_tables_function = $list_all_tables_function;
		if( ! is_null( $caching_class ) && ! ( $caching_class instanceof Sqloo_CacheInterface ) ) throw new Sqloo_Exception( "Caching class doesn't implement Sqloo_CacheInterface", Sqloo_Exception::BAD_INPUT );
		$this->_caching_class = $caching_class;
	}
	
	/**
	*	Destruct Function
	*
	*	@access private
	*/
	
	public function __destruct()
	{
		
		if( $this->_transaction_depth ) {
			do {
				$this->rollbackTransaction();				
			} while( $this->_transaction_depth );
			throw new Sqloo_Exception( "Transaction was not closed and was rolled back", Sqloo_Exception::BAD_INPUT );
		}
	}
	
	/**
	*	Prevent cloning
	*
	*	@access private
	*/
	
	private function __clone() { throw new Sqloo_Exception( "Clone is not allowed.", Sqloo_Exception::BAD_INPUT ); }
	
	/**
	*	Runs the $query_string on a database determinded by the params
	*	
	*	@param	string		Query string
	*	@param	bool		Set if the query can be run on a slave. If in a transaction, this is ignored.
	*	@param	array		Array of parameters, these will be escaped
	*	@return	resource	Resource from PDO::query().
	*/
	
	public function query( $query_string, array $parameters_array = NULL, $on_slave = FALSE )
	{
		$query_string .= ";";
		$statement_object = $this->prepare( $query_string, $on_slave );
		$this->execute( $statement_object, $parameters_array );
		return $statement_object;
	}
	
	/**
	*	Prepares the $query_string for the database determinded by the params
	*	
	*	@param	string		Query string
	*	@param	bool		Set if the query can be run on a slave. If in a transaction, this is ignored.
	*	@return	resource	Resource from PDO::prepare().
	*/
	
	public function prepare( $query_string, $on_slave = FALSE )
	{
		if( $this->_transaction_depth || ( ! $on_slave ) )
			$query_type = self::QUERY_MASTER;
		else
			$query_type = self::QUERY_SLAVE;
		$database_resource = $this->_getDatabaseResource( $query_type );
		
		try {
			$statement_object = $database_resource->prepare( $query_string );			
		} catch ( PDOException $exception ) {
			$statement_object = NULL;
		}
		if( ! $statement_object ) {
			throw new Sqloo_Exception( $exception->getMessage()."<br>\n".$query_string, hexdec( substr( $exception->getCode(), 0, 2 ) ) );
		}
		return $statement_object;
	}
	
	/**
	*	Runs the prepared query and escapes the parameter_array
	*	
	*	@param	resource	Resource from PDO::prepare().
	*	@param	array		Array of parameters, these will be escaped
	*/
	
	public function execute( $statement_object, array $parameters_array = NULL )
	{	
		if( ! $statement_object->execute( $parameters_array ) ) {
			$error_info = $statement_object->errorInfo();
			$driver_message = array_key_exists( 2, $error_info ) ? $error_info[2]."<br>\n" : "";
			$error_string = $driver_message.$statement_object->queryString;
			if( ! is_null( $parameters_array ) ) {
				$parameters_string = "";
				foreach( $parameters_array as $key => $value ) {
					$parameters_string .= "$key => $value, ";
				}
				$error_string .= "\narray(".substr( $parameters_string, 0, -2 )." )";
			}
			throw new Sqloo_Exception( $error_string, hexdec( substr( $error_info[0], 0, 2 ) ) );
		}
	}
	
	/**
	*	Returns if sqloo instance is in a transaction.
	*
	*	@return bool TRUE if in transaction, FALSE if not
	*/
	
	public function inTransaction()
	{
		return (bool)$this->_transaction_depth;
	}
	
	/**
	*	Opens a new transaction
	*/
	
	public function beginTransaction()
	{
		if( $this->_transaction_depth == 0 ) {
			$pdo = $this->_getDatabaseResource( self::QUERY_MASTER );
			$pdo->beginTransaction();
		} else {
			$savepoint_name = "s".$this->_transaction_depth;
			$this->query( "SAVEPOINT $savepoint_name" );		
		}
		
		//add another layer to the cache
		$this->_transaction_cache_array[$this->_transaction_depth] = array();
		$this->_transaction_depth++;
	}
	
	/**
	*	Rollbacks the transaction
	*/
	
	public function rollbackTransaction()
	{
		if( $this->_transaction_depth ) {
			$this->_transaction_depth--;
			
			if( $this->_transaction_depth == 0 ) {
				$pdo = $this->_getDatabaseResource( self::QUERY_MASTER )->rollBack();
			} else {
				$savepoint_name = "s".( $this->_transaction_depth );
				$this->query( "ROLLBACK TO SAVEPOINT $savepoint_name" );		
			}
			
			//throw away outer cache layer
			unset( $this->_transaction_cache_array[$this->_transaction_depth] );
		} else {
			throw new Sqloo_Exception( "not in a transaction, didn't rollback", Sqloo_Exception::BAD_INPUT );		
		}
	}
	
	/**
	*	Commits the transaction
	*/
	
	public function commitTransaction()
	{
		if( $this->_transaction_depth ) {
			$this->_transaction_depth--;
			
			//merge outer layer into the next layer
			$outer_cache_layer = $this->_transaction_cache_array[$this->_transaction_depth];
			unset( $this->_transaction_cache_array[$this->_transaction_depth] );
			
			if( $this->_transaction_depth > 0 ) {
				//merge layer down
				$this->_transaction_cache_array[$this->_transaction_depth - 1] = $outer_cache_layer + $this->_transaction_cache_array[$this->_transaction_depth - 1];
			} else {
				foreach( $outer_cache_layer as $key => $info ) {
					switch( $info["type"] ) {
						case "set": $this->_caching_class->set( $key, $info["data"] ); break;
						case "remove": $this->_caching_class->remove( $key ); break;
						default: throw new Exception( "bad type" ); break;
					}
				}
			}
		
			if( $this->_transaction_depth == 0 ) {
				$this->_getDatabaseResource( self::QUERY_MASTER )->commit();
			} else {
				$savepoint_name = "s".( $this->_transaction_depth );
				$this->query( "RELEASE SAVEPOINT $savepoint_name" );		
			}
		} else {
			throw new Sqloo_Exception( "not in a transaction, didn't commit", Sqloo_Exception::BAD_INPUT );		
		}
	}
	
	/**
	*	Get a new Sqloo_Query object
	*
	*	@returns Sqloo_Query
	*/
	
	public function newQuery()
	{
		return new Sqloo_Query( $this );
	}
	
	/**
	*	Insert a new row into a table
	*
	*	This function will set the columns "added" and "modified" to the current date if they exist in the table
	*
	*	@param	string	Name of the table
	*	@param	mixed	Array with the attributes to be inserted, IE array( "column_name1" => "value1", "column_name2" => "value2" ). It can also be a Sqloo_Query object, that the output columns correspond with the insert table columns. 
	*	@param	string	Insert modifier: insert_low_priority, insert_high_priority or insert_delayed
	*	@return	int		The id of the inserted row
	*/
	
	public function insert( $table_name, array $insert_array )
	{		
		$insert_string = "INSERT INTO \"".$table_name."\"\n";
		$table_column_array = $this->_getTable( $table_name )->column;
		$column_array = array_keys( $insert_array );
		$value_array = array();
		$escaped_value_array = array();
		
		//build query string
		foreach( array_values( $insert_array ) as $value ) {
			if( is_array( $value ) ) { //string inside an array is "safe"
				$escaped_value_array = $value[0];
			} else { //else it's dirty
				$escaped_value_array[] = "?";
				$value_array[] = $value;
			}
		}
		
		//check if we have a "magic" added/modifed field
		foreach( array( "added", "modified" ) as $magic_column ) {
			if( array_key_exists( $magic_column, $table_column_array ) &&
				! array_key_exists( $magic_column, $insert_array )
			) {
				$column_array[] = $magic_column;
				$escaped_value_array[] = "CURRENT_TIMESTAMP";
			}
		}
		
		$insert_string .= "(\"".implode( "\",\"", $column_array )."\") VALUES(".implode( ",", $escaped_value_array ).")";
		$this->query( $insert_string, $value_array );
		return $this->_getDatabaseResource( self::QUERY_MASTER )->lastInsertId( $table_name."_id_seq" );
	}
	
	public function insertQuery( $table_name, Sqloo_Query $query, array $parameter_array = NULL )
	{
		$insert_string = "INSERT INTO \"".$table_name."\"\n";
		if( is_string( $table_name ) ) { //quick check to make sure it's not a temp table
			$table_column_array = $this->_getTable( $table_name )->column;
		
			//check if we have a "magic" added/modifed field
			foreach( array( "added", "modified" ) as $magic_column ) {
				if( array_key_exists( $magic_column, $table_column_array ) && ( ! array_key_exists( $magic_column, $query->column ) ) ) 
					$query->column[$magic_column] = "CURRENT_TIMESTAMP";
			}
		}
		

		$insert_string .= 
			" (".implode( ",", array_keys( $query->column ) ).")\n".
			(string)$query; //transform object to string (function __toString)
		
		if( ! $parameter_array ) $parameter_array = array();
		$parameter_array += $query->getParameterArray();

		$this->query( $insert_string, $parameter_array );
		
		return $this->_getDatabaseResource( self::QUERY_MASTER )->lastInsertId( $table_name."_id_seq" );
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
	
	public function update( $table_name, array $update_array, $id_array_or_where_string )
	{
		/* create update string */
		$update_string = 
			"UPDATE \"".$table_name."\"\n".
			"SET ";
		
		//check if we have a "magic" modifed field
		if( array_key_exists( "modified", $this->_getTable($table_name)->column ) )
			$update_string .= "modified=CURRENT_TIMESTAMP,";
		
		//add other fields
		foreach( array_keys( $update_array ) as $key )
			$update_string .= "\"".$key."\"=?,";
		
		$update_string = substr( $update_string, 0, -1 )."\n";
		if( is_array( $id_array_or_where_string ) ) {
			$id_array = $id_array_or_where_string;
			$id_array_count = count( $id_array );
			if( ! $id_array_count ) throw new Sqloo_Exception( "id_array of 0 size", Sqloo_Exception::BAD_INPUT );
			$update_string .= "WHERE id IN (".implode( ",", array_fill( 0, count( $id_array ), "?" ) ).")\n";
			$this->query( $update_string, array_merge( array_values( $update_array ), array_values( $id_array ) ) );
		} else if( is_string( $id_array_or_where_string ) ) {
			$where_string = $id_array_or_where_string;
			$update_string .= "WHERE ".$where_string;
			$this->query( $update_string, array_values( $update_array ) );
		} else {
			throw new Sqloo_Exception( "bad input type", Sqloo_Exception::BAD_INPUT );
		}
	}
	
	/**
	*	Delete a list of rows
	*
	*	@param	string	Name of the table
	*	@param	array	Array of positive int values that are the id's for the rows you want to delete
	*/
	
	public function delete( $table_name, array $id_array )
	{
		if ( ! $id_array ) throw new Sqloo_Exception( "id_array of 0 size", Sqloo_Exception::BAD_INPUT );
		$delete_string =
			"DELETE FROM \"".$table_name."\"\n".
			"WHERE id IN (".implode( ",", array_fill( 0, count( $id_array ) , "?" ) ).")";
		$this->query( $delete_string, array_values( $id_array ) );
	}
	
	public function deleteWhere( $table_name, $where_string, array $parameters_array = NULL )
	{
		if( is_string( $where_string ) ) {
			$delete_string = 
				"DELETE FROM \"".$table_name."\"\n".
				"WHERE ".$where_string;
			$this->query( $delete_string, $parameters_array );
		} else {
			throw new Sqloo_Exception( "bad input type", Sqloo_Exception::BAD_INPUT );
		}
	}
	
	/**
	*	Make a union from multiable Sqloo_Query objects
	*
	*	All Sqloo_Query objects must have the same output column names for this to work
	*
	*	@param	array		Array of Sqloo_Query objects
	*	@return	Sqloo_Query	Sqloo_Query object preloaded with a union of the Sqloo_Query objects in $array_of_queries
	*/
	
	public function union( array $array_of_queries )
	{
		if( ! $array_of_queries ) throw new Sqloo_Exception( "No queries in array", Sqloo_Exception::BAD_INPUT );
		$query_union = new Sqloo_Query( $this, $array_of_queries );
		$first_query = array_shift( $array_of_queries );
		foreach( $first_query->column as $column_name => $column_value ) {
			$query_union->column[$column_name] = $column_name;
		}
		return $query_union;
	}
	
	/**
	*	Make a new Sqloo_Table Object and return it.
	*	
	*	@param	string		New table name
	*	@return	Sqloo_Table	Empty Sqloo_Table object
	*/
	
	public function newTable( $table_name )
	{
		return $this->_table_array[ $table_name ] = new Sqloo_Table( $table_name );
	}
	
	/**
	*	Make a new Sqloo_Table Object, setup the parents and return it.
	*	
	*	@param	Sqloo_Table	Parent table 1
	*	@param	Sqloo_Table	Parent table 2
	*	@return	Sqloo_Table	N:M Sqloo_Table object, used for NMJoins
	*/
	
	public function newNMTable( $sqloo_table_name_1, $sqloo_table_name_2 )
	{
		$many_to_many_table = $this->newTable( self::computeNMTableName( $sqloo_table_name_1, $sqloo_table_name_2 ) );
		$many_to_many_table->parent = array(
			$sqloo_table_name_1 => array(
				Sqloo::PARENT_TABLE_NAME => $sqloo_table_name_1, 
				Sqloo::PARENT_ALLOW_NULL => FALSE, 
				Sqloo::PARENT_ON_DELETE => Sqloo::ACTION_CASCADE, 
				Sqloo::PARENT_ON_UPDATE => Sqloo::ACTION_CASCADE
			),
			$sqloo_table_name_2 => array(
				Sqloo::PARENT_TABLE_NAME => $sqloo_table_name_2, 
				Sqloo::PARENT_ALLOW_NULL => FALSE, 
				Sqloo::PARENT_ON_DELETE => Sqloo::ACTION_CASCADE, 
				Sqloo::PARENT_ON_UPDATE => Sqloo::ACTION_CASCADE
			)
		);
		return $many_to_many_table;
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
		return ( (string)$table_name_1 < (string)$table_name_2 ) ? $table_name_1."-".$table_name_2 : $table_name_2."-".$table_name_1;
	}
	
	/**
	*	Get's the datatype for a variable type.
	*
	*	@param	array	key-value attrubute array
	*	@return string	type string
	*/
	
	public function getTypeString( array $attributes_array )
	{
		switch( $this->getDBType() ) {
		case self::DB_MYSQL: 
			require_once( "Sqloo/Datatypes/Mysql.php" );
			return Sqloo_Datatypes_Mysql::getTypeString( $attributes_array );
			break;
		case self::DB_PGSQL: 
			require_once( "Sqloo/Datatypes/Postgres.php" );
			return Sqloo_Datatypes_Postgres::getTypeString( $attributes_array );
			break;
		default: throw new Sqloo_Exception( "Unknown database: ".$database_configuration["type"], Sqloo_Exception::BAD_INPUT );
		}
	}
	
	/**
	*	Get's the function name for a database type.
	*
	*	@param	string	
	*	@return string	type string
	*/
	
	public function getFunction( $function, $content )
	{
		switch( $this->getDBType() ) {
		case self::DB_MYSQL: 
			require_once( "Sqloo/Datatypes/Mysql.php" );
			return Sqloo_Datatypes_Mysql::getFunction( $function, $content );
			break;
		case self::DB_PGSQL: 
			require_once( "Sqloo/Datatypes/Postgres.php" );
			return Sqloo_Datatypes_Postgres::getFunction( $function, $content );
			break;
		default: throw new Sqloo_Exception( "Unknown database: ".$database_configuration["type"], Sqloo_Exception::BAD_INPUT );
		}
	}
	
	/**
	*	Get's the database type
	*
	*	@return string	type string
	*/
	public function getDBType()
	{
		$database_configuration = $this->_getDatabaseConfiguration( self::QUERY_MASTER );
		return $database_configuration["type"];
	}
	
	/**
	*	Checks and correct the database schema to match the table schema setup in code.
	*
	*	@return	string	Log of the queries run.
	*/
	
	public function checkSchema()
	{
		switch( $this->getDBType() ) {
		case self::DB_MYSQL: $file_name = "Mysql"; break;
		case self::DB_PGSQL: $file_name = "Postgres"; break;
		default: throw new Sqloo_Exception( "Bad database type: ".$database_configuration["type"], Sqloo_Exception::BAD_INPUT ); break;
		}

		require_once( "Sqloo/Schema.php" );
		require_once( "Sqloo/Schema/".$file_name.".php" );
		$class_name = "Sqloo_Schema_".$file_name;
		$schema = new $class_name( $this );
		return $schema->checkSchema();
	}
	
	/* Transactional Caching */
	
	public function cacheSet( $key, $data )
	{
		if( $this->_transaction_depth > 0 ) {
			$this->_transaction_cache_array[$this->_transaction_depth - 1][$key] = array( "type" => "set", "data" => $data );
		} else {
			$this->_caching_class->set( $key, $data );
		}
	}
	
	public function cacheGet( $key, &$data )
	{
		//try to find it in the cache transaction layers
		for( $current_cache_layer = $this->_transaction_depth - 1; $current_cache_layer >= 0; $current_cache_layer-- ) {
			if( array_key_exists( $key, $this->_transaction_cache_array[$current_cache_layer] ) ) {
				switch( $this->_transaction_cache_array[$current_cache_layer][$key]["type"] ) {
					case "set":
						$data = $this->_transaction_cache_array[$current_cache_layer][$key]["data"];
						return TRUE;
						break;
					case "remove":
						return FALSE;
						break;
					default:
						throw new Exception( "bad type" );
						break;
				}
			}
		}
		
		return $this->_caching_class->get( $key, $data );
	}
	
	public function cacheRemove( $key )
	{
		if( $this->_transaction_depth > 0 ) {
			$this->_transaction_cache_array[$this->_transaction_depth - 1][$key] = array( "type" => "remove" );
		} else {
			$this->_caching_class->remove( $key );
		}
	}
	
	
	/* Private Functions */
	
	public function _getTable( $table_name )
	{
		if( ! array_key_exists( $table_name, $this->_table_array ) )
			$this->_loadTable( $table_name );
		return $this->_table_array[$table_name];
	}
	
	private function _loadTable( $table_name )
	{
		if( ! array_key_exists( $table_name, $this->_table_array ) ) {
			if( $this->_load_table_function && is_callable( $this->_load_table_function ) )
				call_user_func( $this->_load_table_function, $table_name, $this );
			if( ! array_key_exists( $table_name, $this->_table_array ) )
				throw new Sqloo_Exception( "could not load table: ".$table_name, Sqloo_Exception::BAD_INPUT );
		}
	}
	
	public function _getAllTables()
	{
		static $all_tables_loaded = FALSE;
		if( ! $all_tables_loaded ) {
			if( is_callable( $this->_list_all_tables_function ) )
				foreach( call_user_func( $this->_list_all_tables_function ) as $table_name )
					$this->_loadTable( $table_name );
			
			$all_tables_loaded = TRUE;
		}
		return $this->_table_array;
	}
	
	public function _getDatabaseResource( $type_id )
	{
		static $database_object_array = array();
		if( ! array_key_exists( $type_id, $database_object_array ) ) {
			$configuration_array = $this->_getDatabaseConfiguration( $type_id );
			try {
				$database_object_array[$type_id] = new PDO( 
					$configuration_array["type"].":dbname=".$configuration_array["name"].";host=".$configuration_array["address"], 
					$configuration_array["username"], 
					$configuration_array["password"], 
					array(
						PDO::ATTR_PERSISTENT => TRUE,
						PDO::ATTR_TIMEOUT => 15,
						PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='POSTGRESQL'"
					) 
				);		
			} catch ( PDOException $exception ) {
				throw new Sqloo_Exception( $exception->getMessage(), Sqloo_Exception::CONNECTION_FAILED );
			}
			
		}
		return $database_object_array[$type_id];
	}
	
	public function _getDatabaseConfiguration( $type_id )
	{
		static $database_configuration_array = array();
		if( ! array_key_exists( $type_id, $database_configuration_array ) ) {
			switch( $type_id ) {
			case self::QUERY_MASTER:
				$function_name_array = array( $this->_master_db_function );
				break;
			case self::QUERY_SLAVE:
				$function_name_array = array( $this->_slave_db_function, $this->_master_db_function );
				break;
			default:
				throw new Sqloo_Exception( "Bad type_id: ".$type_string, Sqloo_Exception::BAD_INPUT );
			}
			
			do {
				if( ! count( $function_name_array ) ) throw new Sqloo_Exception( "No good function for setup database", Sqloo_Exception::BAD_INPUT );
				
				$function_name = array_shift( $function_name_array );
				if( $function_name ) {
					if( is_callable( $function_name ) )
						$database_configuration_array[$type_id] = call_user_func( $function_name );
					else
						throw new Sqloo_Exception( "Non-existing function was referenced: ".$function_name );
				}
			} while( ! array_key_exists( $type_id, $database_configuration_array ) );
		}
		return $database_configuration_array[$type_id];
	}
	
	public function quote( $variable )
	{
		if( is_bool( $variable ) ) {
			return $variable ? "TRUE" : "FALSE";
		} else if( is_int( $variable ) ) {
			return $variable;
		} else if( is_float( $variable ) ) {
			return $variable;
		} else if( is_null( $variable ) ) {
			return "NULL";
		} else if( is_string( $variable ) ) {
			return $this->_getDatabaseResource( self::QUERY_MASTER )->quote( $variable );
		} else {
			throw new Sqloo_Exception( "Bad variable type", Sqloo_Exception::BAD_INPUT );
		}
	}
	
}

?>