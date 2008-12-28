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

class Sqloo_Query_Table
{
	
	/** @access private */
	const join_child = 1;
	/** @access private */
	const join_parent = 2;
	/** @access private */
	const join_nm = 3;
	
	private $_name;
	private $_reference;
	private $_nm_query_table_class;
	private $_join_data = array();
	
	public function __construct( $table_name, $table_reference = NULL, $nm_query_table_class = NULL )
	{
		$this->_name = $table_name;
		$this->_reference = ( $table_reference !== NULL ) ? $table_reference : $table_name;
		$this->_nm_query_table_class = $nm_query_table_class;
	}
	
	public function __get( $key ) { return "`".$this->_reference."`.".$key; }
	
	public function getNMTable()
	{
		if( $this->_nm_query_table_class === NULL ) trigger_error( "This table wasn't joined using joinNM(), so it doesn't have a NMTable", E_USER_ERROR );
		return $this->_nm_query_table_class;
	}
	
	public function joinChild( $child_table_name, $join_column, $join_type = Sqloo::join_inner )
	{
		$new_table_reference = $this->_reference."|".$child_table_name."+".$join_column;
		$new_sqloo_query_table = new self( $child_table_name, $new_table_reference );
		$this->_join_data[] = array(
			"type" => Sqloo_Query_Table::join_child,
			"class" => $new_sqloo_query_table,
			"table_to" => $child_table_name,
			"reference_from" => $this->_reference,
			"reference_to" => $new_table_reference,
			"join_column" => $join_column,
			"join_type" => $join_type
		);
		return $new_sqloo_query_table;
	}
	
	public function joinParent( $parent_table_name, $join_column, $join_type = Sqloo::join_inner )
	{
		$new_table_reference = $this->_reference."|".$parent_table_name."++".$join_column;
		$new_sqloo_query_table = new self( $parent_table_name, $new_table_reference );
		$this->_join_data[] = array(
			"type" => Sqloo_Query_Table::join_parent,
			"class" => $new_sqloo_query_table,
			"table_to" => $parent_table_name,
			"reference_from" => $this->_reference,
			"reference_to" => $new_table_reference,
			"join_column" => $join_column,
			"join_type" => $join_type
		);
		return $new_sqloo_query_table;
	}
	
	public function joinNM( $table_name, $join_type = Sqloo::join_inner )
	{
		$nm_table_name = Sqloo::computeNMTableName( $this->_name, $table_name );
		$nm_table_reference = $this->_reference."|".$nm_table_name;
		$new_table_reference = $this->_reference."|".$table_name;
		$new_nm_sqloo_query_table = new self( $nm_table_name, $nm_table_reference );
		$new_sqloo_query_table = new self( $table_name, $new_table_reference, $new_nm_sqloo_query_table );
		$this->_join_data[] = array(
			"type" => Sqloo_Query_Table::join_nm,
			"class" => $new_sqloo_query_table,
			"table_from" => $this->_name,
			"table_to" => $table_name,
			"table_nm" => $nm_table_name,
			"reference_from" => $this->_reference,
			"reference_to" => $new_table_reference,
			"reference_nm" => $nm_table_reference,
			"join_type" => $join_type
		);
		return $new_sqloo_query_table;
	}
	
	/** @access private */
	public function getTableName() { return $this->_name; }
	/** @access private */
	public function getJoinData() { return $this->_join_data; }
	
}

?>