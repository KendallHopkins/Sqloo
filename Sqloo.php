<?php

require( "Sqloo/Pool.php" );
require( "Sqloo/Table.php" );

class Sqloo
{

	public $tables = array();
	private $_sqloo_pool;
	private $_in_Transaction = 0;
	
	//Table Consts
	//shared attributes
	const allow_null = 1; //bool
	
	//Parent Attributes
	const on_delete = 2; //action - see below
	const on_update = 3; //action - see below
	const table_class = 4; //class Sqloo_Table - see ./sqloo/table.php
	
	//Column Attributes
	const data_type = 5; //string
	const indexed = 6; //bool
	const default_value = 7; //bool
	const primary_key = 8; //bool
	const auto_increment = 9; //bool
	
	//Actions
	const restrict = "RESTRICT";
	const cascade = "CASCADE";
	const set_null = "SET NULL";
	const no_action = "NO ACTION";
	
	//Order Types
	const order_ascending = "ASC";
	const order_descending = "DESC";
	
	//Join Types
	const join_inner = "INNER";
	const join_outer = "OUTER";
	const join_left = "LEFT";
	const join_right = "RIGHT";
	
	//Insert Modifiers
	const insert_low_priority = "LOW_PRIORITY";
	const insert_high_priority = "HIGH_PRIORITY";
	const insert_delayed = "DELAYED";
	
	public function __construct( $db_configure_file_path ) 
	{
		require( $db_configure_file_path );
		$this->_sqloo_pool = new Sqloo_Pool( $master_pool, $slave_pool );
	}
	
	public function __destruct()
	{
		if( $this->_in_Transaction > 0 ) {
			for( $i = 0; $i < $this->_in_Transaction; $i++ ) $this->rollbackTransaction();
			trigger_error( $i." transaction was not close and was rolled back", E_USER_ERROR );
		}
	}
	
	public function __clone() { trigger_error( "Clone is not allowed.", E_USER_ERROR ); }

	public function query( $query_string, $on_slave = FALSE, $buffered = TRUE )
	{
		if( $this->_in_Transaction > 0 )
			$db = $this->_sqloo_pool->getTransactionResource();
		else if( $on_slave === FALSE )
			$db = $this->_sqloo_pool->getMasterResource();
		else
			$db = $this->_sqloo_pool->getSlaveResource();
		
		$resource = $buffered ? mysql_query( $query_string, $db ) : mysql_unbuffered_query( $query_string, $db );
		if ( $resource === FALSE ) trigger_error( mysql_error( $db )."<br>\n".$query_string, E_USER_ERROR );
		return $resource;
	}
		
	public function beginTransaction()
	{
		$this->_in_Transaction++;
		$this->query( "BEGIN" );
	}
	
	public function rollbackTransaction()
	{
		if( $this->_in_Transaction === 0 ) trigger_error( "not in a transaction, didn't rollback", E_USER_ERROR );
		$this->query( "ROLLBACK" );
		$this->_in_Transaction--;
	}
	
	public function commitTransaction()
	{
		if( $this->_in_Transaction === 0 ) trigger_error( "not in a transaction, didn't commit", E_USER_ERROR );
		$this->query( "COMMIT" );
		$this->_in_Transaction--;
	}
	
	public function newQuery()
	{
		require_once( "Sqloo/Query.php" );
		return new Sqloo_Query( $this );
	}
	
	public function insert( $table_name, $insert_array, $modifier = NULL )
	{		
		//check if we have a "magic" added/modifed field
		if( array_key_exists( "added", $this->tables[$table_name]->columns ) ) $insert_array["added"] = "CURRENT_TIMESTAMP";
		if( array_key_exists( "modified", $this->tables[$table_name]->columns ) ) $insert_array["modified"] = "CURRENT_TIMESTAMP";
		
		$insert_string = "INSERT ";
		if( $modifier !== NULL ) $insert_string .= $modifier." ";
		$insert_string .= "INTO `".$table_name."`\n";
		$insert_string .= "SET ".self::processKeyValueArray( $insert_array )."\n";
		$this->query( $insert_string );
		return mysql_insert_id( $this->_sqloo_pool->getMasterResource() );
	}
	
	public function update( $table_name, $update_array, $id_array, $limit = NULL )
	{
		$array_count = count( $id_array );
		if( $array_count === 0 ) trigger_error( "array of 0 size", E_USER_ERROR );
				
		//check if we have a "magic" modifed field
		if( array_key_exists( "modified", $this->tables[$table_name]->columns ) ) $update_array["modified"] = "CURRENT_TIMESTAMP";
				
		/* create update string */
		$update_string = "UPDATE `".$table_name."`\n";
		$update_string .= "SET ".self::processKeyValueArray( $update_array )."\n";
		$update_string .= "WHERE id IN ".self::arrayToIn( $id_array )."\n";				
		$update_string .= "LIMIT ".$array_count."\n";
		$this->query( $update_string );
	}
	
	public function delete( $table_name, $id_array )
	{
		$array_count = count( $id_array );
		if ( $array_count === 0 ) trigger_error( "array of 0 size", E_USER_ERROR );
		
		$delete_string = "DELETE FROM `".$table_name."`\n";
		$delete_string .= "WHERE id IN ".self::arrayToIn( $id_array )."\n";
		$delete_string .= "LIMIT ".$array_count.";";
		$this->query( $delete_string );
	}
	
	public function union( $array_of_queries )
	{
		if( count( $array_of_queries ) < 2 ) trigger_error( "union must have more than 1 query objects", E_USER_ERROR );
		require_once( "Sqloo/Query.php" );
		return new Sqloo_Query( $this, $array_of_queries );
	}
	
	public function newTable( $name )
	{
		return $this->tables[ $name ] = new Sqloo_Table( $name );
	}
	
	public function newRelationshipTable( $table1, $table2 )
	{
		$many_to_many_table = $this->newTable( self::computeJoinTableName( $table1->name, $table2->name ) );
		$many_to_many_table->parents = array(
			$table1->name => array(
				Sqloo::table_class => $table1, 
				Sqloo::allow_null => FALSE, 
				Sqloo::on_delete => Sqloo::cascade, 
				Sqloo::on_update => Sqloo::cascade
			),
			$table2->name => array(
				Sqloo::table_class => $table2, 
				Sqloo::allow_null => FALSE, 
				Sqloo::on_delete => Sqloo::cascade, 
				Sqloo::on_update => Sqloo::cascade
			)
		);
		return $many_to_many_table;
	}
	
	public function nextId( $tableName )
	{
		$query = "SHOW TABLE STATUS WHERE name = '".$tableName."';";
		$resource = $this->query( $query );
		$array = @mysql_fetch_assoc( $resource );
		if( $array == FALSE ) trigger_error( "bad table name", E_USER_ERROR );
		return $array[ "Auto_increment" ];
	}
	
	//schema functions
	public function checkSchema()
	{
		require_once( "Sqloo/Schema.php" );
		static $schema = NULL;
		if( $schema === NULL ) $schema = new Sqloo_Schema( $this );
		return $schema->checkSchema();
	}
	
	static public function processVariable( $value )
	{
		switch ( gettype( $value ) ) {
		case "boolean": return "'".(int)$value."'";
		case "NULL": return "NULL";
		case "integer":
		case "double":
		case "float": return $value;
		case "string":
			switch( $value ) {
			case "CURRENT_TIMESTAMP": return $value;
			default: return "'".mysql_escape_string( $value )."'";
			}
		case "object": return "(".(string)$value.")";
		default: trigger_error( "bad imput: ".var_export( $value, TRUE ), E_USER_ERROR );
		}
	}
	
	static public function computeJoinTableName( $first_Table, $second_table )
	{
		return ( $first_Table < $second_table ) ? $first_Table."-".$second_table : $second_table."-".$first_Table;
	}
	
	static public function arrayToIn( $value_array )
	{
		$in_string = "(";
		foreach( $value_array as $value ) $in_string .= self::processVariable( $value ).",";
		return rtrim( $in_string, "," ).")";
	}
	
	static public function processKeyValueArray( $key_value_array )
	{
		$string = "";
		foreach ( $key_value_array as $key => $value ) $string .= $key."=".$this->processVariable( $value ).",";
		return rtrim( $string, "," );
	}
	
	public function getMasterDatabaseName()
	{
		return $this->_sqloo_pool->getMasterDatabaseName();
	}

}

?>