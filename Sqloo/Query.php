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

require( "Query/Table.php" );

class Sqloo_Query implements Iterator
{
	
	private $_sqloo;
	
	/* Query Data */
	private $_root_query_table_class = NULL;
	private $_union_array = NULL;
	private $_query_data = array(
		"column" => NULL,
		"where" => array(),
		"order" => array(),
		"group" => array(),
		"having" => array(),
		"limit" => NULL,
		"page" => NULL,
		"offset" => NULL,
		"distinct" => FALSE,
		"buffered" => TRUE
	);
	private $_statement_object = NULL;
	
	/**
	*	Construct function
	*
	*	This should never be called directly, look at Sqloo->newQuery()
	*	@param	Sqloo
	*	@param	array	If this query is a union query this is an array of Sqloo_Query objects
	*/
	
	public function __construct( $sqloo, $union_array = NULL )
	{
		$this->_sqloo = $sqloo;
		$this->_union_array = $union_array;
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
	*	@param	string		The root table name
	*	@return	Sqloo_Table	A Sqloo_Table object
	*/
	
	public function table( $table_name )
	{
		if( $this->_root_query_table_class !== NULL )
			throw new Sqloo_Exception( "Root table is already set", Sqloo_Exception::BAD_INPUT );
		if( $this->_union_array !== NULL )
			throw new Sqloo_Exception( "This is a union query", Sqloo_Exception::BAD_INPUT );
		
		$this->_root_query_table_class = new Sqloo_Query_Table( $table_name );
		return $this->_root_query_table_class;
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
		
		$this->_releaseStatementObject();
		return $this->_query_data[$key];
	}
	
	/**
	*	Sets the current value of an attribute of the query
	*
	*	Will throw exception if $key is bad
	*	
	*	@param	string	The attribute key
	*	@param	mixed	The attribute value
	*/
	
	public function __set( $key, $value )
	{
		if( ! array_key_exists( $key, $this->_query_data ) )
			throw new Sqloo_Exception( "Bad key: $key", Sqloo_Exception::BAD_INPUT );
		
		$this->_releaseStatementObject();
		$this->_query_data[$key] = $value;
	}
	
	/**
	*	Executes the query object
	*
	*	@param	array	key-value array of escaped values
	*/
	
	public function run( $parameters_array = NULL )
	{
		if( ! $this->_statement_object )
			$this->_statement_object = $this->_sqloo->prepare( $this->getQueryString(), TRUE );
		
		$this->_sqloo->execute( $this->_statement_object, $parameters_array );
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
			$this->_getLimitString();
	}
	
	public function count()
	{
		if( ! $this->_statement_object )
			throw new Sqloo_Exception( "Query hasn't been run yet", Sqloo_Exception::BAD_INPUT );
		
		return $this->_statement_object->rowCount();			
	}
	
	public function inArray( array $array, array &$unescaped_array = NULL )
	{
		if( ! $array ) throw new Sqloo_Exception( "Empty Array passed", Sqloo_Exception::BAD_INPUT );
		if( ! is_null( $unescaped_array ) ) {
			static $in_array_index = 0; //keeps the unescaped array keys from conflicting
			$i = 0;
			$in_array_keys = array();
			$key_prefix = "_in_".$in_array_index."_";
			foreach( $array as $in_array_item ) {
				$key = $key_prefix.++$i;
				$unescaped_array[$key] = $in_array_item;
				$in_array_keys[] = ":".$key;
			}
			++$in_array_index;
			return "IN (".implode( ",", $in_array_keys ).")";
		} else {
			foreach( $array as &$value ) {
				$value = $this->_sqloo->quote( $value );
			}
			return "IN (".implode( ",", $array ).")";
		}
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
		$select_string = $this->_query_data["distinct"] ? "SELECT DISTINCT\n" : "SELECT\n";
		if( $this->_query_data["column"])
			foreach( $this->_query_data["column"] as $output_name => $reference )
				$select_string .= ( is_null( $reference ) ? "NULL" : $reference )." AS \"".$output_name."\",\n";
		else
			$select_string .= "* \n";
			
		return substr( $select_string, 0, -2 )."\n";
	}
	
	private function _getFromString()
	{
		if( ( ! $this->_root_query_table_class ) && ( ! $this->_union_array ) ) 
			throw new Sqloo_Exception( "Root table is not set", Sqloo_Exception::BAD_INPUT );
		if( ( $this->_root_query_table_class ) && ( $this->_union_array ) )
			throw new Sqloo_Exception( "Nothing set", Sqloo_Exception::BAD_INPUT );
		
		$from_string = "FROM ";
		if( $this->_root_query_table_class ) {
			$from_string .= "\"".$this->_root_query_table_class->getTableName()."\" AS \"".$this->_root_query_table_class->getReference()."\"\n";
			foreach( $this->_getJoinData( $this->_root_query_table_class ) as $join_data ) {
				switch( $join_data["type"] ) {
				case Sqloo_Query_Table::JOIN_CHILD:
					$from_string .= 
						$join_data["join_type"]." JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n".
						"ON \"".$join_data["reference_to"]."\".".$join_data["join_column"]." = \"".$join_data["reference_from"]."\".id\n";
					break;
				case Sqloo_Query_Table::JOIN_PARENT:
					$from_string .= 
						$join_data["join_type"]." JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n".
						"ON \"".$join_data["reference_to"]."\".id = \"".$join_data["reference_from"]."\".".$join_data["join_column"]."\n";
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
						$join_data["join_type"]." JOIN \"".$join_data["table_to"]."\" AS \"".$join_data["reference_to"]."\"\n".
						"ON 1\n";
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
		} else {
			$from_string .= "( ( ".implode( " )\nUNION\n( ", $this->_union_array )." ) ) union_name\n";
		}
		return $from_string;
	}
	
	private function _getJoinData( $query_table_class )
	{
		$join_data_array = $query_table_class->getJoinData();
		foreach( $join_data_array as $join_data )
			$join_data_array = array_merge( $join_data_array, $this->_getJoinData( $join_data["class"] ) );
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
		return $this->_query_data["having"]? "HAVING ( ".implode( " ) AND\n( ", $this->_query_data["having"] )." )\n" : "";
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
			if ( ! is_null( $this->_query_data["page"] ) ) $offset += $this->_query_data["limit"] * $this->_query_data["page"];
			if( ! is_null( $this->_query_data["offset"] ) ) $offset += $this->_query_data["offset"];
			$limit_string .= "LIMIT ".$this->_query_data["limit"]." OFFSET ".$offset."\n";
		}
		return $limit_string;		
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
		++$this->position;
		$this->_current_row = $this->fetchRow();
	}

	function valid()
	{
		return (bool)$this->_current_row;
	}
	
	private function __clone() {}
		
}

?>