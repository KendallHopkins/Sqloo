<?php

class Sqloo_Union extends Sqloo_Query
{
	
	private $_union_query_array;
	
	public function __construct( Sqloo_Connection $sqloo_connection, array $union_query_array )
	{
		parent::__construct( $sqloo_connection );
		$this->_union_query_array = $union_query_array;
		$this->_root_table_class = new Sqloo_Query_Table( "union" );
	}
		
	public function getParameterArray()
	{
		$parameter_array = $this->parameter_array;
		foreach( $this->_union_query_array as $union_query ) {
			$parameter_array += $union_query->getParameterArray();
		}
		return $parameter_array;
	}
	
	protected function _getFromString()
	{
		return
			"FROM ".
			"( ( ".implode(
				" )\n".
				"UNION ".( $this->_query_data["distinct"] ? "DISTINCT" : "ALL" )." \n".
				"( ",
				$this->_union_query_array
			)." ) ) \"".$this->_root_table_class->getReference()."\"\n";
	}
	
}

?>