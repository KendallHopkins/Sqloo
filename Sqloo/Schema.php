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

require_once( "Query/Results.php" );

/** @access private */

class Sqloo_Schema
{
	
	const id_data_type = "int(10) unsigned";
	static private $_id_column_attributes = array( Sqloo::primary_key => TRUE, Sqloo::auto_increment => TRUE );
	static private $_column_default_attributes = array( Sqloo::data_type => self::id_data_type, Sqloo::allow_null => FALSE, Sqloo::default_value => NULL, Sqloo::primary_key => FALSE, Sqloo::auto_increment => FALSE );
	static private $_foreign_key_default_attributes = array( Sqloo::on_delete => Sqloo::action_cascade, Sqloo::on_update => Sqloo::action_cascade );
	static private $_alter_table_data;
		
	static public function checkSchema( $all_tables, $database_resource, $database_configuration )
	{
		//reset alter array
		self::$_alter_table_data = array();
		
		//build query
		$table_array = self::_getTableArray( $database_resource );
		self::_getTableDifference(
			$table_array,
			self::_getTargetTableDataArray( $all_tables )
		);
		self::_getColumnDifference(
			self::_getColumnDataArray( $database_resource, $table_array ),
			self::_getTargetColumnDataArray( $all_tables )
		);
		self::_getIndexDifference(
			self::_getIndexDataArray( $database_resource, $table_array ),
			self::_getTargetIndexDataArray( $all_tables )
		);
		self::_getForeignKeyDifference(
			self::_getForeignKeyDataArray( $database_resource, $database_configuration ),
			self::_getTargetForeignKeyDataArray( $all_tables )
		);
		
		//correct the tables
		return self::_executeAlterQuery( $database_resource );		
	}
	
	/* Correction function */
	
	static private function _executeAlterQuery( $database_resource )
	{
		$log_string = "";
		if( count( self::$_alter_table_data ) > 0 ) {
			self::_query( "SET FOREIGN_KEY_CHECKS=0;", $database_resource );
			foreach( self::$_alter_table_data as $table_name => $table_query_info_array ) {
				$query_string = "";
				if( array_key_exists( "create", $table_query_info_array ) ) {
					$query_string .= "CREATE TABLE `".$table_name."`(\n";
				} else {
					$query_string .= "ALTER TABLE `".$table_name."`\n";
				}
				
				$query_string .= implode( ",\n", $table_query_info_array["list"] );
				
				if( array_key_exists( "create", $table_query_info_array ) ) {
					$query_string .= "\n) ENGINE=".$table_query_info_array["create"]["engine"]." DEFAULT CHARSET=".$table_query_info_array["create"]["default_charset"].";";
					$query_string = str_replace( //Alter syntax to Create syntax
						array( "ADD COLUMN ", 	"ADD PRIMARY KEY ", "ADD INDEX ",	"ADD UNIQUE INDEX ",	"ADD FOREIGN KEY "	), 
						array( "",				"PRIMARY KEY ",		"INDEX ",		"UNIQUE INDEX ",		"FOREIGN KEY "		),
						$query_string
					);
				} else {
					$query_string .= ";";
				}
				self::_query( $query_string, $database_resource );
				$log_string .= $query_string."\n";
			}
			self::_query( "SET FOREIGN_KEY_CHECKS=1;", $database_resource );
		}
		return $log_string;
	}
	
	/* Data Fetching functions */
	
	static private function _getTableArray( $database_resource )
	{
		$table_array = array();
		$query_resource = self::_query( "SHOW TABLES;", $database_resource );
		while( $row = mysql_fetch_assoc( $query_resource ) ) {
			$table_array[] = end($row);
		}
		return $table_array;
	}
	
	static private function _getColumnDataArray( $database_resource, $table_array )
	{
		$column_data_array = array();
		foreach( $table_array as $table_name ) {
			$column_data = array();
			$query_resource = self::_query( "SHOW COLUMNS FROM `".$table_name."`;", $database_resource );
			while( $row = mysql_fetch_assoc( $query_resource ) ) {
				$column_data[ $row["Field"] ] = array(
					Sqloo::data_type => $row["Type"],
					Sqloo::allow_null => ( $row["Null"] === "YES" ),
					Sqloo::default_value => $row["Default"],
					Sqloo::primary_key => ( $row["Key"] === "PRI" ),
					Sqloo::auto_increment => (bool)preg_match( "/auto_increment/i", $row["Extra"] )
				);
			}
			$column_data_array[$table_name] = $column_data;
		}
		return $column_data_array;
	}
	
	static private function _getIndexDataArray( $database_resource, $table_array )
	{
		$index_data_array = array();
		foreach( $table_array as $table_name ) {
			$index_data = array();
			$query_resource = self::_query( "SHOW INDEXES FROM `".$table_name."`;", $database_resource );
			while( $row = mysql_fetch_assoc( $query_resource ) ) {
				if( $row["Key_name"] !== "PRIMARY" ) {
					$index_data[ $row["Key_name"] ][Sqloo::column_array][ $row["Seq_in_index"] - 1 ] = $row["Column_name"];
					$index_data[ $row["Key_name"] ][Sqloo::unique] = ( $row["Non_unique"] === "0" );
				}
			}
			$index_data_array[$table_name] = $index_data;
		}
		return $index_data_array;
	}
	
	static private function _getForeignKeyDataArray( $database_resource, $database_configuration )
	{
		//This is very very hacky
		$foreign_key_data_array = array();
		$query_string = "SELECT\n";
		$query_string .= "ke.referenced_table_name referenced_table_name,\n";
		$query_string .= "ke.table_name table_name,\n";
		$query_string .= "ke.column_name column_name,\n";
		$query_string .= "ke.referenced_column_name referenced_column_name,\n";
		$query_string .= "ke.constraint_name constraint_name\n";
		$query_string .= "FROM\n";
		$query_string .= "information_schema.KEY_COLUMN_USAGE ke\n";
		$query_string .= "WHERE\n";
		$query_string .= "ke.referenced_table_name IS NOT NULL &&\n";
		$query_string .= "ke.TABLE_SCHEMA = '".$database_configuration["database_name"]."';";
		$query_resource = self::_query( $query_string, $database_resource );
		while( $row = mysql_fetch_assoc( $query_resource ) ) {
			$current_attribute_array = self::_getForeignKeyAttributeArray( $database_resource, $row["table_name"], $row["column_name"] );
			$foreign_key_data_array[ $row["table_name"] ][ $row["column_name"] ][ $row["constraint_name"] ] = array( 
				"target_table_name" => $row["referenced_table_name"],
				"target_column_name" => $row["referenced_column_name"],
				Sqloo::on_delete => $current_attribute_array[Sqloo::on_delete],
				Sqloo::on_update => $current_attribute_array[Sqloo::on_update]
			);
		}
		return $foreign_key_data_array;
	}
	
	static private function _getForeignKeyAttributeArray( $database_resource, $table_name, $column_name )
	{
		$attribute_array = array( Sqloo::on_delete => Sqloo::action_no_action, Sqloo::on_update => Sqloo::action_no_action );
		$query_resource = self::_query( "SHOW CREATE TABLE `".$table_name."`;", $database_resource );
		$create_table_array = mysql_fetch_assoc( $query_resource );
		$create_table_string_array = explode( "\n", $create_table_array["Create Table"] );
		foreach( $create_table_string_array as $string )
			if( substr_count( $string, "FOREIGN KEY (`".$column_name."`)" ) > 0 )
				foreach( array( Sqloo::on_delete => "ON DELETE", Sqloo::on_update => "ON UPDATE" ) as $type_id => $type_string )
					foreach( array( Sqloo::action_restrict, Sqloo::action_cascade, Sqloo::action_set_null, Sqloo::action_no_action ) as $action )
						if( preg_match( "/".$type_string." ".$action."/i", $string ) )
							$attribute_array[$type_id] = $action;
		
		return $attribute_array;
	}
	
	/* Target Array functions */
	
	static private function _getTargetTableDataArray( $all_tables )
	{
		$target_table_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			$target_table_array[$table_name] = NULL;
		}
		return $target_table_array;
	}
	
	static private function _getTargetColumnDataArray( $all_tables )
	{
		$target_column_data_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			$target_column_data_array[$table_name]["id"] = array_merge( self::$_column_default_attributes, self::$_id_column_attributes ); //every table has an id column
			foreach( $table_class->column as $column_name => $column_attribute_array ) {
				$target_column_data_array[$table_name][$column_name] = array_merge( self::$_column_default_attributes, $column_attribute_array );		
			}
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$target_column_data_array[$table_name][$join_column_name] = array_merge( self::$_column_default_attributes, $parent_attribute_array );  //this allows the user to override attributes if they desire			
			}
		}
		return $target_column_data_array;
	}
	
	static private function _getTargetIndexDataArray( $all_tables )
	{
		$target_index_data_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			foreach( $table_class->index as $index_attribute_array ) {
				$target_index_data_array[ $table_name ][] = $index_attribute_array;		
			}
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$target_index_data_array[ $table_name ][] = array( Sqloo::column_array => array( $join_column_name ), Sqloo::unique => FALSE );		
			}
		}
		return $target_index_data_array;
	}
	
	static private function _getTargetForeignKeyDataArray( $all_tables )
	{
		$target_foreign_key_data_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$target_foreign_key_data_array[ $table_name ][ $join_column_name ] = array(
					"target_table_name" => $parent_attribute_array[Sqloo::parent_table_name],
					"target_column_name" => "id",
					Sqloo::on_delete => $parent_attribute_array[Sqloo::on_delete],
					Sqloo::on_update => $parent_attribute_array[Sqloo::on_update]
				);	
			}
		}
		return $target_foreign_key_data_array;
	}

	/* Different function */

	static private function _getForeignKeyDifference( $foreign_key_data_array, $target_foreign_key_data_array )
	{
		foreach( $target_foreign_key_data_array as $table_name => $table_foreign_key_data ) {
			//search for good foreign keys that exists
			foreach( $table_foreign_key_data as $column_name => $target_foreign_key_attribute_array ){
				//look for the foreign key in the actual database
				$key_found = FALSE;
				if( array_key_exists( $table_name, $foreign_key_data_array ) && array_key_exists( $column_name, $foreign_key_data_array[$table_name] ) ) {
					foreach( $foreign_key_data_array[$table_name][$column_name] as $foreign_key_name => $foreign_key_attributes_array ) {
						if( ( $foreign_key_attributes_array[Sqloo::on_delete] === $foreign_key_attributes_array[Sqloo::on_delete] ) &&
							( $foreign_key_attributes_array[Sqloo::on_update] === $foreign_key_attributes_array[Sqloo::on_update] ) &&
							( $foreign_key_attributes_array["target_table_name"] === $foreign_key_attributes_array["target_table_name"] ) &&
							( $foreign_key_attributes_array["target_column_name"] === $foreign_key_attributes_array["target_column_name"] )
						) {
							//we found it!
							$key_found = TRUE;
							unset( $foreign_key_data_array[$table_name][$column_name][$foreign_key_name] );
							break;
						}
					}
				}
				//mark for adding if it doesn't exists
				if( ! $key_found ) {
					self::_addForeignKey( $table_name, $column_name, $target_foreign_key_attribute_array );
				}
			}
		}
		//make a list of bad foreign keys
		foreach( $foreign_key_data_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $foreign_key_array ) {
				foreach( $foreign_key_array as $foreign_key_name => $foreign_key_attribute_array ) {
					self::_dropForeignKey( $table_name, $foreign_key_name );
				}
			}
		}
	}
	
	static private function _getIndexDifference( $index_data_array, $target_index_data_array )
	{
		foreach( $target_index_data_array as $table_name => $target_index_array ) {
			foreach( $target_index_array as $target_index_attribute_array ) {
				$index_found = FALSE;
				if( array_key_exists( $table_name, $index_data_array ) ) {
					foreach( $index_data_array[$table_name] as $index_name => $index_attribute_array ) {
						if( ( count( array_diff_assoc( $index_attribute_array[Sqloo::column_array], $target_index_attribute_array[Sqloo::column_array] ) ) === 0 ) &&
							( $index_attribute_array[Sqloo::unique] === $target_index_attribute_array[Sqloo::unique] )
						) {
							//we found the index
							$index_found = TRUE;
							unset( $index_data_array[$table_name][$index_name] );
							break;
						}
					}
				}
				//not found, mark it to add
				if( ! $index_found ) {
					self::_addIndex( $table_name, $target_index_attribute_array );
				}
			}
		}
		//make a list of bad index on that table
		foreach( $index_data_array as $table_name => $index_array ) {
			foreach( $index_array as $index_name => $index_attribute_array ) {
				self::_dropIndex( $table_name, $index_name );
			}		
		}
	}
	
	static private function _getColumnDifference( $column_data_array, $target_column_data_array)
	{
		$modify_array = array();
		foreach( $target_column_data_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $target_column_attribute_array ) {
				$column_found = FALSE;
				$column_matches = FALSE;
				if( array_key_exists( $table_name, $column_data_array ) &&
					array_key_exists( $column_name, $column_data_array[$table_name] )
				) {
					$column_found = TRUE;
					$column_attribute_array = $column_data_array[$table_name][$column_name];
					if( ( $target_column_attribute_array[Sqloo::data_type] === $column_attribute_array[Sqloo::data_type] ) &&
						( $target_column_attribute_array[Sqloo::allow_null] === $column_attribute_array[Sqloo::allow_null] ) &&
						( $target_column_attribute_array[Sqloo::default_value] === $column_attribute_array[Sqloo::default_value] ) &&
						( $target_column_attribute_array[Sqloo::primary_key] === $column_attribute_array[Sqloo::primary_key] ) &&
						( $target_column_attribute_array[Sqloo::auto_increment] === $column_attribute_array[Sqloo::auto_increment] )
					) {
						$column_matches = TRUE;
						unset( $column_data_array[$table_name][$column_name] );
					}
				}
				if( ! $column_found ) {
					self::_addColumn( $table_name, $column_name, $target_column_attribute_array );
					unset( $column_data_array[$table_name][$column_name] );
				} else if( $column_found && ( ! $column_matches ) ) {
					self::_alterColumn( $table_name, $column_name, $target_column_attribute_array, $column_data_array[$table_name][$column_name] );
					unset( $column_data_array[$table_name][$column_name] );
				}
			}
		}
		
		foreach( $column_data_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $column_attribute_array ) {
				self::_removeColumn( $table_name, $column_name );
			}
		}
	}
	
	static private function _getTableDifference( $table_array, $target_table_array )
	{
		foreach( $table_array as $table_name ) {
			if( array_key_exists( $table_name, $target_table_array ) ) {
				unset( $target_table_array[$table_name] );
			} else {
				//self::_removeTable( $table_name );
			}
		}
		foreach( $target_table_array as $table_name => $place_holder ) {
			self::_addTable( $table_name );
		}
	}	
	
	/* Database interface functions */
	
	static private function _addTable( $table_name, $engine_name = "InnoDB", $default_charset = "utf8" )
	{
		self::$_alter_table_data[$table_name]["create"] = array( "default_charset" => $default_charset, "engine" => $engine_name );
	}
	
	/*
	static private function _removeTable( $table_name )
	{
		self::_sqloo->query( "DROP TABLE `".$table_name."`;" );
	}
	*/
	
	static private function _addColumn( $table_name, $column_name, $column_attributes )
	{
		self::$_alter_table_data[$table_name]["list"][] = "ADD COLUMN `".$column_name."` ".self::_buildFullTypeString( $column_attributes );
		if( $column_attributes[Sqloo::primary_key] ) self::$_alter_table_data[$table_name]["list"][] = "ADD PRIMARY KEY (`".$column_name."`)";	}
	
	static private function _removeColumn( $table_name, $column_name )
	{
		self::$_alter_table_data[$table_name]["list"][] = "DROP COLUMN `".$column_name."`";
	}
	
	static private function _alterColumn( $table_name, $column_name, $target_attribute_array, $current_attribute_array )
	{	
		self::$_alter_table_data[$table_name]["list"][] = "MODIFY COLUMN `".$column_name."` ".self::_buildFullTypeString( $target_attribute_array );
		if( $target_attribute_array[Sqloo::primary_key] && ( ! $current_attribute_array[Sqloo::primary_key] ) ) self::$_alter_table_data[$table_name]["list"][] = "ADD PRIMARY KEY (`".$column_name."`)";
		if( ( ! $target_attribute_array[Sqloo::primary_key] ) && $current_attribute_array[Sqloo::primary_key] ) self::$_alter_table_data[$table_name]["list"][] = "DROP PRIMARY KEY";
	}
	
	static private function _buildFullTypeString( $target_attribute_array )
	{
		$full_type_string = $target_attribute_array[Sqloo::data_type];
		$full_type_string .= ( $target_attribute_array[Sqloo::allow_null] ) ? " NULL" : " NOT NULL";
		$full_type_string .= ( $target_attribute_array[Sqloo::default_value] !== NULL ) ? " DEFAULT '".$target_attribute_array[Sqloo::default_value]."'" : "";
		$full_type_string .= ( $target_attribute_array[Sqloo::auto_increment] ) ? " AUTO_INCREMENT" : "";
		return $full_type_string;
	}
	
	static private function _addIndex( $table_name, $index_attribute_array )
	{
		$index_name = self::_getIndexName( $index_attribute_array );
		$query_string = "ADD ";
		if( $index_attribute_array[Sqloo::unique] ) $query_string .= "UNIQUE ";
		$query_string .= "INDEX `".$index_name."` ( `".implode( "`,`", $index_attribute_array[Sqloo::column_array] )."` )";
		self::$_alter_table_data[$table_name]["list"][] = $query_string;
	}
	
	static private function _dropIndex( $table_name, $index_name )
	{
		self::$_alter_table_data[$table_name]["list"][] = "DROP INDEX `".$index_name."`";
	}
	
	static private function _getIndexName( $index_attribute_array )
	{
		return (string)rand();
	}
	
	static private function _addForeignKey( $table_name, $column_name, $foreign_key_attribute_array )
	{
		self::$_alter_table_data[$table_name]["list"][] = "ADD FOREIGN KEY ( `".$column_name."` ) REFERENCES `".$foreign_key_attribute_array["target_table_name"]."` ( `".$foreign_key_attribute_array["target_column_name"]."` ) ON DELETE ".$foreign_key_attribute_array[Sqloo::on_delete]." ON UPDATE ".$foreign_key_attribute_array[Sqloo::on_update];
	}

	static private function _dropForeignKey( $table_name, $foreign_key_name )
	{
		self::$_alter_table_data[$table_name]["list"][] = "DROP FOREIGN KEY `".$foreign_key_name."`";
	}
	
	static private function _query( $query_string, $database_resource )
	{
		$query_resource = mysql_query( $query_string, $database_resource );
		if ( ! $query_resource ) trigger_error( mysql_error( $database_resource )."<br>\n".$query_string, E_USER_ERROR );
		return $query_resource;
	}
	
}

?>