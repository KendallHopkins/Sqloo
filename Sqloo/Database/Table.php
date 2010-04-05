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

class Sqloo_Database_Table
{
	
	public $name;
	public $column = array();
	public $parent = array();
	public $index = array();
	
	public function __construct( $table_name ) { $this->name = $table_name; }
	
	public function column( $column_name, $data_type, $allow_null = FALSE, $default_value = NULL )
	{
		$this->column[ $column_name ] = array(
			Sqloo_Schema::COLUMN_DATA_TYPE => $data_type,
			Sqloo_Schema::COLUMN_ALLOW_NULL => $allow_null,
			Sqloo_Schema::COLUMN_DEFAULT_VALUE => $default_value
		);
	}
	
	public function parent( $join_column_name, $parent_table_name, $allow_null = FALSE, $default_value = NULL, $on_delete = Sqloo_Schema::ACTION_CASCADE, $on_update = Sqloo_Schema::ACTION_CASCADE )
	{
		$this->parent[ $join_column_name ] = array(
			Sqloo_Schema::PARENT_TABLE_NAME => $parent_table_name, 
			Sqloo_Schema::PARENT_ALLOW_NULL => $allow_null, 
			Sqloo_Schema::PARENT_DEFAULT_VALUE => $default_value, 
			Sqloo_Schema::PARENT_ON_DELETE => $on_delete, 
			Sqloo_Schema::PARENT_ON_UPDATE => $on_update
		);
	}
	
	public function index( array $column_array, $unique = FALSE )
	{
		$this->index[] = array(
			Sqloo_Schema::INDEX_COLUMN_ARRAY => $column_array,
			Sqloo_Schema::INDEX_UNIQUE => $unique
		);
	}
	
}

?>