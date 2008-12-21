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

class Sqloo_Schema
{
	
	const id_data_type = "int(10) unsigned";
	private $_sqloo;
	private $_id_column_attributes = array( Sqloo::data_type => self::id_data_type, Sqloo::allow_null => FALSE, Sqloo::default_value => NULL, Sqloo::primary_key => TRUE, Sqloo::auto_increment => TRUE );
	private $_column_default_attributes = array( Sqloo::data_type => self::id_data_type, Sqloo::allow_null => FALSE, Sqloo::default_value => NULL, Sqloo::primary_key => FALSE, Sqloo::auto_increment => FALSE );
	private $_foreign_key_default_attributes = array( Sqloo::on_delete => Sqloo::action_cascade, Sqloo::on_update => Sqloo::action_cascade );
	
	//DB
	private $_table_array;
	private $_column_data_array;
	private $_index_data_array;
	private $_foreign_key_data_array;
	
	//Target
	private $_target_table_array;
	private $_target_column_data_array;
	private $_target_index_data_array;
	private $_target_foreign_key_data_array;
		
	public function __construct( $sqloo ) { $this->_sqloo = $sqloo; }
	
	public function checkSchema()
	{
		$this->_sqloo->beginTransaction();
		$this->_sqloo->query( "SET FOREIGN_KEY_CHECKS=0;" );
		$this->_refreshTableArray();
		$this->_refreshColumnDataArray();
		$this->_refreshIndexDataArray();
		$this->_refreshForeignKeyDataArray();
		$this->_refreshTargetTableDataArray();
		$this->_refreshTargetColumnDataArray();
		$this->_refreshTargetIndexDataArray();
		$this->_refreshTargetForeignKeyDataArray();		
		$foreign_key_difference_array = $this->_getForeignKeyDifferenceArray();
		$index_difference_array = $this->_getIndexDifferenceArray();
		$column_difference_array = $this->_getColumnDifferenceArray();
		$table_difference_array = $this->_getTableDifferenceArray();
		$log_string = $this->_removeUnneededForeignKeys( $foreign_key_difference_array["delete"] );
		$log_string .= $this->_removeUneededIndexes( $index_difference_array["delete"] );
		$log_string .= $this->_removeUnneededColumns( $column_difference_array["delete"] );
		$log_string .= $this->_alterDifferentColumns( $column_difference_array["modify"] );
		$log_string .= $this->_addMissingTables( $table_difference_array["add"] );
		$log_string .= $this->_addMissingColumns( $column_difference_array["add"] );
		$log_string .= $this->_addMissingIndexes( $index_difference_array["add"] );
		$log_string .= $this->_addMissingForeignKeys( $foreign_key_difference_array["add"] );
		$this->_sqloo->query( "SET FOREIGN_KEY_CHECKS=1;" );
		$this->_sqloo->commitTransaction();
		return $log_string;
	}
	
	/* Data Fetching */
	
	function _refreshTableArray()
	{
		$this->_table_array = array();
		$results = new Sqloo_Query_Results( $this->_sqloo->query( "SHOW TABLES;" ) );
		while( $row = $results->fetchRow() ) {
			$this->_table_array[] = end($row);
		}
	}
	
	function _refreshColumnDataArray()
	{
		$this->_column_data_array = array();
		foreach( $this->_table_array as $table_name ) $this->_column_data_array[$table_name] = $this->_getColumnDataArray( $table_name );
	}
	
	function _getColumnDataArray( $table_name )
	{
		$column_data_array = array();
		$results = new Sqloo_Query_Results( $this->_sqloo->query( "SHOW COLUMNS FROM `".$table_name."`;" ) );
		while( $row = $results->fetchRow() ) {
			$column_data_array[ $row["Field"] ] = array(
				Sqloo::data_type => $row["Type"],
				Sqloo::allow_null => ( $row["Null"] === "YES" ),
				Sqloo::default_value => $row["Default"],
				Sqloo::primary_key => ( $row["Key"] === "PRI" ),
				Sqloo::auto_increment => (bool)preg_match( "/auto_increment/i", $row["Extra"] )
			);
		}
		return $column_data_array;
	}
	
	function _refreshIndexDataArray()
	{
		$this->_index_data_array = array();
		foreach( $this->_table_array as $table_name ) $this->_index_data_array[$table_name] = $this->_getIndexDataArray( $table_name );
	}
	
	function _getIndexDataArray( $table_name )
	{
		$index_data_array = array();
		$results = new Sqloo_Query_Results( $this->_sqloo->query( "SHOW INDEXES FROM `".$table_name."`;" ) );
		while( $row = $results->fetchRow() ) {
			if( $row["Key_name"] !== "PRIMARY" ) {
				$index_data_array[ $row["Key_name"] ][Sqloo::column_array][ $row["Seq_in_index"] - 1 ] = $row["Column_name"];
				$index_data_array[ $row["Key_name"] ][Sqloo::unique] = ( $row["Non_unique"] === "0" );
			}
		}
		return $index_data_array;
	}
	
	function _refreshForeignKeyDataArray()
	{
		//This is very very hacky
		$db_name = $this->_sqloo->getMasterDatabaseName();
		$this->_foreign_key_data_array = array();
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
		$query_string .= "ke.TABLE_SCHEMA = '".$db_name."';";
		$results = new Sqloo_Query_Results( $this->_sqloo->query( $query_string ) );
		while( $row = $results->fetchRow() ) {
			$current_attribute_array = $this->_getForeignKeyAttributeArray( array( "table" => $row["table_name"], "column" => $row["column_name"] ) );
			$this->_foreign_key_data_array[ $row["table_name"] ][ $row["column_name"] ][ $row["constraint_name"] ] = array( 
				"target_column_reference_array" => array(
					"table" => $row["referenced_table_name"],
					"column" => $row["referenced_column_name"]
				),
				Sqloo::on_delete => $current_attribute_array[Sqloo::on_delete],
				Sqloo::on_update => $current_attribute_array[Sqloo::on_update]
			);
		}
	}
	
	function _getForeignKeyAttributeArray( $column_reference_array )
	{
		$attribute_array = array( Sqloo::on_delete => Sqloo::action_no_action, Sqloo::on_update => Sqloo::action_no_action );
		$results = new Sqloo_Query_Results( $this->_sqloo->query( "SHOW CREATE TABLE `".$column_reference_array["table"]."`;" ) );
		$create_table_array = $results->fetchRow();
		$create_table_string_array = explode( "\n", $create_table_array["Create Table"] );
		foreach( $create_table_string_array as $string )
			if( substr_count( $string, "FOREIGN KEY (`".$column_reference_array["column"]."`)" ) > 0 )
				foreach( array( Sqloo::on_delete => "ON DELETE", Sqloo::on_update => "ON UPDATE" ) as $type_id => $type_string )
					foreach( array( Sqloo::action_restrict, Sqloo::action_cascade, Sqloo::action_set_null, Sqloo::action_no_action ) as $action )
						if( preg_match( "/".$type_string." ".$action."/i", $string ) )
							$attribute_array[$type_id] = $action;
		
		return $attribute_array;
	}
	
	/* Target Schema Building */
	
	function _refreshTargetTableDataArray()
	{
		foreach( $this->_sqloo->getTableSchemaData() as $table_name => $table_class ) {
			$this->_target_table_array[$table_name] = NULL;
		}
	}
	
	function _refreshTargetColumnDataArray()
	{
		$this->_target_column_data_array = array();
		foreach( $this->_sqloo->getTableSchemaData() as $table_name => $table_class ) {
			$this->_target_column_data_array[$table_name]["id"] = $this->_id_column_attributes; //every table has an id column
			foreach( $table_class->column as $column_name => $column_attribute_array ) {
				$this->_target_column_data_array[$table_name][$column_name] = array_merge( $this->_column_default_attributes, $column_attribute_array );		
			}
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$this->_target_column_data_array[$table_name][$join_column_name] = array_merge( $this->_column_default_attributes, $parent_attribute_array );  //this allows the user to override attributes if they desire			
			}
		}
	}
	
	function _refreshTargetIndexDataArray()
	{
		$this->_target_index_data_array = array();
		foreach( $this->_sqloo->getTableSchemaData() as $table_name => $table_class ) {
			foreach( $table_class->index as $index_attribute_array ) {
				$this->_target_index_data_array[ $table_name ][] = $index_attribute_array;		
			}
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$this->_target_index_data_array[ $table_name ][] = array( Sqloo::column_array => array( $join_column_name ), Sqloo::unique => FALSE );		
			}
		}
	}
	
	function _refreshTargetForeignKeyDataArray()
	{
		$this->_target_foreign_key_data_array = array();
		foreach( $this->_sqloo->getTableSchemaData() as $table_name => $table_class ) {
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$this->_target_foreign_key_data_array[ $table_name ][ $join_column_name ] = array(
					"target_column_reference_array" => array(
						"table" => $parent_attribute_array[Sqloo::parent_table_name],
						"column" => "id"
					),
					Sqloo::on_delete => $parent_attribute_array[Sqloo::on_delete],
					Sqloo::on_update => $parent_attribute_array[Sqloo::on_update]
				);	
			}
		}
	}

	function _getForeignKeyDifferenceArray()
	{
		$foreign_key_data_array = $this->_foreign_key_data_array;
		$add_array = array();
		$delete_array = array();
		foreach( $this->_target_foreign_key_data_array as $table_name => $table_foreign_key_data ) {
			//search for good foreign keys that exists
			foreach( $table_foreign_key_data as $column_name => $target_foreign_key_attribute_array ){
				//look for the foreign key in the actual database
				$key_found = FALSE;
				if( array_key_exists( $table_name, $foreign_key_data_array ) && array_key_exists( $column_name, $foreign_key_data_array[$table_name] ) ) {
					foreach( $foreign_key_data_array[$table_name][$column_name] as $foreign_key_name => $foreign_key_attributes_array ) {
						if( ( $foreign_key_attributes_array[Sqloo::on_delete] === $foreign_key_attributes_array[Sqloo::on_delete] ) &&
							( $foreign_key_attributes_array[Sqloo::on_update] === $foreign_key_attributes_array[Sqloo::on_update] ) &&
							( $foreign_key_attributes_array["target_column_reference_array"]["table"] === $foreign_key_attributes_array["target_column_reference_array"]["table"] ) &&
							( $foreign_key_attributes_array["target_column_reference_array"]["column"] === $foreign_key_attributes_array["target_column_reference_array"]["column"] )
						) {
							//we found it!
							$key_found = TRUE;
							unset( $foreign_key_data_array[$table_name][$column_name][$foreign_key_name] );
							break;
						}
					}
				}
				//mark for adding if it doesn't exists
				if( $key_found === FALSE ) {
					$add_array[$table_name][$column_name] = $target_foreign_key_attribute_array;
				}
			}
		}
		//make a list of bad foreign keys
		foreach( $foreign_key_data_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $foreign_key_array ) {
				foreach( $foreign_key_array as $foreign_key_name => $foreign_key_attribute_array ) {
					$delete_array[$table_name][] = $foreign_key_name;				
				}
			}
		}

		return array( "add" => $add_array, "delete" => $delete_array );
	}
	
	function _getIndexDifferenceArray()
	{
		$index_data_array = $this->_index_data_array;
		$add_array = array();
		$delete_array = array();
		foreach( $this->_target_index_data_array as $table_name => $target_index_array ) {
			if( array_key_exists( $table_name, $index_data_array ) ) {
				foreach( $target_index_array as $target_index_attribute_array ) {
					$index_found = FALSE;
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
					//not found, mark it to add
					if( $index_found === FALSE ) {
						$add_array[$table_name][] = $target_index_attribute_array;
					}
				}
			}
		}
		//make a list of bad index on that table
		foreach( $index_data_array as $table_name => $index_array ) {
			foreach( $index_array as $index_name => $index_attribute_array ) {
				$delete_array[$table_name][] = $index_name;
			}		
		}

		return array( "add" => $add_array, "delete" => $delete_array );
	}
	
	function _getColumnDifferenceArray()
	{
		$column_data_array = $this->_column_data_array;
		$add_array = array();
		$modify_array = array();
		$delete_array = array();
		foreach( $this->_target_column_data_array as $table_name => $column_array ) {
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
				if( $column_found === FALSE ) {
					$add_array[$table_name][$column_name] = $target_column_attribute_array;
					unset( $column_data_array[$table_name][$column_name] );
				} else if( ( $column_found === TRUE ) && ( $column_matches === FALSE ) ) {
					$modify_array[$table_name][$column_name] = array( "target" => $target_column_attribute_array, "current" => $column_data_array[$table_name][$column_name] );
					unset( $column_data_array[$table_name][$column_name] );
				}
			}
		}
		
		foreach( $column_data_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $column_attribute_array ) {
				$delete_array[$table_name][] = $column_name;
			}
		}
		
		return array( "add" => $add_array, "modify" => $modify_array ,"delete" => $delete_array );
	}
	
	function _getTableDifferenceArray()
	{
		$target_table_array = $this->_target_table_array;
		$add_array = array();
		$delete_array = array();
		foreach( $this->_table_array as $table_name ) {
			if( array_key_exists( $table_name, $target_table_array ) ) {
				unset( $target_table_array[$table_name] );
			} else {
				$delete_array[] = $table_name;
			}
		}
		foreach( $target_table_array as $table_name => $place_holder ) {
			$add_array[] = $table_name;
		}
		return array( "add" => $add_array ,"delete" => $delete_array );
	}
	
	function _removeUnneededForeignKeys( $unneeded_foreign_key_array )
	{
		$log_string = "";
		foreach( $unneeded_foreign_key_array as $table_name => $foreign_key_array ) {
			foreach( $foreign_key_array as $foreign_key_name ) {
				$log_string .= $this->_dropForeignKey( $table_name, $foreign_key_name );
			}
		}
		return $log_string;
	}
	
	function _removeUneededIndexes( $unneeded_index_array )
	{
		$log_string = "";
		foreach( $unneeded_index_array as $table_name => $index_array ) {
			foreach( $index_array as $index_name ) {
				$log_string .= $this->_dropIndex( $table_name, $index_name );
			}
		}
		return $log_string;
	}
	
	function _removeUnneededColumns( $unneeded_column_array )
	{
		$log_string = "";
		foreach( $unneeded_column_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name ) {
				$log_string .= $this->_removeColumn( $table_name, $column_name );				
			}
		}
		return $log_string;
	}
	
	function _alterDifferentColumns( $alter_column_array )
	{
		$log_string = "";
		foreach( $alter_column_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $attribute_array_array ) {
				$log_string .= $this->_alterColumn( $table_name, $column_name, $attribute_array_array["target"], $attribute_array_array["current"] );				
			}
		}
		return $log_string;
	}
	
	function _addMissingTables( $needed_table_array )
	{
		$log_string = "";
		foreach( $needed_table_array as $table_name ) {
			$log_string .= $this->_addTable( $table_name );
		}
		return $log_string;
	}
	
	function _addMissingColumns( $needed_column_array )
	{
		$log_string = "";
		foreach( $needed_column_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $attribute_array ) {
				$log_string .= $this->_addColumn( $table_name, $column_name, $attribute_array );				
			}
		}
		return $log_string;
		
	}
	
	function _addMissingIndexes( $needed_index_array )
	{
		$log_string = "";
		foreach( $needed_index_array as $table_name => $index_array ) {
			foreach( $index_array as $index_attribute_array ) {
				$log_string .= $this->_addIndex( $table_name, $index_attribute_array );
			}
		}
		return $log_string;
	}
	
	function _addMissingForeignKeys( $needed_foreign_key_array )
	{
		$log_string = "";
		foreach( $needed_foreign_key_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $foreign_key_attribute_array ) {
				$log_string .= $this->_addForeignKey( $table_name, $column_name, $foreign_key_attribute_array );
			}
		}
		return $log_string;
	}
	
	private function _addTable( $table_name, $engine_name = "InnoDB", $character_set_name = "utf8" )
	{
		$this->_sqloo->query( "CREATE TABLE `".$table_name."` (id int) CHARACTER SET ".$character_set_name." ENGINE = ".$engine_name.";" );
		return "creating table: ".$table_name."<br>\n";
	}
	
	private function _removeTable( $table_name )
	{
		$this->_sqloo->query( "DROP TABLE `".$table_name."`;" );
		return "removing table: ".$table_name."<br>\n";
	}
	
	private function _addColumn( $table_name, $column_name, $column_attributes )
	{			
		$query_string = "ALTER TABLE `".$table_name."`\n";
		$query_string .= "ADD COLUMN `".$column_name."` ".$this->_buildFullTypeString( $column_attributes );
		if( $column_attributes[Sqloo::primary_key] ) $query_string .= ",\n ADD PRIMARY KEY(`".$column_name."`)";
		$query_string .= ";";
		
		$this->_sqloo->query( $query_string );
		return "creating column: ".$table_name.".".$column_name."<br>\n";
	}
	
	private function _removeColumn( $table_name, $column_name )
	{
		$this->_sqloo->query( "ALTER TABLE `".$table_name."`\nDROP COLUMN `".$column_name."`;" );
		return "removing column: ".$table_name.".".$column_name."<br>\n";
	}
	
	private function _alterColumn( $table_name, $column_name, $target_attribute_array, $current_attribute_array )
	{	
		$query_string = "ALTER TABLE `".$table_name."`\n";
		$query_string .= "MODIFY COLUMN `".$column_name."` ".$this->_buildFullTypeString( $target_attribute_array );
		if( ( $target_attribute_array[Sqloo::primary_key] === TRUE ) && ( $current_attribute_array[Sqloo::primary_key] === FALSE ) ) $query_string .= ",\n ADD PRIMARY KEY(`".$column_name."`)";
		if( ( $target_attribute_array[Sqloo::primary_key] === FALSE ) && ( $current_attribute_array[Sqloo::primary_key] === TRUE ) ) $query_string .= ",\n DROP PRIMARY KEY";
		$query_string .= ";";
		$this->_sqloo->query( $query_string );
		return "alter column: ".$table_name.".".$column_name."<br>\n";
	}
	
	private function _buildFullTypeString( $target_attribute_array )
	{
		$full_type_string = $target_attribute_array[Sqloo::data_type];
		$full_type_string .= ( $target_attribute_array[Sqloo::allow_null] === TRUE ) ? " NULL" : " NOT NULL";
		$full_type_string .= ( $target_attribute_array[Sqloo::default_value] !== NULL ) ? " DEFAULT '".$target_attribute_array[Sqloo::default_value]."'" : "";
		$full_type_string .= ( $target_attribute_array[Sqloo::auto_increment] === TRUE ) ? " AUTO_INCREMENT" : "";
		return $full_type_string;
	}
	
	private function _addIndex( $table_name, $index_attribute_array )
	{
		$index_name = $this->_getIndexName( $index_attribute_array );
		$query = "CREATE ";
		if( $index_attribute_array[Sqloo::unique] === TRUE ) $query .= "UNIQUE ";
		$query .= "INDEX `".$index_name."` ON `".$table_name."` ( `".implode( "`,`", $index_attribute_array[Sqloo::column_array] )."` );";
		$this->_sqloo->query( $query );
		return "adding index ".$index_name." on table ".$table_name."<br>\n";
	}
	
	private function _dropIndex( $table_name, $index_name )
	{
		$this->_sqloo->query( "DROP INDEX `".$index_name."` ON `".$table_name."`;" );
		return "dropping index ".$index_name." on table ".$table_name."<br>\n";
	}
	
	private function _getIndexName( $index_attribute_array )
	{
		return (string)rand();
	}
	
	private function _addForeignKey( $table_name, $column_name, $foreign_key_attribute_array )
	{
		$this->_sqloo->query( "ALTER TABLE `".$table_name."`\nADD FOREIGN KEY ( `".$column_name."` )\nREFERENCES `".$foreign_key_attribute_array["target_column_reference_array"]["table"]."` ( `".$foreign_key_attribute_array["target_column_reference_array"]["column"]."` )\nON DELETE ".$foreign_key_attribute_array[Sqloo::on_delete]."\nON UPDATE ".$foreign_key_attribute_array[Sqloo::on_update].";" );
		return "adding foreign key from ".$table_name.".".$column_name." to ".$foreign_key_attribute_array["target_column_reference_array"]["table"].".".$foreign_key_attribute_array["target_column_reference_array"]["column"]."<br>\n";
	}

	private function _dropForeignKey( $table_name, $foreign_key_name )
	{
		$this->_sqloo->query( "ALTER TABLE `".$table_name."`\nDROP FOREIGN KEY `".$foreign_key_name."`" );
		return "deleting foreign key from ".$table_name." called ".$foreign_key_name."<br>\n";
	}
	
}

?>