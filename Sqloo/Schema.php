<?php

class Sqloo_Schema
{
	/* 
	PUBLIC 
	*/

	public function __construct( $sqloo ) { $this->_sqloo = $sqloo; }
	
	public function checkSchema()
	{
		$string_log = "";
		$this->_sqloo->query( "SET FOREIGN_KEY_CHECKS=0" );
		$string_log .= $this->_checkTableStructures();
		$string_log .= $this->_checkForeignKeys();
        $this->_sqloo->query( "SET FOREIGN_KEY_CHECKS=1" );
        return $string_log;
	}
	
	/* 
	PRIVATE 
	*/
	
	private $_sqloo;
	private $_id_data_type = array( Sqloo_Table::data_type => "int(10) unsigned", Sqloo_Table::allow_null => FALSE, Sqloo_Table::indexed => FALSE, Sqloo_Table::primary_key => TRUE, Sqloo_Table::auto_increment => TRUE );
	
	private function _checkTableStructures()
	{
		$string_log = "";
		$results = new Sqloo_Results( $this->_sqloo->query( "SHOW TABLES;" ) );
		$table_list_array = array();
		while( $row = $results->fetchRow() ) {
			$table_list_array[ end($row) ] = NULL;
		}

		foreach ( $this->_sqloo->tables as $table_name => $table_class ) {	
			/* ensure table exists */
			$string_log .= $this->_checkTable( $table_name, $table_list_array );
			
			/* make sure our columns are correct */
			//take snapshot of columns before hand, as we check columns we remove them for this list
			//if any still exist at the end, we remove them
			$current_column_array = $this->_getColumnInfo( $table_name );

			
			//every table have a id field
			$string_log .= $this->_checkColumn( 
				array( 
					"table" => $table_name, 
					"column" => "id" 
				), 
				$this->_id_data_type,
				$current_column_array
			);
			unset( $current_column_array["id"] );
			
			//parent columns (foreign keys)
			foreach( $table_class->parents as $parent_name => $parent_attributes ) {
				$string_log .= $this->_checkColumn( 
					array( 
						"table" => $table_name, 
						"column" => $parent_name 
					), 
					array( 
						Sqloo_Table::data_type => "int(10) unsigned", 
						Sqloo_Table::allow_null => $parent_attributes[Sqloo_Table::allow_null], 
						Sqloo_Table::indexed => TRUE 
					),
					$current_column_array
				);
				unset( $current_column_array[$parent_name] );
			}
			
			//normal columns
			foreach( $table_class->columns as $column_name => $column_attributes ) {
				$string_log .= $this->_checkColumn( 
					array( 
						"table" => $table_name, 
						"column" => $column_name 
					),
					$column_attributes,
					$current_column_array
				);
				unset( $current_column_array[$column_name] );
			}
			
			//remove extras
			foreach( $current_column_array as $column_name => $column_attributes ) {
				$string_log .= $this->_removeColumn(
					array( 
						"table" => $table_name, 
						"column" => $column_name 
					)
				);
			}
		}
		return $string_log;
	}
	
	/* table functions */
	
	private function _checkTable( $table_name, $table_list_array )
	{
		$string_log = "";
		if( array_key_exists( $table_name, $table_list_array ) == FALSE ) {
			$string_log = "creating table: ".$table_name."<br>\n";
			$this->_addTable( $table_name );
		}
		return $string_log;
	}
	
	private function _tableExists( $table_name ) 
	{
		$results = new Sqloo_Results( $this->_sqloo->query( "SHOW TABLES LIKE '".$table_name."';" ) );
		return $results->countRows() > 0;
	}
	
	private function _addTable( $table_name, $engine_name = "InnoDB", $character_set_name = "utf8" )
	{
		$query_string = "CREATE TABLE `".$table_name."` (id int) CHARACTER SET ".$character_set_name." ENGINE = ".$engine_name.";";
		$this->_sqloo->query( $query_string );
	}
	
	private function _removeTable( $table_name )
	{
		$query_string = "DROP TABLE `".$table_name."`;";
		$this->_sqloo->query( $query_string );
	}
	
	/* column functions */
	
	private function _getColumnInfo( $table_name )
	{
		//refigure out columns
		$results = new Sqloo_Results( $this->_sqloo->query( "SHOW COLUMNS FROM `".$table_name."`;" ) );
		$column_info_array = array();
		while( $row = $results->fetchRow() ) {
			$column_info_array[ $row["Field"] ] = $row;
		}
		return $column_info_array;
	}
	
	private function _checkColumn( $column_reference_array, $column_attributes, $column_info_array )
	{
		$string_log = "";
		/* ensure column exists */
		if( array_key_exists( $column_reference_array["column"], $column_info_array ) == FALSE ) {
		//if( $this->_columnExists( $column_reference_array ) == FALSE ) {
			$string_log .= "creating column: ".$column_reference_array["table"].".".$column_reference_array["column"]."<br>\n";
			$this->_addColumn( $column_reference_array, $column_attributes );
			$column_info_array = $this->_getColumnInfo( $column_reference_array["table"] );
		}
		$this->_alterColumn( $column_reference_array, $column_attributes, $column_info_array );
		return $string_log;
	}
	
	private function _columnExists( $column_reference_array )
	{
		$results = new Sqloo_Results( $this->_sqloo->query( "SHOW COLUMNS FROM `".$column_reference_array["table"]."` WHERE `Field` = '".$column_reference_array["column"]."';" ) );
		return $results->countRows() > 0;
		
	}
	
	private function _addColumn( $column_reference_array, $column_attributes )
	{	
		//create row as close to spec as possible
		//we can't add everything yet
		
		$full_type_string = $this->_buildFullTypeString( 
			array_key_exists( Sqloo_Table::data_type, $column_attributes ) ? $column_attributes[Sqloo_Table::data_type] : NULL,
			array_key_exists( Sqloo_Table::allow_null, $column_attributes ) ? $column_attributes[Sqloo_Table::allow_null] : NULL,
			array_key_exists( Sqloo_Table::default_value, $column_attributes ) ? $column_attributes[Sqloo_Table::default_value] : NULL,
			NULL //auto_increment is added later
		);
		
		$query_string = "ALTER TABLE `".$column_reference_array["table"]."`\n";
		$query_string .= "ADD COLUMN `".$column_reference_array["column"]."` ".$full_type_string.";";
		$this->_sqloo->query( $query_string );
	}
	
	private function _removeColumn( $column_reference_array )
	{
		$string_log = "removing column: ".$column_reference_array["table"].".".$column_reference_array["column"]."<br>\n";
		$query_string = "ALTER TABLE `".$column_reference_array["table"]."`\n";
		$query_string .= "DROP COLUMN `".$column_reference_array["column"]."`;";
		$this->_sqloo->query( $query_string );
		return $string_log;
	}
	
	private function _alterColumn( $column_reference_array, $target_attribute_array, $column_info_array )
	{	
		//modify primary_key
		$string_log = "";
		$target_primary_key_status = array_key_exists( Sqloo_Table::primary_key, $target_attribute_array ) ? $target_attribute_array[Sqloo_Table::primary_key] : FALSE;
		if( $target_primary_key_status != $this->_getColumnPrimaryKey( $column_reference_array, $column_info_array ) ) {
			$string_log .= "changing column primary key: ".$column_reference_array["table"].".".$column_reference_array["column"]."<br>\n";
			$this->_setColumnPrimaryKey( $column_reference_array, $target_primary_key_status );
		}
		
		//modify indexed
		$target_index_status = array_key_exists( Sqloo_Table::indexed, $target_attribute_array ) ? $target_attribute_array[Sqloo_Table::indexed] : FALSE;
		if( $target_index_status != $this->_getColumnIndexed( $column_reference_array, $column_info_array ) ) {
			$string_log .= "changing column index: ".$column_reference_array["table"].".".$column_reference_array["column"]."<br>\n";
			$this->_setColumnIndexed( $column_reference_array, $target_index_status );
		}
		
		//modify full type data
		$target_full_type_string = $this->_buildFullTypeString( 
			array_key_exists( Sqloo_Table::data_type, $target_attribute_array ) ? $target_attribute_array[Sqloo_Table::data_type] : NULL,
			array_key_exists( Sqloo_Table::allow_null, $target_attribute_array ) ? $target_attribute_array[Sqloo_Table::allow_null] : NULL,
			array_key_exists( Sqloo_Table::default_value, $target_attribute_array ) ? $target_attribute_array[Sqloo_Table::default_value] : NULL, 
			array_key_exists( Sqloo_Table::auto_increment, $target_attribute_array ) ? $target_attribute_array[Sqloo_Table::auto_increment] : NULL 
		);
		if( $target_full_type_string != $this->_getColumnTypeData( $column_reference_array, $column_info_array ) ) {
			$string_log .= $this->_setColumnTypeData( $column_reference_array, $target_full_type_string );
		}
		return $string_log;
	}
	
	private function _setColumnTypeData( $column_reference_array, $full_type_string )
	{
		$string_log = "altering column type: ".$column_reference_array["table"].".".$column_reference_array["column"]."<br>\n";
		$query_string = "ALTER TABLE `".$column_reference_array["table"]."`\n";
		$query_string .= "MODIFY COLUMN `".$column_reference_array["column"]."` ".$full_type_string.";";
		$this->_sqloo->query( $query_string );
		return $string_log;
	}
	
	private function _getColumnTypeData( $column_reference_array, $column_info_array )
	{
		$row = $column_info_array[ $column_reference_array["column"] ];
		$type_string = $row["Type"];
		$allow_null = ( $row["Null"] === "YES" );
		$default_value = $row["Default"];
		$auto_increment = (bool)preg_match( "/auto_increment/i", $row["Extra"] );
		return $this->_buildFullTypeString( $type_string, $allow_null, $default_value, $auto_increment );
	}
			
	private function _setColumnIndexed( $column_reference_array, $is_indexed )
	{
		$query = $is_indexed ? ( "CREATE INDEX `".$column_reference_array["column"]."` ON `".$column_reference_array["table"]."` ( `".$column_reference_array["column"]."` );" ) : ( "DROP INDEX `".$column_reference_array["column"]."` ON `".$column_reference_array["table"]."`;" );
		$this->_sqloo->query( $query );
	}
	
	private function _getColumnIndexed( $column_reference_array, $column_info_array )
	{
		$row = $column_info_array[ $column_reference_array["column"] ];
		return $row["Key"] == "MUL";
	}
	
	private function _setColumnPrimaryKey( $column_reference_array, $is_primary_key )
	{
		$query = "ALTER TABLE `".$column_reference_array["table"]."`\n";
		if( $is_primary_key == TRUE ) {
			$query .= "ADD PRIMARY KEY(`".$column_reference_array["column"]."`);"; 
		} else if( $this->_getColumnPrimaryKey( $column_reference_array ) === TRUE ) {
			$query .= "DROP PRIMARY KEY;";
		} else {
			trigger_error( "miss guessed primary key change", E_USER_NOTICE );
			return;
		}
		$this->_sqloo->query( $query ); 
	}
	
	private function _getColumnPrimaryKey( $column_reference_array, $column_info_array )
	{
		return ( $column_info_array[ $column_reference_array["column"] ]["Key"] === "PRI" );
	}
	
	/* foreign key functions */
	private function _checkForeignKeys()
	{
		$string_log = "";
		//build target foreign key array
		$target_foreign_key_array = array();
		foreach ( $this->_sqloo->tables as $table_name => $table ) {
			$table_class = $this->_sqloo->tables[ $table_name ];
			foreach( $table_class->parents as $parent_name => $parent_attributes ) {
				$target_foreign_key_array[ $table_name ][ $parent_name ] = array( 
					"reference_table" => $parent_attributes["table"]->name,
					"reference_column" => "id",
					"attributes" => $parent_attributes 
				);
			}
		}
		
		//build current foreign key array
		$db_name = $this->_sqloo->getMasterDatabaseName();
		$current_foreign_key_array = array();
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
		$results = new Sqloo_Results( $this->_sqloo->query( $query_string ) );
		while( $row = $results->fetchRow() ) {
			$current_foreign_key_array[ $row["table_name"] ][ $row["column_name"] ][] = array( "reference_table" => $row["referenced_table_name"] , "reference_column" => $row["referenced_column_name"], "constraint" => $row["constraint_name"] );
		}
				
		//ensure we have everything every key
		foreach( $target_foreign_key_array as $table_name => $table_key_array ) {
			foreach( $table_key_array as $column_name => $column_key_array ) {
				$need_drop = FALSE;
				$need_add = FALSE;
				if( array_key_exists( $table_name, $current_foreign_key_array ) && array_key_exists( $column_name, $current_foreign_key_array[$table_name] ) ) {
					$current_column_key_array = $current_foreign_key_array[$table_name][$column_name][0];
					unset( $current_foreign_key_array[$table_name][$column_name][0] );
					if( ( $column_key_array["reference_table"] !== $current_column_key_array["reference_table"] ) ||
						( $column_key_array["reference_column"] !== $current_column_key_array["reference_column"] )
					) {
						$need_drop = TRUE;
						$need_add = TRUE;
					}
				} else {
					$need_add = TRUE;
				}
				
				//fix foreign key
				if( $need_drop === TRUE ) {
					$string_log .= $this->_dropForeignKey( $table_name, $current_column_key_array["constraint_name"] );
				}
				if( $need_add === TRUE ) {
					$string_log .= $this->_addForeignKey( 
						$table_name, $column_name, 
						$column_key_array["reference_table"], $column_key_array["reference_column"], 
						$column_key_array["attributes"][Sqloo_Table::on_delete], $column_key_array["attributes"][Sqloo_Table::on_update]
					);
				}
			}
		}

		//clean up extra foreign keys!
		foreach( $current_foreign_key_array as $table_name => $table_key_array ) {
			foreach( $table_key_array as $column_name => $column_key_array_array ) {
				foreach( $column_key_array_array as $column_key_array ) {
					$string_log .= $this->_dropForeignKey( $table_name, $column_key_array["constraint"] );
				}
			}	
		}
		return $string_log;
	}
	
	private function _addForeignKey( $table_name, $column_name, $reference_table_name, $reference_column_name, $on_delete, $on_update )
	{
		$string_log = "adding foreign key from ".$table_name.".".$column_name." to ".$reference_table_name.".".$reference_column_name."<br>\n";
		$query_string = "ALTER TABLE `".$table_name."`\n";
		$query_string .= "ADD FOREIGN KEY ( `".$column_name."` )\n";
		$query_string .= "REFERENCES `".$reference_table_name."` ( `".$reference_column_name."` )\n";
		$query_string .= "ON DELETE ".$on_delete."\n";
		$query_string .= "ON UPDATE ".$on_update.";";
		$this->_sqloo->query( $query_string );
		return $string_log;
	}
	
	private function _dropForeignKey( $table_name, $foreign_key_name )
	{
		$string_log = "deleting foreign key from ".$table_name." called ".$foreign_key_name."<br>\n";
		$query_string = "ALTER TABLE `".$table_name."` DROP FOREIGN KEY `".$foreign_key_name."`";
		$this->_sqloo->query( $query_string );
		return $string_log;
	}
	
	private function _buildFullTypeString( $type_string, $allow_null = FALSE, $default_value = NULL, $auto_increment = FALSE )
	{
		$full_type_string = $type_string;
		$full_type_string .= $allow_null === TRUE ? " NULL" : " NOT NULL";
		$full_type_string .= $default_value !== NULL ? " DEFAULT '".$default_value."'" : "";
		$full_type_string .= $auto_increment === TRUE ? " AUTO_INCREMENT" : "";
		return $full_type_string;
	}
}


?>