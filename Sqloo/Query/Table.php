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
	
	static private $_unique_table_id = 0;
	
	private $_name;
	private $_reference;
	private $_join_data_array = array();
	
	public function __construct( $table_name, $table_reference = NULL )
	{
		$this->_name = $table_name;
		$this->_reference = ( is_null( $table_reference ) ? $table_name : $table_reference )."_".( ++self::$_unique_table_id );
	}
		
	public function __get( $key ) { return "\"".$this->_reference."\".\"".$key."\""; }
	
	public function __toString() { return "\"".$this->_reference."\""; }
	
	public function getTableName() { return $this->_name; }
	
	public function join( $foreign_table_name, $local_join_column, $foreign_join_column, $join_type = Sqloo_Query::JOIN_INNER )
	{
		$new_table = new self( $foreign_table_name, $this->_reference."|".$foreign_table_name."+".$local_join_column."+".$foreign_join_column );
		$this->_join_data[] = array(
			"table_class" => $new_table,
			"table_to" => $foreign_table_name,
			"on_string" => $new_table->$foreign_join_column." = ".$this->$local_join_column,
			"join_type" => $join_type
		);
		return $new_table;
	}
	
	public function joinChild( $foreign_table_name, $foreign_join_column, $join_type = Sqloo_Query::JOIN_INNER )
	{
		return $this->join( $foreign_table_name, "id", $foreign_join_column, $join_type );
	}
	
	public function joinParent( $foreign_table_name, $local_join_column, $join_type = Sqloo_Query::JOIN_INNER )
	{
		return $this->join( $foreign_table_name, $local_join_column, "id", $join_type );
	}
	
	public function joinCustomOn( $foreign_table_name, &$on_string, $join_type = Sqloo_Query::JOIN_INNER )
	{
		$new_table = new self( $foreign_table_name, $this->_reference."||".$foreign_table_name );
		$this->_join_data[] = array(
			"table_class" => $new_table,
			"table_to" => $foreign_table_name,
			"on_string" => &$on_string,
			"join_type" => $join_type
		);
		return $new_table;
	}
	
	public function joinCross( $foreign_table_name )
	{
		$new_table = new self( $foreign_table_name, $this->_reference."||".$foreign_table_name );
		$this->_join_data[] = array(
			"table_class" => $new_table,
			"table_to" => $foreign_table_name,
			"join_type" => "CROSS"
		);
		return $new_table;
	}
		
	/** @access private */
	public function _getJoinDataArray() { return $this->_join_data_array; }

}

?>