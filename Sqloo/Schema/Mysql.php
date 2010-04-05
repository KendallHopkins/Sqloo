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

/** @access private */

class Sqloo_Schema_Mysql extends Sqloo_Schema
{
	
	/* Correction function */
	
	protected function _executeAlterQuery()
	{
		$log_string = "";
		if( $this->_alter_table_data ) {
			$this->_sqloo_connection->query( "SET FOREIGN_KEY_CHECKS=0" );
			foreach( $this->_alter_table_data as $table_name => $table_query_info_array ) {
				$query_string = "";
				if( array_key_exists( "create", $table_query_info_array ) )
					$query_string .= "CREATE TABLE \"".$table_name."\"(\n";
				else
					$query_string .= "ALTER TABLE \"".$table_name."\"\n";
				
				$query_string .= implode( ",\n", $table_query_info_array["list"] );
				
				if( array_key_exists( "create", $table_query_info_array ) ) {
					$query_string .= "\n) ENGINE=".$table_query_info_array["create"]["engine"]." DEFAULT CHARSET=".$table_query_info_array["create"]["default_charset"];
					$query_string = str_replace( //Alter syntax to Create syntax
						array( "ADD COLUMN ", 	"ADD PRIMARY KEY ", "ADD INDEX ",	"ADD UNIQUE INDEX ",	"ADD FOREIGN KEY "	), 
						array( "",				"PRIMARY KEY ",		"INDEX ",		"UNIQUE INDEX ",		"FOREIGN KEY "		),
						$query_string
					);
				}
				$this->_sqloo_connection->query( $query_string );
				$log_string .= $query_string."\n";
			}
			$this->_sqloo_connection->query( "SET FOREIGN_KEY_CHECKS=1" );
		}
		return $log_string;
	}
	
	/* Data Fetching functions */
	
	protected function _getTableArray()
	{
		$table_array = array();
		$query_object = $this->_sqloo_connection->query( "SHOW TABLES" );
		while( $row = $query_object->fetch( PDO::FETCH_ASSOC ) )
			$table_array[] = end($row);
		return $table_array;
	}
	
	protected function _getColumnDataArray( $table_array )
	{
		$column_data_array = array();
		foreach( $table_array as $table_name ) {
			$column_data = array();
			$query_resource = $this->_sqloo_connection->query( "SHOW COLUMNS FROM \"".$table_name."\"" );
			while( $row = $query_resource->fetch( PDO::FETCH_ASSOC ) ) {
				$column_data[ $row["Field"] ] = array(
					Sqloo_Schema::COLUMN_DATA_TYPE => $row["Type"],
					Sqloo_Schema::COLUMN_ALLOW_NULL => ( $row["Null"] === "YES" ),
					Sqloo_Schema::COLUMN_DEFAULT_VALUE => $row["Default"],
					Sqloo_Schema::COLUMN_PRIMARY_KEY => ( $row["Key"] === "PRI" ),
					Sqloo_Schema::COLUMN_AUTO_INCREMENT => (bool)preg_match( "/auto_increment/i", $row["Extra"] )
				);
			}
			$column_data_array[$table_name] = $column_data;
		}
		return $column_data_array;
	}
	
	protected function _getIndexDataArray( $table_array )
	{
		$index_data_array = array();
		foreach( $table_array as $table_name ) {
			$index_data = array();
			$query_resource = $this->_sqloo_connection->query( "SHOW INDEXES FROM \"".$table_name."\"" );
			while( $row = $query_resource->fetch( PDO::FETCH_ASSOC ) ) {
				if( $row["Key_name"] !== "PRIMARY" ) {
					$index_data[ $row["Key_name"] ][Sqloo_Schema::INDEX_COLUMN_ARRAY][ $row["Seq_in_index"] - 1 ] = $row["Column_name"];
					$index_data[ $row["Key_name"] ][Sqloo_Schema::INDEX_UNIQUE] = ( $row["Non_unique"] === "0" );
				}
			}
			$index_data_array[$table_name] = $index_data;
		}
		return $index_data_array;
	}
	
	protected function _getForeignKeyDataArray()
	{
		$foreign_key_data_array = array();
		$query_string = 
			"SELECT\n".
			"ke.referenced_table_name referenced_table_name,\n".
			"ke.table_name table_name,\n".
			"ke.column_name column_name,\n".
			"ke.referenced_column_name referenced_column_name,\n".
			"ke.constraint_name constraint_name\n".
			"FROM\n".
			"information_schema.KEY_COLUMN_USAGE ke\n".
			"WHERE\n".
			"ke.referenced_table_name IS NOT NULL &&\n".
			"ke.TABLE_SCHEMA = '".$this->_database_configuration["name"]."'";
		$query_resource = $this->_sqloo_connection->query( $query_string );
		while( $row = $query_resource->fetch( PDO::FETCH_ASSOC ) ) {
			$current_attribute_array = self::_getForeignKeyAttributeArray( $row["table_name"], $row["column_name"] );
			$foreign_key_data_array[ $row["table_name"] ][ $row["column_name"] ][ $row["constraint_name"] ] = array( 
				"target_table_name" => $row["referenced_table_name"],
				"target_column_name" => $row["referenced_column_name"],
				Sqloo_Schema::PARENT_ON_DELETE => $current_attribute_array[Sqloo_Schema::PARENT_ON_DELETE],
				Sqloo_Schema::PARENT_ON_UPDATE => $current_attribute_array[Sqloo_Schema::PARENT_ON_UPDATE]
			);
		}
		return $foreign_key_data_array;
	}
	
	private function _getForeignKeyAttributeArray( $table_name, $column_name )
	{
		$attribute_array = array( Sqloo_Schema::PARENT_ON_DELETE => Sqloo_Schema::ACTION_NO_ACTION, Sqloo_Schema::PARENT_ON_UPDATE => Sqloo_Schema::ACTION_NO_ACTION );
		$query_resource = $this->_sqloo_connection->query( "SHOW CREATE TABLE \"".$table_name."\"" );
		$create_table_array = $query_resource->fetch( PDO::FETCH_ASSOC );
		$create_table_string_array = explode( "\n", $create_table_array["Create Table"] );
		foreach( $create_table_string_array as $string )
			if( substr_count( $string, "FOREIGN KEY (\"".$column_name."\")" ) > 0 )
				foreach( array( Sqloo_Schema::PARENT_ON_DELETE => "ON DELETE", Sqloo_Schema::PARENT_ON_UPDATE => "ON UPDATE" ) as $type_id => $type_string )
					foreach( array( Sqloo_Schema::ACTION_RESTRICT, Sqloo_Schema::ACTION_CASCADE, Sqloo_Schema::ACTION_SET_NULL, Sqloo_Schema::ACTION_NO_ACTION ) as $action )
						if( preg_match( "/".$type_string." ".$action."/i", $string ) )
							$attribute_array[$type_id] = $action;
		
		return $attribute_array;
	}
	
	/* Database interface functions */
	
	protected function _addTable( $table_name, $engine_name = "InnoDB", $default_charset = "utf8" )
	{
		$this->_alter_table_data[$table_name]["create"] = array( "default_charset" => $default_charset, "engine" => $engine_name );
	}
	
	protected function _removeTable( $table_name )
	{
		$this->_sqloo_connection->query( "SET FOREIGN_KEY_CHECKS=0" );
		$this->_sqloo_connection->query( "DROP TABLE \"".$table_name."\"" );
		$this->_sqloo_connection->query( "SET FOREIGN_KEY_CHECKS=1" );
	}
	
	protected function _addColumn( $table_name, $column_name, $column_attributes )
	{
		$this->_alter_table_data[$table_name]["list"][] = "ADD COLUMN \"".$column_name."\" ".self::_buildFullTypeString( $column_attributes );
		if( $column_attributes[Sqloo_Schema::COLUMN_PRIMARY_KEY] ) $this->_alter_table_data[$table_name]["list"][] = "ADD PRIMARY KEY (\"".$column_name."\")";	}
	
	protected function _removeColumn( $table_name, $column_name )
	{
		$this->_alter_table_data[$table_name]["list"][] = "DROP COLUMN \"".$column_name."\"";
	}
	
	protected function _alterColumn( $table_name, $column_name, $target_attribute_array, $current_attribute_array )
	{	
		$this->_alter_table_data[$table_name]["list"][] = "MODIFY COLUMN \"".$column_name."\" ".self::_buildFullTypeString( $target_attribute_array );
		if( $target_attribute_array[Sqloo_Schema::COLUMN_PRIMARY_KEY] && ( ! $current_attribute_array[Sqloo_Schema::COLUMN_PRIMARY_KEY] ) ) $this->_alter_table_data[$table_name]["list"][] = "ADD PRIMARY KEY (\"".$column_name."\")";
		if( ( ! $target_attribute_array[Sqloo_Schema::COLUMN_PRIMARY_KEY] ) && $current_attribute_array[Sqloo_Schema::COLUMN_PRIMARY_KEY] ) $this->_alter_table_data[$table_name]["list"][] = "DROP PRIMARY KEY";
	}
	
	protected function _buildFullTypeString( $target_attribute_array )
	{
		return
			$this->_sqloo_connection->getTypeString( $target_attribute_array[Sqloo_Schema::COLUMN_DATA_TYPE] ).
			( ( $target_attribute_array[Sqloo_Schema::COLUMN_ALLOW_NULL] ) ? " NULL" : " NOT NULL" ).
			( ( $target_attribute_array[Sqloo_Schema::COLUMN_DEFAULT_VALUE] !== NULL ) ? " DEFAULT ".$this->_sqloo_connection->quote( $target_attribute_array[Sqloo_Schema::COLUMN_DEFAULT_VALUE] ) : "" ).
			( ( $target_attribute_array[Sqloo_Schema::COLUMN_AUTO_INCREMENT] ) ? " AUTO_INCREMENT" : "" );
	}
	
	protected function _addIndex( $table_name, $index_attribute_array )
	{
		$index_name = self::_getIndexName( $index_attribute_array );
		$query_string = "ADD ";
		if( $index_attribute_array[Sqloo_Schema::INDEX_UNIQUE] )
			$query_string .= "UNIQUE ";
		$query_string .= "INDEX \"".$index_name."\" ( \"".implode( "\",\"", $index_attribute_array[Sqloo_Schema::INDEX_COLUMN_ARRAY] )."\" )";
		$this->_alter_table_data[$table_name]["list"][] = $query_string;
	}
	
	protected function _dropIndex( $table_name, $index_name )
	{
		$this->_alter_table_data[$table_name]["list"][] = "DROP INDEX \"".$index_name."\"";
	}
	
	protected function _addForeignKey( $table_name, $column_name, $foreign_key_attribute_array )
	{
		$this->_alter_table_data[$table_name]["list"][] = "ADD FOREIGN KEY ( \"".$column_name."\" ) REFERENCES \"".$foreign_key_attribute_array["target_table_name"]."\" ( \"".$foreign_key_attribute_array["target_column_name"]."\" ) ON DELETE ".$foreign_key_attribute_array[Sqloo_Schema::PARENT_ON_DELETE]." ON UPDATE ".$foreign_key_attribute_array[Sqloo_Schema::PARENT_ON_UPDATE];
	}

	protected function _dropForeignKey( $table_name, $column_name, $foreign_key_name )
	{
		$this->_alter_table_data[$table_name]["list"][] = "DROP FOREIGN KEY \"".$foreign_key_name."\"";
	}
	
}

?>