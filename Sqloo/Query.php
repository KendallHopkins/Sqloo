<?php

class Sqloo_Query
{
	const forward_join = "|";
	const reverse_join = "<>";
	
	private $_sqloo;
	private $_select_string;
	private $_from_string;
	private $_where_string;
	private $_group_string;
	private $_having_string;
	private $_order_string;
	private $_limit_string;
	private $_table_names = array();
	
	function __construct( $sqloo )
	{
		$this->_sqloo = $sqloo;
		$this->_select_string_prefix = "SELECT\n";
		$this->_select_string = "";
		$this->_from_string = "";
		$this->_where_string = "";
		$this->_group_string = "";
		$this->_having_string = "";
		// http://dev.mysql.com/doc/refman/5.0/en/select.html
		// If you use GROUP BY, output rows are sorted according to the GROUP BY columns as if you had an ORDER BY for the same columns. To avoid the overhead of sorting that GROUP BY produces, add ORDER BY NULL 
		$this->_order_string = "ORDER BY NULL\n";
		$this->_limit_string = "";
	}
	
	public function distinctResults( $show_only_distinted_results = TRUE )
	{
		if( $this->_select_string_prefix == TRUE ){
			$this->_select_string_prefix = "SELECT DISTINCT\n";
		} else {
			$this->_select_string_prefix = "SELECT\n";
		}
	}
	
	public function addAllColumns( )
	{
		$this->_select_string .= "*,\n";
	}
	
	public function addColumn( $table_name, $column_name, $rename_to_string = NULL, $wrap_function_array = array() )
	{
		$ending = "";
		foreach( $wrap_function_array as $wrap_function ) {
			$this->_select_string .= $wrap_function."( ";
			$ending .= " )";
		}
		$this->_select_string .= "`".$table_name."`.".$column_name;
		$this->_select_string .= $ending;
		$this->_select_string .= " AS `";
		if ( $rename_to_string != NULL ) {
			$this->_select_string .= $rename_to_string;
		} else {
			$this->_select_string .= $table_name.".".$column_name;
		}
		
		$this->_select_string .= "`,\n";
	}
	
	public function addColumnRaw( $column_string, $rename_to_string = NULL )
	{
		$this->_select_string .= $this->_escapeTableNames( $column_string );
		if ( $rename_to_string != NULL ) {
			$this->_select_string .= " AS `";
			$this->_select_string .= $rename_to_string."`";
		}
		$this->_select_string .= ",\n";
	}
	
	public function setFromRaw( $from_string )
	{
		$this->_from_string = "FROM ".$from_string."\n";
	}
	
	public function setTable( $name )
	{
		$this->_from_string = "FROM `".$name."` `".$name."`\n";
		$this->_table_names[$name] = $name;
	}
	
	//first table is the parent
	public function addJoin( $first_table, $parent_name, $join_type = "INNER" )
	{
		$first_table_name = self::stripOffLastTableFromString( $first_table );
		$second_table = $this->_sqloo->tables[ $first_table_name ]->parents[ $parent_name ]["table"]->name;
		$join_table_name = $first_table.self::forward_join.$parent_name;
		

		if ( $this->_from_string == "" ) {
				$this->_from_string .= "FROM `".$first_table."`\n";
				$this->_table_names[$first_table] = $first_table;
		}
		$this->_table_names[$join_table_name] = $join_table_name;
		
		$this->_from_string .= $join_type." JOIN `".$second_table."` AS `".$join_table_name."`\n";
		$this->_from_string .= "ON `".$join_table_name."`.id = `".$first_table."`.".$parent_name."\n";
	}
	
	//first table is the child
	public function addBackJoin( $first_table, $second_table, $parent_name, $join_type = "INNER" )
	{
		$first_table_name = self::stripOffLastTableFromString( $first_table );
		$join_table_name = $first_table.self::reverse_join.$second_table;

		if ( $this->_from_string == "" ) {
				$this->_from_string .= "FROM `".$first_table."`\n";
				$this->_table_names[$first_table] = $first_table;
		}
		$this->_table_names[$join_table_name] = $join_table_name;
		
		$this->_from_string .= $join_type." JOIN `".$second_table."` AS `".$join_table_name."`\n";
		$this->_from_string .= "ON `".$join_table_name."`.".$parent_name." = `".$first_table."`.id\n";
	}
	
	public function addNMJoin( $first_table, $second_table, $join_type = "INNER" )
	{
		$second_table_name = $second_table;
		$first_table_name = self::stripOffLastTableFromString( $first_table );
		
		if( $first_table_name < $second_table_name ) {
			$join_table = $first_table_name."-".$second_table_name;
		} else {
			$join_table = $second_table_name."-".$first_table_name;
		}
		
		$join_table_name = $first_table.self::forward_join.$join_table;
		$second_table_join_name = $first_table.self::forward_join.$second_table;

		$this->_table_names[$join_table_name] = $join_table_name;
		$this->_table_names[$second_table_join_name] = $second_table_join_name;
		
		//start making string
		if ( $this->_from_string == "" ) {
			$this->_from_string .= "FROM `".$first_table."`\n";
			$this->_table_names[$first_table] = $first_table;
		}
		$this->_from_string .= $join_type." JOIN `".$join_table."` `".$join_table_name."`\n";
		$this->_from_string .= "ON `".$first_table."`.id = `".$join_table_name."`.".$first_table_name."\n";
		$this->_from_string .= $join_type." JOIN `".$second_table."` AS `".$second_table_join_name."`\n";
		$this->_from_string .= "ON `".$second_table_join_name."`.id = `".$join_table_name."`.".$second_table_name."\n";
	}
	
	public function setWhereRaw( $where_string )
	{
		$this->_where_string = "WHERE ".$where_string."\n";
	}
	
	public function setHavingRaw( $having_string )
	{
		$this->_having_string = "HAVING ".$having_string."\n";
	}
	
	public function setGroup( $table_name, $column_name )
	{
		$this->_group_string = "GROUP BY ".$table_name.".".$column_name."\n";
	}
	
	public function setGroupRaw( $group_string )
	{
		$this->_group_string = "GROUP BY ".$group_string."\n";
	}
		
	public function setOrder( $table_name, $column_name, $ascORdesc = '' )
	{
		$this->_order_string = "ORDER BY ".$table_name.".".$column_name." ".$ascORdesc."\n";
	}
	
	public function setOrderRaw( $order_string )
	{
		$this->_order_string = "ORDER BY ".$order_string."\n";
	}
	
	public function setLimit( $max_number, $page_number = NULL )
	{
		$this->_limit_string = "LIMIT ".$max_number;	
		if ( $page_number != NULL ) {
			$this->_limit_string .= " OFFSET ".($max_number*$page_number);
		}
		$this->_limit_string .= "\n";
	}
	
	private function _queryString()
	{
		$query_string = $this->_select_string_prefix.substr($this->_select_string, 0, -2)."\n"; //string needs comma stripped off end
		$query_string .= $this->_from_string; 
		$query_string .= $this->_escapeTableNames( $this->_where_string );
		$query_string .= $this->_escapeTableNames( $this->_group_string ); 
		$query_string .= $this->_escapeTableNames( $this->_having_string );
		$query_string .= $this->_escapeTableNames( $this->_order_string ); 
		$query_string .= $this->_limit_string; 
		return $query_string;
	}
	
	private function _escapeTableNames( $string )
	{
		$temp_array = array_reverse( $this->_table_names );
		foreach( $temp_array as $table_name )
		{
			$string = str_replace($table_name.".", "`".$table_name."`.", $string );
		}
		return $string;
	}
	
	public function getQueryString()
	{
		return $this->_queryString();
	}
	
	public function run()
	{
		$resource = $this->_sqloo->query( $this->_queryString(), TRUE ); //run on slave
        return new Sqloo_Results( $resource );
	}
	
	static public function stripOffLastTableFromString( $string )
	{
		$first_table_name_start = FALSE;
		foreach( array( self::forward_join, self::reverse_join ) as $join_symbol ) {
			$symbol_location = strrpos( $string, $join_symbol );
			if( ( $symbol_location !== FALSE ) && ( $symbol_location > $first_table_name_start ) ) {
				$first_table_name_start = $symbol_location + strlen( $join_symbol );
			}
		}
		if( $first_table_name_start === FALSE ) {
			$first_table_name_start = 0;
		}
		return substr( $string, $first_table_name_start );
	}
	
	public function __toString()
	{
		return $this->getQueryString();
	}
}
