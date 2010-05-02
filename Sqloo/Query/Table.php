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

class Sqloo_Query_Table
{
	
	/** @access private */
	const JOIN_CHILD = 1;
	/** @access private */
	const JOIN_PARENT = 2;
	/** @access private */
	const JOIN_CROSS = 3;
	/** @access private */
	const JOIN_CUSTOM_ON = 4;
	
	//this ensure you can union simlar queries without naming overlap
	static private $_unique_table_iterator = 0;
	
	private $_name;
	private $_reference;
	private $_join_data = array();
	
	public function __construct( $table_name, $table_reference = NULL )
	{
		$this->_name = $table_name;
		$this->_reference = ( is_null( $table_reference ) ? $table_name : $table_reference )."_".( ++self::$_unique_table_iterator );
	}
	
	public function __get( $key ) { return "\"".$this->_reference."\".\"".$key."\""; }
	
	public function __toString() { return "\"".$this->_reference."\""; }
	
	public function joinChild( $foreign_table_name, $local_join_column, $foreign_join_column, $join_type = Sqloo_Query::JOIN_INNER )
	{
		$new_sqloo_query_table = new self( $foreign_table_name, $this->_reference."|".$foreign_table_name."+".$foreign_join_column );
		$this->_join_data[] = array(
			"type" => Sqloo_Query_Table::JOIN_CHILD,
			"class" => $new_sqloo_query_table,
			"table_to" => $foreign_table_name,
			"reference_to" => $new_sqloo_query_table->getReference(),
			"to_column_ref" => $new_sqloo_query_table->$foreign_join_column,
			"from_column_ref" => $this->$local_join_column,
			"join_type" => $join_type
		);
		return $new_sqloo_query_table;
	}
	
	public function joinParent( $foreign_table_name, $local_join_column, $foreign_join_column, $join_type = Sqloo_Query::JOIN_INNER )
	{
		$new_sqloo_query_table = new self( $foreign_table_name, $this->_reference."|".$foreign_table_name."++".$foreign_join_column );
		$this->_join_data[] = array(
			"type" => Sqloo_Query_Table::JOIN_PARENT,
			"class" => $new_sqloo_query_table,
			"table_to" => $foreign_table_name,
			"reference_to" => $new_sqloo_query_table->getReference(),
			"to_column_ref" => $new_sqloo_query_table->$foreign_join_column,
			"from_column_ref" => $this->$local_join_column,
			"join_type" => $join_type
		);
		return $new_sqloo_query_table;
	}
	
	public function joinCross( $table_name, $join_type = Sqloo_Query::JOIN_INNER )
	{
		$cross_table = new self( $table_name, $this->_reference."||".$table_name );
		$this->_join_data[] = array(
			"type" => Sqloo_Query_Table::JOIN_CROSS,
			"class" => $cross_table,
			"table_to" => $table_name,
			"reference_to" => $cross_table->getReference(),
			"join_type" => $join_type
		);

		return $cross_table;
	}
	
	public function joinCustomOn( $table_name, &$on_string, $join_type = Sqloo_Query::JOIN_INNER )
	{
		$cross_table = new self( $table_name, $this->_reference."||".$table_name );
		$this->_join_data[] = array(
			"type" => Sqloo_Query_Table::JOIN_CUSTOM_ON,
			"class" => $cross_table,
			"table_to" => $table_name,
			"reference_to" => $cross_table->getReference(),
			"on_string" => &$on_string,
			"join_type" => $join_type
		);

		return $cross_table;
	}
	
	/** @access private */
	public function getTableName() { return $this->_name; }
	/** @access private */
	public function getReference() { return $this->_reference; }
	/** @access private */
	public function getJoinData() { return $this->_join_data; }

	
}

?>