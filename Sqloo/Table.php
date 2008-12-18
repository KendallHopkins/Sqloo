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

class Sqloo_Table
{
	
	public $name;
	public $columns = array();
	public $parents = array();
	
	function __construct( $table_name ) { $this->name = $table_name; }
	
	public function column( $column_name, $data_type, $allow_null, $default_value, $indexed = FALSE )
	{
		if( array_key_exists( $column_name, $this->columns ) ) trigger_error( "Bad column name, intersects", E_USER_ERROR );
		$this->columns[ $column_name ] = array(
			Sqloo::data_type => $data_type,
			Sqloo::allow_null => $allow_null,
			Sqloo::default_value => $default_value,
			Sqloo::indexed => $indexed
		);
	}
	
	public function parent( $parent_name, $table_class, $allow_null = FALSE, $on_delete = self::cascade, $on_update = self::cascade )
	{
		if( array_key_exists( $parent_name, $this->parents ) ) trigger_error( "Bad parent name, intersects", E_USER_ERROR );
		$this->parents[ $parent_name ] = array(
			Sqloo::table_class => $table_class, 
			Sqloo::allow_null => $allow_null, 
			Sqloo::on_delete => $on_delete, 
			Sqloo::on_update => $on_update
		);
	}
	
}

?>