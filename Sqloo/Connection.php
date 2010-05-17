<?php

/*
The MIT License

Copyright (c) 2010 Kendall Hopkins

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

require_once( "Query.php" );
require_once( "Union.php" );
require_once( "CacheInterface.php" );
require_once( "Exception.php" );

class Sqloo_Connection
{
	
	//Database Type
	const DB_MYSQL = "mysql";
	const DB_PGSQL = "pgsql";
	
	/**
	*	Construct Function
	*	@param callback Function that is called when Sqloo needs a master db configuration, should return a random master configuration
	*	@param callback Function that is called when Sqloo needs a slave db configuration, should return a random slave configuration
	*	@param class 	Class that implements Sqloo_CacheInterface
	*	@returns Sqloo
	*/
	
	public function __construct( $master_db_function, $slave_db_function = NULL, $caching_class = NULL ) 
	{
		if( ! is_null( $caching_class ) && ! ( $caching_class instanceof Sqloo_CacheInterface ) )
			throw new Sqloo_Exception( "Caching class doesn't implement Sqloo_CacheInterface", Sqloo_Exception::BAD_INPUT );
	
		$this->_master_db_function = $master_db_function;
		$this->_slave_db_function = $slave_db_function;
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
		$query_type = ( $this->_transaction_depth || ( ! $on_slave ) ) ? self::QUERY_MASTER : self::QUERY_SLAVE;
		$database_resource = $this->_getDatabaseResource( $query_type );
		
		try {
			$statement_object = $database_resource->prepare( $query_string );			
		} catch ( PDOException $exception ) {
			$statement_object = NULL;
		}
		if( ! $statement_object ) {
			throw new Sqloo_Exception( $exception->getMessage().PHP_EOL.$query_string, hexdec( substr( $exception->getCode(), 0, 2 ) ) );
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
		if( ! is_null( $parameters_array ) ) {
			foreach( $parameters_array as $key => $value ) {
				if( is_null( $value ) ) {
					$type = PDO::PARAM_NULL;
				} else if( is_bool( $value ) ) {
					$type = PDO::PARAM_BOOL;
				} else if( is_int( $value ) ) {
					$type = PDO::PARAM_INT;
				} else {
					$type = PDO::PARAM_STR;
				}
				if( is_int( $key ) ) $key++;
				$statement_object->bindValue( $key, $value, $type );	
			}
		}
		
		if( ! $statement_object->execute() ) {
			$error_info = $statement_object->errorInfo();
			$driver_message = array_key_exists( 2, $error_info ) ? $error_info[2].PHP_EOL : "";
			$error_string = $driver_message.$statement_object->queryString;
			if( ! is_null( $parameters_array ) ) {
				$error_string .= PHP_EOL.var_export( $parameters_array, TRUE );
			}
			throw new Sqloo_Exception( $error_string, hexdec( substr( $error_info[0], 0, 2 ) ) );
		}
	}
	
	/**
	*	Returns the transaction depth of the connection
	*
	*	@return int transaction depth
	*/
	
	public function getTransactionDepth()
	{
		return $this->_transaction_depth;
	}
	
	/**
	*	Opens a new transaction
	*/
	
	public function beginTransaction()
	{
		if( $this->_transaction_depth == 0 ) {
			$this->_getDatabaseResource( self::QUERY_MASTER )->beginTransaction();
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
		if( ! $this->_transaction_depth )
			throw new Sqloo_Exception( "not in a transaction, didn't rollback", Sqloo_Exception::BAD_INPUT );
				
		$this->_transaction_depth--;
		
		if( $this->_transaction_depth > 0 ) {
			$savepoint_name = "s".( $this->_transaction_depth );
			$this->query( "ROLLBACK TO SAVEPOINT $savepoint_name" );	
		} else {
			$this->_getDatabaseResource( self::QUERY_MASTER )->rollBack();
		}
		
		//throw away outer cache layer
		unset( $this->_transaction_cache_array[$this->_transaction_depth] );
	}
	
	/**
	*	Commits the transaction
	*/
	
	public function commitTransaction()
	{
		if( ! $this->_transaction_depth )
			throw new Sqloo_Exception( "not in a transaction, didn't commit", Sqloo_Exception::BAD_INPUT );	
		
		$this->_transaction_depth--;
		
		//merge outer layer into the next layer
		$outer_cache_layer = $this->_transaction_cache_array[$this->_transaction_depth];
		unset( $this->_transaction_cache_array[$this->_transaction_depth] );
		
		if( $this->_transaction_depth > 0 ) {
			//merge cache layer down
			$this->_transaction_cache_array[$this->_transaction_depth - 1] = $outer_cache_layer + $this->_transaction_cache_array[$this->_transaction_depth - 1];
			$savepoint_name = "s".( $this->_transaction_depth );
			$this->query( "RELEASE SAVEPOINT $savepoint_name" );
		} else {
			//commit cache layer to cache
			foreach( $outer_cache_layer as $key => $info ) {
				switch( $info[self::_CACHE_INDEX_TYPE] ) {
					case self::_CACHE_TYPE_SET: $this->_caching_class->set( $key, $info[self::_CACHE_INDEX_DATA] ); break;
					case self::_CACHE_TYPE_REMOVE: $this->_caching_class->remove( $key ); break;
					default: throw new Exception( "bad type" );
				}
			}
			$this->_getDatabaseResource( self::QUERY_MASTER )->commit();
		}
	}
	
	/* Transactional Caching */
	
	public function cacheSet( $key, $data )
	{
		if( $this->_transaction_depth > 0 ) {
			$this->_transaction_cache_array[$this->_transaction_depth - 1][$key] = array(
				self::_CACHE_INDEX_TYPE => self::_CACHE_TYPE_SET,
				self::_CACHE_INDEX_DATA => $data
			);
		} else {
			$this->_caching_class->set( $key, $data );
		}
	}
	
	public function cacheGet( $key, &$data )
	{
		//try to find it in the cache transaction layers, before hitting the main cache
		for( $current_cache_layer = $this->_transaction_depth - 1; $current_cache_layer >= 0; $current_cache_layer-- ) {
			if( array_key_exists( $key, $this->_transaction_cache_array[$current_cache_layer] ) ) {
				switch( $this->_transaction_cache_array[$current_cache_layer][$key][self::_CACHE_INDEX_TYPE] ) {
					case self::_CACHE_TYPE_SET:
						$data = $this->_transaction_cache_array[$current_cache_layer][$key][self::_CACHE_INDEX_DATA];
						return TRUE;
						break;
						
					case self::_CACHE_TYPE_REMOVE:
						return FALSE;
						break;
						
					default:
						throw new Exception( "bad type" );
				}
			}
		}
		
		return $this->_caching_class->get( $key, $data );
	}
	
	public function cacheRemove( $key )
	{
		if( $this->_transaction_depth > 0 ) {
			$this->_transaction_cache_array[$this->_transaction_depth - 1][$key] = array(
				self::_CACHE_INDEX_TYPE => self::_CACHE_TYPE_REMOVE
			);
		} else {
			$this->_caching_class->remove( $key );
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
		$column_array = array();
		$param_value_array = array();
		$placeholder_value_array = array();
		
		//build query string
		foreach( $insert_array as $column_name => $value ) {
			$column_array[] = $column_name;
			if( is_array( $value ) ) { //string inside an array is "safe"
				$placeholder_value_array = $value[0];
			} else { //else it's dirty
				$placeholder_value_array[] = "?";
				$param_value_array[] = $value;
			}
		}
		
		$insert_string .=
			"(\"".implode( "\",\"", $column_array )."\")\n".
			"VALUES(".implode( ",", $placeholder_value_array ).")";
		
		$this->query( $insert_string, $param_value_array );
		
		return $this->_getDatabaseResource( self::QUERY_MASTER )->lastInsertId( $table_name."_id_seq" );
	}
	
	public function insertQuery( $table_name, Sqloo_Query $query, array $parameter_array = NULL )
	{
		$insert_string =
			"INSERT INTO \"".$table_name."\"\n". 
			"(".implode( ",", array_keys( $query->column ) ).")\n".
			(string)$query; //transform object to string (function __toString)
		
		if( ! $parameter_array ) $parameter_array = array();
		$parameter_array += $query->getParameterArray();

		$this->query( $insert_string, $parameter_array );		
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
	
	public function update( $table_name, array $update_array, array $id_array )
	{
		if( ! $update_array )
			throw new Sqloo_Exception( "update_array of 0 size", Sqloo_Exception::BAD_INPUT );
			
		if( ! $id_array )
			throw new Sqloo_Exception( "update_array of 0 size", Sqloo_Exception::BAD_INPUT );
		
		/* create update string */
		$update_string = 
			"UPDATE \"".$table_name."\"\n".
			"SET ";
		
		//add other fields
		foreach( array_keys( $update_array ) as $key )
			$update_string .= "\"".$key."\"=?,";
		
		$update_string = substr( $update_string, 0, -1 )."\n";
		$id_array_count = count( $id_array );
		$update_string .= "WHERE id IN (".implode( ",", array_fill( 0, count( $id_array ), "?" ) ).")\n";
		
		$this->query( $update_string, array_merge( array_values( $update_array ), array_values( $id_array ) ) );
	}
	
	/**
	*	Update a list of rows with new values
	*
	*	This function will set the column "modified" to the current date if they exist in the table
	*
	*	@param	string	Name of the table
	*	@param	array	Array with the attributes to be modified, IE array( "column_name1" => "value1", "column_name2" => "value2" )
	*	@param	string	Where string
	*/
	
	public function updateWhere( $table_name, array $update_array, $where_string )
	{
		if( ! $update_array )
			throw new Sqloo_Exception( "update_array of 0 size", Sqloo_Exception::BAD_INPUT );

		/* create update string */
		$update_string = 
			"UPDATE \"".$table_name."\"\n".
			"SET ";
		
		//add other fields		
		foreach( array_keys( $update_array ) as $key )
			$update_string .= "\"".$key."\"=?,";
		
		$update_string =
			substr( $update_string, 0, -1 )."\n".
			"WHERE ".$where_string;
		
		$this->query( $update_string, array_values( $update_array ) );
	}
	
	/**
	*	Delete a list of rows
	*
	*	@param	string	Name of the table
	*	@param	array	Array of positive int values that are the id's for the rows you want to delete
	*/
	
	public function delete( $table_name, array $id_array )
	{
		if ( ! $id_array )
			throw new Sqloo_Exception( "id_array of 0 size", Sqloo_Exception::BAD_INPUT );
		
		$delete_string =
			"DELETE FROM \"".$table_name."\"\n".
			"WHERE id IN (".implode( ",", array_fill( 0, count( $id_array ) , "?" ) ).")";
			
		$this->query( $delete_string, array_values( $id_array ) );
	}
	
	public function deleteWhere( $table_name, $where_string, array $parameters_array = NULL )
	{
		if( ! is_string( $where_string ) )
			throw new Sqloo_Exception( "bad input type", Sqloo_Exception::BAD_INPUT );
		
		$delete_string = 
			"DELETE FROM \"".$table_name."\"\n".
			"WHERE ".$where_string;
		
		$this->query( $delete_string, $parameters_array );
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
		if( ! $array_of_queries )
			throw new Sqloo_Exception( "No queries in array", Sqloo_Exception::BAD_INPUT );
				
		return new Sqloo_Union( $this, $array_of_queries );
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
	
	/* Privates */
	
	//Query Types (private)
	/** @access private */
	const QUERY_MASTER = 1;
	/** @access private */
	const QUERY_SLAVE = 2;
	
	//Cache Array Indexes (private)
	/** @access private */
	const _CACHE_INDEX_TYPE = 3;
	/** @access private */
	const _CACHE_INDEX_DATA = 4;
	
	//Cache Types (private)
	/** @access private */
	const _CACHE_TYPE_SET = 5;
	/** @access private */
	const _CACHE_TYPE_REMOVE = 6;

	private $_master_db_function;
	private $_slave_db_function;
	private $_caching_class;
	
	private $_table_array = array();
	private $_transaction_depth = 0;
	private $_transaction_cache_array = array();
	
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
						PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY,
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