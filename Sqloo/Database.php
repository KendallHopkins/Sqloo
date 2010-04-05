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

require_once( "Connection.php" );
require_once( "Schema.php" );

class Sqloo_Database
{

	private $_table_array = array();
	private $_load_table_function;
	private $_list_all_tables_function;

	public function __construct( $load_table_function = NULL, $list_all_tables_function = NULL )
	{
		$this->_load_table_function = $load_table_function;
		$this->_list_all_tables_function = $list_all_tables_function;
	}
	
		/**
	*	Make a new Sqloo_Table Object and return it.
	*	
	*	@param	string		New table name
	*	@return	Sqloo_Table	Empty Sqloo_Table object
	*/
	
	public function newTable( $table_name )
	{
		return $this->_table_array[ $table_name ] = new Sqloo_Table( $table_name );
	}
	
	/**
	*	Make a new Sqloo_Table Object, setup the parents and return it.
	*	
	*	@param	Sqloo_Table	Parent table 1
	*	@param	Sqloo_Table	Parent table 2
	*	@return	Sqloo_Table	N:M Sqloo_Table object, used for NMJoins
	*/
	
	public function newNMTable( $sqloo_table_name_1, $sqloo_table_name_2 )
	{
		$many_to_many_table = $this->newTable( Sqloo_Utility::computeNMTableName( $sqloo_table_name_1, $sqloo_table_name_2 ) );
		$many_to_many_table->parent = array(
			$sqloo_table_name_1 => array(
				Sqloo_Schema::PARENT_TABLE_NAME => $sqloo_table_name_1, 
				Sqloo_Schema::PARENT_ALLOW_NULL => FALSE, 
				Sqloo_Schema::PARENT_ON_DELETE => Sqloo_Schema::ACTION_CASCADE, 
				Sqloo_Schema::PARENT_ON_UPDATE => Sqloo_Schema::ACTION_CASCADE
			),
			$sqloo_table_name_2 => array(
				Sqloo_Schema::PARENT_TABLE_NAME => $sqloo_table_name_2, 
				Sqloo_Schema::PARENT_ALLOW_NULL => FALSE, 
				Sqloo_Schema::PARENT_ON_DELETE => Sqloo_Schema::ACTION_CASCADE, 
				Sqloo_Schema::PARENT_ON_UPDATE => Sqloo_Schema::ACTION_CASCADE
			)
		);
		return $many_to_many_table;
	}


	/**
	*	Checks and correct the database schema to match the table schema setup in code.
	*
	*	@return	string	Log of the queries run.
	*/
	
	public function checkSchema( Sqloo_Connection $sqloo_connection )
	{
		switch( $sqloo_connection->getDBType() ) {
			case Sqloo_Connection::DB_MYSQL: $file_name = "Mysql"; break;
			case Sqloo_Connection::DB_PGSQL: $file_name = "Postgres"; break;
			default: throw new Sqloo_Exception( "Bad database type: ".$database_configuration["type"], Sqloo_Exception::BAD_INPUT ); break;
		}

		require_once( "Schema/".$file_name.".php" );
		$class_name = "Sqloo_Schema_".$file_name;
		$schema = new $class_name( $sqloo_connection, $this );
		return $schema->checkSchema();
	}

	public function _getTable( $table_name )
	{
		if( ! array_key_exists( $table_name, $this->_table_array ) )
			$this->_loadTable( $table_name );
		return $this->_table_array[$table_name];
	}
	
	private function _loadTable( $table_name )
	{
		if( ! array_key_exists( $table_name, $this->_table_array ) ) {
			if( $this->_load_table_function && is_callable( $this->_load_table_function ) )
				call_user_func( $this->_load_table_function, $table_name, $this );
			if( ! array_key_exists( $table_name, $this->_table_array ) )
				throw new Sqloo_Exception( "could not load table: ".$table_name, Sqloo_Exception::BAD_INPUT );
		}
	}
	
	public function _getAllTables()
	{
		static $all_tables_loaded = FALSE;
		if( ! $all_tables_loaded && ! is_null( $this->_list_all_tables_function ) ) {
			if( ! is_callable( $this->_list_all_tables_function ) )
				throw new Exception( "List all table function isn't callable." );
			
			foreach( call_user_func( $this->_list_all_tables_function ) as $table_name )
				$this->_loadTable( $table_name );
			
			$all_tables_loaded = TRUE;
		}
		return $this->_table_array;
	}


}