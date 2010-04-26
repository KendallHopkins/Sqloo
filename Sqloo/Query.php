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

require_once( "Query/Table.php" );

class Sqloo_Query implements Iterator
{
	//Order Types
	const ORDER_ASCENDING = "ASC";
	const ORDER_DESCENDING = "DESC";
	
	//Join Types
	const JOIN_INNER = "INNER";
	const JOIN_OUTER = "OUTER";
	const JOIN_LEFT = "LEFT";
	const JOIN_RIGHT = "RIGHT";
	
	//Locking Types
	const SELECT_LOCK_NONE = 0;
	const SELECT_LOCK_SHARE = 1;
	const SELECT_LOCK_UPDATE = 2;
	
	private $_sqloo_connection;
	
	/* Query Data */
	protected $_root_table_class = NULL;
	protected $_query_data = array(
		"column" => NULL,
		"where" => array(),
		"order" => array(),
		"group" => array(),
		"having" => array(),
		"limit" => NULL,
		"page" => NULL,
		"offset" => NULL,
		"distinct" => FALSE,
		"lock" => self::SELECT_LOCK_NONE,
		"lock_wait" => TRUE,
		"allow_slave" => FALSE
	);
	private $_statement_object = NULL;
	public $parameter_array = array();
 
	
	/**
	*	Construct function
	*
	*	This should never be called directly, look at Sqloo->newQuery()
	*	@param	Sqloo
	*/
	
	public function __construct( Sqloo_Connection $sqloo_connection )
	{
		$this->_sqloo_connection = $sqloo_connection;
	}
	
	/**
	*	Destruct function
	*
	*	Releases memory from query
	*
	*	@access private
	*/
	
	public function __destruct()
	{
		$this->_releaseStatementObject();
	}
	
	/**
	*	Class to string
	*
	*	Just calls $this->getQueryString()
	*	
	*	@return	string 	Query string
	*/
	
	public function __toString()
	{
		return $this->getQueryString();
	}
		
	/**
	*	Set a root table
	*
	*	Only done once
	*
	*	@param	string				The root table name
	*	@return	Sqloo_Query_Table	A Sqloo_Query_Table object
	*/
	
	public function table( $table_name )
	{
		if( $this->_root_table_class )
			throw new Sqloo_Exception( "Root table is already set", Sqloo_Exception::BAD_INPUT );
		
		$this->_root_table_class = new Sqloo_Query_Table( $table_name );
		return $this->_root_table_class;
	}
	
	/**
	*	Get root table
	*
	*	@return	Sqloo_Query_Table	A Sqloo_Query_Table object
	*/
	
	public function getTable()
	{
		if( ! $this->_root_table_class )
			throw new Sqloo_Exception( "Root table not set", Sqloo_Exception::BAD_INPUT );
		
		return $this->_root_table_class;
	}
	
	/**
	*	Returns the current value of an attribute of the query
	*
	*	Will throw exception if $key is bad
	*	
	*	@param	string	The attribute key
	*	@return	mixed	The attribute value
	*/
	
	public function & __get( $key )
	{
		if( ! array_key_exists( $key, $this->_query_data ) )
			throw new Sqloo_Exception( "Bad key: $key", Sqloo_Exception::BAD_INPUT );
		
		if( $key !== "parameter_array" )
			$this->_releaseStatementObject();		
		
		return $this->_query_data[$key];
	}
	
	/**
	*	Sets the current value of an attribute of the query
	*
	*	Will throw exception if $key is bad
	*	
	*	@param	string	attribute key
	*	@param	mixed	attribute value
	*/
	
	public function __set( $key, $value )
	{
		if( ! array_key_exists( $key, $this->_query_data ) )
			throw new Sqloo_Exception( "Bad key: $key", Sqloo_Exception::BAD_INPUT );
		
		if( $key !== "parameter_array" )
			$this->_releaseStatementObject();
		
		$this->_query_data[$key] = $value;
	}
	
	/**
	*	Function to add column
	*	
	*	@param string	output name string
	*	@param string	expression string
	*/
	
	public function column( $output_name, $expression )
	{
		$this->column[$output_name] = $expression;
		return $this;
	}
	
	/**
	*	Function to add where clause
	*	
	*	@param string	condition string
	*/
	
	public function where( $condition )
	{
		$this->where[] = $condition;
		return $this;
	}
	
	/**
	*	Function to order clause
	*	
	*	@param string	expression string
	*	@param string	order type (ORDER_ASCENDING, ORDER_DESCENDING)
	*/
	
	public function order( $expression, $type )
	{
		$this->order[$expression] = $type;
		return $this;
	}
	
	/**
	*	Function to order clause
	*	
	*	@param string	expression string
	*/
	
	public function group( $expression )
	{
		$this->group[] = $expression;
		return $this;
	}
	
	/**
	*	Function to add having clause
	*	
	*	@param string	condition string
	*/
	
	public function having( $condition )
	{
		$this->having[] = $condition;
		return $this;
	}
	
	/**
	*	Function to set limit clause
	*	
	*	@param int	row limit
	*/
	
	public function limit( $limit, $page = 0 )
	{
		$this->limit = $limit;
		$this->page = $page;
		return $this;
	}
	
	/**
	*	Function to set limit offset clause
	*	
	*	@param int	offset number
	*/
	
	public function offset( $offset )
	{
		$this->offset = $offset;
		return $this;
	}
	
	/**
	*	Function to set distinct
	*	
	*	@param bool	only distinct
	*/
	
	public function distinct( $distinct )
	{
		$this->distinct = $distinct;
		return $this;
	}
	
	/**
	*	Function to set row locking
	*	
	*	@param bool	only distinct
	*/
	
	public function lock( $type, $wait = TRUE )
	{
		$this->lock = $type;
		$this->lock_wait = $wait;
		return $this;
	}
	
	/**
	*	Executes the query object
	*
	*	@param	array	key-value array of escaped values
	*/
	
	public function run( array $parameter_array = array() )
	{
		//For some reason if we don't bind any parameters, it doesn't prepare the query, even though it says it should. So the object can't be reused :(
		if( ( ! $this->_statement_object ) || ( ! $parameter_array ) ) 
			$this->_statement_object = $this->_sqloo_connection->prepare( $this->getQueryString(), TRUE );
		
		$parameter_array += $this->getParameterArray();
		$this->_sqloo_connection->execute( $this->_statement_object, $parameter_array );
	}
	
	/**
	*	Explains the query object
	*
	*	@param	array	key-value array of escaped values
	*/
	
	public function explain( array $parameter_array = array() )
	{
		$parameter_array += $this->getParameterArray();
		
		return $this->_sqloo_connection->query( "EXPLAIN ".$this->getQueryString(), $parameter_array )->fetchAll( PDO::FETCH_ASSOC );
	}
	
	/**
	*	The query string
	*
	*	@return	string	Query string
	*/
	
	public function getQueryString()
	{
		return 
			$this->_getSelectString().
			$this->_getFromString().
			$this->_getWhereString().
			$this->_getGroupString().
			$this->_getHavingString().
			$this->_getOrderString().
			$this->_getLimitString().
			$this->_getLockString();
	}
	
	/**
	*	Counts rows returned by query
	*
	*	@return	int	row count
	*/
	
	public function count()
	{
		if( ! $this->_statement_object )
			throw new Sqloo_Exception( "Query hasn't been run yet", Sqloo_Exception::BAD_INPUT );
		
		return $this->_statement_object->rowCount();			
	}
	
	/**
	*	Create an IN expression
	*
	*	@return	string	in part of expression (ie. "IN( .... )" )
	*/
	
	public function inArray( array $array )
	{
		if( ! $array ) throw new Sqloo_Exception( "Empty Array passed", Sqloo_Exception::BAD_INPUT );
		$in_array_keys = array();
		foreach( $array as $in_array_item ) {
			$in_array_keys[] = $this->parameter( $in_array_item );
		}
		return "IN (".implode( ",", $in_array_keys ).")";
	}
	
	/**
	*	Adds a parameter to the query
	*
	*	@return	string	parameter reference
	*/
	
	public function parameter( &$parameter )
	{
		static $parameter_index = 0;
		$key = "_p_".$parameter_index++;
		$this->parameter_array[$key] = &$parameter;
		return ":".$key;
	}
	
	/**
	*	Shortcut for ->parameter( .. );
	*/
	
	public function p( &$parameter )
	{
		return $this->parameter( $parameter );
	}
	
	/**
	*	Shortcut for ->parameter( .. );
	*/
	
	public function bind( &$parameter )
	{
		return $this->parameter( $parameter );
	}
	
	/**
	*	Accessor for the internal parameter array
	*
	*	@return	array	associtated array
	*/
	
	public function getParameterArray()
	{
		return $this->parameter_array;
	}
	
	/**
	*	Fetches a row
	*
	*	@return	array	associtated array
	*/
	
	public function fetchRow()
	{
		if( ! $this->_statement_object )
			throw new Sqloo_Exception( "Query hasn't been run yet", Sqloo_Exception::BAD_INPUT );
	
		return $this->_statement_object->fetch( PDO::FETCH_ASSOC );
	}
	
	/**
	*	Fetches an array of rows
	*
	*	@return	array	array of associtated array
	*/
	
	public function fetchArray()
	{
		if( ! $this->_statement_object )
			throw new Sqloo_Exception( "Query hasn't been run yet", Sqloo_Exception::BAD_INPUT );

		return $this->_statement_object->fetchAll( PDO::FETCH_ASSOC );
	}
	
	/* Inner workings */
	
	private function _releaseStatementObject()
	{
		if( $this->_statement_object ) {
			$this->_statement_object->closeCursor();
			$this->_statement_object = NULL;
		}
	}
	
	private function _getSelectString()
	{
		$select_string =
			"SELECT ".( $this->_query_data["distinct"] ? "DISTINCT" : "ALL" )."\n";
			
		if( $this->_query_data["column"])
			foreach( $this->_query_data["column"] as $output_name => $reference )
				$select_string .= ( is_null( $reference ) ? "NULL" : $reference )." AS \"".$output_name."\",\n";
		else
			$select_string .= "* \n";
			
		return substr( $select_string, 0, -2 )."\n";
	}
	
	protected function _getFromString()
	{
		if( ! $this->_root_table_class ) 
			throw new Sqloo_Exception( "Root table is not set", Sqloo_Exception::BAD_INPUT );
		
		$from_string = 
			"FROM \"".$this->_root_table_class->getTableName()."\" AS \"".$this->_root_table_class->getReference()."\"\n";
		
		foreach( $this->_getJoinData( $this->_root_table_class ) as $join_data ) {
			switch( $join_data["type"] ) {
				case Sqloo_Query_Table::JOIN_CHILD:
					$from_string .= 
						$join_data["join_type"]." JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n".
						"ON ".$join_data["to_column_ref"]." = ".$join_data["from_column_ref"]."\n";
					break;
				case Sqloo_Query_Table::JOIN_PARENT:
					$from_string .= 
						$join_data["join_type"]." JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n".
						"ON ".$join_data["to_column_ref"]." = ".$join_data["from_column_ref"]."\n";
					break;
				
				case Sqloo_Query_Table::JOIN_NM:
					$from_string .= 
						$join_data["join_type"]." JOIN \"".$join_data["table_nm"]."\" AS \"".$join_data["reference_nm"]."\"\n".
						"ON \"".$join_data["reference_from"]."\".id = \"".$join_data["reference_nm"]."\".".$join_data["table_from"]."\n".
						$join_data["join_type"]." JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n".
						"ON \"".$join_data["reference_to"]."\".id = \"".$join_data["reference_nm"]."\".".$join_data["table_to"]."\n";
					break;
				
				case Sqloo_Query_Table::JOIN_CROSS:
					$from_string .= 
						$join_data["join_type"]." CROSS JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n";
					break;
				
				case Sqloo_Query_Table::JOIN_CUSTOM_ON:
					$from_string .= 
						$join_data["join_type"]." JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n".
						"ON ".$join_data["on_string"]."\n";
					break;
					
				default:
					throw new Sqloo_Exception( "Bad join type, type_id: ".$join_data["type"], Sqloo_Exception::BAD_INPUT );
			}
		}
		return $from_string;
	}
	
	private function _getJoinData( $query_table_class )
	{
		$join_data_array = $query_table_class->getJoinData();
		foreach( $join_data_array as $join_data )
			$join_data_array += $this->_getJoinData( $join_data["class"] );
		
		return $join_data_array;
	}
	
	private function _getWhereString()
	{
		return $this->_query_data["where"] ? "WHERE ( ".implode( " ) AND\n( ", $this->_query_data["where"] )." )\n" : "";
	}
	
	private function _getGroupString()
	{
		return $this->_query_data["group"] ? "GROUP BY ".implode( ", ", $this->_query_data["group"] )."\n" : "";
	}
	
	private function _getHavingString()
	{
		return $this->_query_data["having"] ? "HAVING ( ".implode( " ) AND\n( ", $this->_query_data["having"] )." )\n" : "";
	}
	
	private function _getOrderString()
	{
		$order_string = "";
		if( $this->_query_data["order"] ) {
			$order_string .= "ORDER BY ";
			foreach( $this->_query_data["order"] as $reference => $order_type )
				$order_string .= $reference." ".$order_type.", ";
			$order_string = substr( $order_string, 0, -2 )."\n";
		}
		return $order_string;
	}
	
	private function _getLimitString()
	{
		$limit_string = "";
		if( ! is_null( $this->_query_data["limit"] ) ) {
			$offset = 0;
			if( ! is_null( $this->_query_data["page"] ) ) $offset += $this->_query_data["limit"] * $this->_query_data["page"];
			if( ! is_null( $this->_query_data["offset"] ) ) $offset += $this->_query_data["offset"];
			$limit_string .= "LIMIT ".$this->_query_data["limit"]." OFFSET ".$offset."\n";
		}
		return $limit_string;		
	}
	
	private function _getLockString()
	{
		$lock_string = "";
		if( $this->_query_data["lock"] !== self::SELECT_LOCK_NONE ) {
			if( $this->_sqloo_connection->getTransactionDepth() == 0 )
				throw new Sqloo_Exception( "Locking row with selects requires to be in a transaction", Sqloo_Exception::TRANSACTION_REQUIRED );
			
			switch( $this->_sqloo_connection->getDBType() ) {
				case Sqloo_Connection::DB_MYSQL:
					switch( $this->_query_data["lock"] ) {
						case self::SELECT_LOCK_SHARE:
							$lock_string .= "LOCK IN SHARE MODE\n";
							break;
						case self::SELECT_LOCK_UPDATE:
							$lock_string .= "FOR UPDATE\n";
							break;
						default:
							throw new Sqloo_Exception( "Unknown locking type: ".$this->_query_data["lock"], Sqloo_Exception::BAD_INPUT );
							break;
					}
					if( ! $this->_query_data["lock_wait"] ) throw new Exception( "Mysql doesn't support NOWAIT for locking tables on select", Sqloo_Exception::BAD_INPUT );
					break;
					
				case Sqloo_Connection::DB_PGSQL:
					switch( $this->_query_data["lock"] ) {
						case self::SELECT_LOCK_SHARE:
							$lock_string .= "FOR SHARE\n";
							break;
						case self::SELECT_LOCK_UPDATE:
							$lock_string .= "FOR UPDATE\n";
							break;
						default:
							throw new Sqloo_Exception( "Unknown locking type: ".$this->_query_data["lock"], Sqloo_Exception::BAD_INPUT );
							break;
					}
					if( ! $this->_query_data["lock_wait"] ) $lock_string .= "NOWAIT\n";
					break;
				
				default:
					throw new Sqloo_Exception( "Unknown db type", Sqloo_Exception::BAD_INPUT );
					break;
			}
		}
		
		
		return $lock_string;
	}
	
	/* ITERATOR INTERFACE */
	
	private $position = NULL;
	private $_current_row = NULL;
	
	
	function rewind()
	{
		$this->position = 0;
		$this->_current_row = $this->fetchRow();
	}

	function current()
	{
		return $this->_current_row;
	}

	function key()
	{
		return $this->position;
	}

	function next()
	{
		$this->position++;
		$this->_current_row = $this->fetchRow();
	}

	function valid()
	{
		return (bool)$this->_current_row;
	}
	
	private function __clone() {}
		
}

?>