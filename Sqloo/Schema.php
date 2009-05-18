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

require_once( "Datatypes.php" );

/** @access private */

abstract class Sqloo_Schema
{
	
	private $_column_default_attributes = array(
		Sqloo::COLUMN_DATA_TYPE => array(
			"type" => Sqloo::DATATYPE_INTEGER,
			"size" => 4
		),
		Sqloo::COLUMN_ALLOW_NULL => FALSE,
		Sqloo::COLUMN_DEFAULT_VALUE => NULL,
		Sqloo::COLUMN_PRIMARY_KEY => FALSE,
		Sqloo::COLUMN_AUTO_INCREMENT => FALSE
	);
		
	private $_id_column_attributes = array(
		Sqloo::COLUMN_PRIMARY_KEY => TRUE,
		Sqloo::COLUMN_AUTO_INCREMENT => TRUE
	);
	
	private $_index_default_attributes = array(
		Sqloo::INDEX_UNIQUE => FALSE
	);
	
	private $_foreign_key_default_attributes = array(
		Sqloo::PARENT_ON_DELETE => Sqloo::ACTION_CASCADE,
		Sqloo::PARENT_ON_UPDATE => Sqloo::ACTION_CASCADE
	);
	
	protected $_sqloo;
	protected $_database_configuration;
	protected $_alter_table_data;
	
	public function __construct( $sqloo )
	{
		$this->_sqloo = $sqloo;
	}
	
	public function checkSchema()
	{
		$this->_database_configuration = $this->_sqloo->_getDatabaseConfiguration( Sqloo::QUERY_MASTER );
		$all_tables = $this->_sqloo->_getAllTables();
		
		//reset alter array
		$this->_alter_table_data = array();

		//build query
		$table_array = $this->_getTableArray();
		$this->_getTableDifference(
			$table_array,
			$this->_getTargetTableDataArray( $all_tables )
		);
		$this->_getColumnDifference(
			$this->_getColumnDataArray( $table_array ),
			$this->_getTargetColumnDataArray( $all_tables )
		);
		$this->_getIndexDifference(
			$this->_getIndexDataArray( $table_array ),
			$this->_getTargetIndexDataArray( $all_tables )
		);
		$this->_getForeignKeyDifference(
			$this->_getForeignKeyDataArray(),
			$this->_getTargetForeignKeyDataArray( $all_tables )
		);
		
		//correct the tables
		$this->_sqloo->beginTransaction();
		try {
			$log_string = $this->_executeAlterQuery();		
			$this->_sqloo->commitTransaction();
		} catch( Exception $e ) {
			$this->_sqloo->rollbackTransaction();
			$log_string = "Schema Change Failed, Rolling back.";
		}
		
		return $log_string;
	}
	
	/* Target Array functions */
	
	private function _getTargetTableDataArray( $all_tables )
	{
		$target_table_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			$target_table_array[$table_name] = NULL;
		}
		return $target_table_array;
	}
	
	private function _getTargetColumnDataArray( $all_tables )
	{
		$target_column_data_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			//every table has an id column
			$target_column_data_array[$table_name]["id"] = array_merge(
				$this->_column_default_attributes,
				$this->_id_column_attributes
			);
			
			//add normal attribute columns
			foreach( $table_class->column as $column_name => $column_attribute_array ) {
				$target_column_data_array[$table_name][$column_name] = array_merge(
					$this->_column_default_attributes,
					$column_attribute_array
				);		
			}
			
			//add join (fk) columns
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$target_column_data_array[$table_name][$join_column_name] = array_merge(
					$this->_column_default_attributes,
					$parent_attribute_array
				);		
			}
		}
		return $target_column_data_array;
	}
	
	private function _getTargetIndexDataArray( $all_tables )
	{
		$target_index_data_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			foreach( $table_class->index as $index_attribute_array ) {
				$target_index_data_array[ $table_name ][] = array_merge(
					$this->_index_default_attributes,
					$index_attribute_array
				);		
			}
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$target_index_data_array[ $table_name ][] = array(
					Sqloo::INDEX_COLUMN_ARRAY => array( $join_column_name ),
					Sqloo::INDEX_UNIQUE => FALSE
				);		
			}
		}
		return $target_index_data_array;
	}
	
	private function _getTargetForeignKeyDataArray( $all_tables )
	{
		$target_foreign_key_data_array = array();
		foreach( $all_tables as $table_name => $table_class ) {
			foreach( $table_class->parent as $join_column_name => $parent_attribute_array ) {
				$target_foreign_key_data_array[ $table_name ][ $join_column_name ] = array_merge( 
					$this->_foreign_key_default_attributes,
					array(
						"target_table_name" => $parent_attribute_array[Sqloo::PARENT_TABLE_NAME],
						"target_column_name" => "id",
						Sqloo::PARENT_ON_DELETE => $parent_attribute_array[Sqloo::PARENT_ON_DELETE],
						Sqloo::PARENT_ON_UPDATE => $parent_attribute_array[Sqloo::PARENT_ON_UPDATE]
					)
				);	
			}
		}
		return $target_foreign_key_data_array;
	}

	/* Different function */

	private function _getForeignKeyDifference( $foreign_key_data_array, $target_foreign_key_data_array )
	{
		foreach( $target_foreign_key_data_array as $table_name => $table_foreign_key_data ) {
			//search for good foreign keys that exists
			foreach( $table_foreign_key_data as $column_name => $target_foreign_key_attribute_array ){
				//look for the foreign key in the actual database
				$key_found = FALSE;
				if( array_key_exists( $table_name, $foreign_key_data_array ) && array_key_exists( $column_name, $foreign_key_data_array[$table_name] ) ) {
					foreach( $foreign_key_data_array[$table_name][$column_name] as $foreign_key_name => $foreign_key_attributes_array ) {
						if( ( $foreign_key_attributes_array[Sqloo::PARENT_ON_DELETE] === $foreign_key_attributes_array[Sqloo::PARENT_ON_DELETE] ) &&
							( $foreign_key_attributes_array[Sqloo::PARENT_ON_UPDATE] === $foreign_key_attributes_array[Sqloo::PARENT_ON_UPDATE] ) &&
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
					$this->_addForeignKey( $table_name, $column_name, $target_foreign_key_attribute_array );
				}
			}
		}
		//make a list of bad foreign keys
		foreach( $foreign_key_data_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $foreign_key_array ) {
				foreach( $foreign_key_array as $foreign_key_name => $foreign_key_attribute_array ) {
					$this->_dropForeignKey( $table_name, $column_name, $foreign_key_name );
				}
			}
		}
	}
	
	private function _getIndexDifference( $index_data_array, $target_index_data_array )
	{
		foreach( $target_index_data_array as $table_name => $target_index_array ) {
			foreach( $target_index_array as $target_index_attribute_array ) {
				$index_found = FALSE;
				if( array_key_exists( $table_name, $index_data_array ) ) {
					foreach( $index_data_array[$table_name] as $index_name => $index_attribute_array ) {
						if( ! ( count( array_diff_assoc( $index_attribute_array[Sqloo::INDEX_COLUMN_ARRAY], $target_index_attribute_array[Sqloo::INDEX_COLUMN_ARRAY] ) ) ) &&
							( $index_attribute_array[Sqloo::INDEX_UNIQUE] === $target_index_attribute_array[Sqloo::INDEX_UNIQUE] )
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
					$this->_addIndex( $table_name, $target_index_attribute_array );
				}
			}
		}
		//make a list of bad index on that table
		foreach( $index_data_array as $table_name => $index_array ) {
			foreach( $index_array as $index_name => $index_attribute_array ) {
				$this->_dropIndex( $table_name, $index_name );
			}		
		}
	}
	
	private function _getColumnDifference( $column_data_array, $target_column_data_array)
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
					if( ( $this->_sqloo->getTypeString( $target_column_attribute_array[Sqloo::COLUMN_DATA_TYPE] ) === $column_attribute_array[Sqloo::COLUMN_DATA_TYPE] ) &&
						( $target_column_attribute_array[Sqloo::COLUMN_ALLOW_NULL] === $column_attribute_array[Sqloo::COLUMN_ALLOW_NULL] ) &&
						( $target_column_attribute_array[Sqloo::COLUMN_DEFAULT_VALUE] === $column_attribute_array[Sqloo::COLUMN_DEFAULT_VALUE] ) &&
						( $target_column_attribute_array[Sqloo::COLUMN_PRIMARY_KEY] === $column_attribute_array[Sqloo::COLUMN_PRIMARY_KEY] ) &&
						( $target_column_attribute_array[Sqloo::COLUMN_AUTO_INCREMENT] === $column_attribute_array[Sqloo::COLUMN_AUTO_INCREMENT] )
					) {
						$column_matches = TRUE;
						unset( $column_data_array[$table_name][$column_name] );
					}
				}
				if( ! $column_found ) {
					$this->_addColumn( $table_name, $column_name, $target_column_attribute_array );
					unset( $column_data_array[$table_name][$column_name] );
				} else if( $column_found && ( ! $column_matches ) ) {
					$this->_alterColumn( $table_name, $column_name, $target_column_attribute_array, $column_data_array[$table_name][$column_name] );
					unset( $column_data_array[$table_name][$column_name] );
				}
			}
		}
		
		foreach( $column_data_array as $table_name => $column_array ) {
			foreach( $column_array as $column_name => $column_attribute_array ) {
				$this->_removeColumn( $table_name, $column_name );
			}
		}
	}
	
	private function _getTableDifference( $table_array, $target_table_array )
	{
		foreach( $table_array as $query_key => $table_name ) {
			if( array_key_exists( $table_name, $target_table_array ) ) {
				unset( $target_table_array[$table_name] );
			} else {
				$this->_removeTable( $table_name );
			}
		}
		foreach( $target_table_array as $table_name => $place_holder ) {
			$this->_addTable( $table_name );
		}
	}
	
	/* Schema changes */
	
	protected function _addTable( $table_name, $engine_name = NULL, $default_charset = "utf8" )
	{
		$this->_alter_table_data[$table_name]["action"] = "add";
		$this->_alter_table_data[$table_name]["info"] = array( "default_charset" => $default_charset, "engine" => $engine_name );
		
	}
		
	protected function _removeTable( $table_name )
	{
		$this->_alter_table_data[$table_name]["action"] = "drop";
	}
	
	protected function _addColumn( $table_name, $column_name, $column_attributes )
	{
		$this->_alter_table_data[$table_name]["column"][$column_name] = array( "action" => "add", "info" => $column_attributes );
	}
	
	protected function _removeColumn( $table_name, $column_name )
	{
		$this->_alter_table_data[$table_name]["column"][$column_name] = array( "action" => "drop" ); //no info needed
	}
	
	protected function _alterColumn( $table_name, $column_name, $target_attribute_array, $current_attribute_array )
	{	
		$this->_alter_table_data[$table_name]["column"][$column_name] = array(
			"action" => "alter",
			"info" => array(
				"target" => $target_attribute_array,
				"current" => $current_attribute_array
			)
		);
	}
	
	protected function _addIndex( $table_name, $index_attribute_array )
	{
		$this->_alter_table_data[$table_name]["index"][ $this->_getIndexName( $index_attribute_array ) ] = array( "action" => "add", "info" => $index_attribute_array );
	}
	
	protected function _dropIndex( $table_name, $index_name )
	{
		$this->_alter_table_data[$table_name]["index"][] = array( "action" => "drop", "info" => $index_name );
	}
	
	protected function _getIndexName( $index_attribute_array )
	{
		return "index".(string)rand();
	}
	
	protected function _addForeignKey( $table_name, $column_name, $foreign_key_attribute_array )
	{
		$this->_alter_table_data[$table_name]["fk"][$column_name][ $this->_getForeignKeyName( $table_name, $column_name, $foreign_key_attribute_array ) ] = array( "action" => "add", "info" => $foreign_key_attribute_array );
	}

	protected function _dropForeignKey( $table_name, $column_name, $foreign_key_name )
	{
		$this->_alter_table_data[$table_name]["fk"][$column_name][$foreign_key_name] = array( "action" => "drop", "info" => $foreign_key_name );
	}
	
	protected function _getForeignKeyName( $table_name, $column_name, $foreign_key_attribute_array )
	{
		return "fk".(string)rand();
	}
	
}

?>