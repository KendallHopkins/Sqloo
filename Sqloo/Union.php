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
require_once( "Query/Table.php" );

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