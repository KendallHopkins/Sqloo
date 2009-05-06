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

class Sqloo_Schema_Postgres extends Sqloo_Schema
{
	
	/* Correction function */
	
	protected function _executeAlterQuery()
	{
		$query_array = array();
				
		//remove foreign keys
		foreach( $this->_alter_table_data as $table_name => $table_array ) {
			if( array_key_exists( "fk", $table_array ) ) {
				foreach( $table_array["fk"] as $column_name => $foreign_key_array ) {
					foreach( $foreign_key_array as $foreign_key ) {
						if( $foreign_key["action"] === "drop" ) {
							$query_array[] = "ALTER TABLE \"".$table_name."\" DROP CONSTRAINT \"".$foreign_key["info"]."\" CASCADE";
						}
					}
				}
			}
		}		
		
		//change columns and indexes
		foreach( $this->_alter_table_data as $table_name => $table_array ) {
			//remove indexes
			if( array_key_exists( "index", $table_array ) ) {
				$drop_index_array = array();
				foreach( $table_array["index"] as $index_name => $index_array ) {
					if( $index_array["action"] === "add" ) {
						//we do this latter
					} else if( $index_array["action"] === "drop" ) { //drop table
						$drop_index_array[] = $index_array["info"];
					} else {
						trigger_error( "Unknown action on column: ".$table_array["action"], E_USER_ERROR );	
					}
				}
				if( count( $drop_index_array ) > 0 ) {
					$query_array[] = "DROP INDEX \"".implode( "\",\"", $drop_index_array )."\" CASCADE";
				}
			}
			
			//change columns
			if( ! array_key_exists( "action", $table_array ) ) { //alter table
				foreach( $table_array["column"] as $column_name => $column_array ) {
					//print_r( $column_array );					
				}
			} else if( $table_array["action"] === "add" ) { //create table
				$query_string = "CREATE TABLE \"".$table_name."\" (\n";
				foreach( $table_array["column"] as $column_name => $column_array ) {
					$query_string .= $column_name." ".$this->_sqloo->getTypeString( $column_array["info"][Sqloo::COLUMN_DATA_TYPE] );
					if( $column_array["info"][Sqloo::COLUMN_AUTO_INCREMENT] ) {
						$sequence_name = $table_name."_".$column_name."_seq";
						$query_array[] = "CREATE SEQUENCE \"".$sequence_name."\"";
						$query_string .= " DEFAULT nextval('".$sequence_name."')";	
					} else {
						if( $column_array["info"][Sqloo::COLUMN_DEFAULT_VALUE] !== NULL )
							$query_string .= " DEFAULT ".$column_array["info"][Sqloo::COLUMN_DEFAULT_VALUE];					
					}
					$query_string .= ( $column_array["info"][Sqloo::COLUMN_ALLOW_NULL] ) ? " NULL" : " NOT NULL";
					if( $column_array["info"][Sqloo::COLUMN_PRIMARY_KEY] )
						$query_string .= " PRIMARY KEY";
					$query_string .= ",\n";
				}
				$query_array[] = substr( $query_string, 0, -2 )."\n)";
			} else if( $table_array["action"] === "drop" ) { //drop table
				$query_array[] = "DROP TABLE \"".$table_name."\"";
			} else {
				trigger_error( "Unknown action on table: ".$table_array["action"], E_USER_ERROR );	
			}
			
			//add indexes
			if( array_key_exists( "index", $table_array ) ) {
				foreach( $table_array["index"] as $index_name => $index_array ) {
					if( $index_array["action"] === "add" ) {
						$query_string = "CREATE ";
						if( $index_array["info"][Sqloo::INDEX_UNIQUE] )
							$query_string .= "UNIQUE ";
						$query_string .= "INDEX \"".$index_name."\" ON \"".$table_name."\" ( \"".implode( "\",\"", $index_array["info"][Sqloo::INDEX_COLUMN_ARRAY] )."\" )";
						$query_array[] = $query_string;
					} else if( $index_array["action"] === "drop" ) {
						//we already did this
					} else {
						trigger_error( "Unknown action on column: ".$table_array["action"], E_USER_ERROR );	
					}
				}
			}
		}
		
		//add foreign keys
		foreach( $this->_alter_table_data as $table_name => $table_array ) {
			if( array_key_exists( "fk", $table_array ) ) {
				foreach( $table_array["fk"] as $column_name => $foreign_key_array ) {
					foreach( $foreign_key_array as $foreign_key_name => $foreign_key ) {
						if( $foreign_key["action"] === "add" ) {
							$query_array[] = 
								"ALTER TABLE \"".$table_name."\"\n".
								"ADD CONSTRAINT \"".$foreign_key_name."\"\n".
								"FOREIGN KEY (\"".$column_name."\")\n".
								"REFERENCES \"".$foreign_key["info"]["target_table_name"]."\" (\"".$foreign_key["info"]["target_column_name"]."\") MATCH FULL\n".
								"ON DELETE ".$foreign_key["info"]["parent_on_delete"]."\n".
								"ON UPDATE ".$foreign_key["info"]["parent_on_update"];
						}
					}
				}
			}
		}				
		foreach( $query_array as $query_string ) {
			$this->_sqloo->query( $query_string );
		}
	}
	
	/* Data Fetching functions */
	
	protected function _getTableArray()
	{
		$table_array = array();
		$query_string = 
			"SELECT table_name\n".
			"FROM information_schema.tables\n".
			"WHERE table_type = 'BASE TABLE' AND table_schema NOT IN ('pg_catalog', 'information_schema')";
		$query_object = $this->_sqloo->query( $query_string );
		while( $row = $query_object->fetch( PDO::FETCH_ASSOC ) ) {
			$table_array[] = end($row);			
		}
		return $table_array;
	}
	
	protected function _getColumnDataArray( $table_array )
	{
		$column_data_array = array();
		foreach( $table_array as $table_name ) {
			$column_data = array();
			$query_string = 
				"SELECT ordinal_position, column_name, data_type, column_default, is_nullable, character_maximum_length, numeric_precision, numeric_scale\n".
				"FROM information_schema.columns\n".
				"WHERE table_name = '".$table_name."'\n".
				"ORDER BY ordinal_position";
			$query_resource = $this->_sqloo->query( $query_string );
			while( $row = $query_resource->fetch( PDO::FETCH_ASSOC ) ) {
				$column_data[ $row["column_name"] ] = array(
					Sqloo::COLUMN_DATA_TYPE => $row["data_type"],
					Sqloo::COLUMN_ALLOW_NULL => ( $row["is_nullable"] === "YES" ),
					Sqloo::COLUMN_DEFAULT_VALUE => $row["column_default"],
					Sqloo::COLUMN_PRIMARY_KEY => FALSE, //we'll change that later
					Sqloo::COLUMN_AUTO_INCREMENT => FALSE //again, we'll set it later
				);
				if( preg_match( "/nextval/i", $row["column_default"] ) ) {
					$column_data[$row["column_name"]][Sqloo::COLUMN_AUTO_INCREMENT] = TRUE;
					$column_data[$row["column_name"]][Sqloo::COLUMN_DEFAULT_VALUE] = NULL;
				}
			}
			
			$query_string_2 = 
				"SELECT index.indkey AS indkey_string\n".
				"FROM pg_index as index\n".
				"JOIN pg_class as table_class ON table_class.oid = index.indrelid\n".
				"JOIN pg_class as index_class ON index_class.oid = index.indexrelid\n".
				"WHERE table_class.relname = '".$table_name."'\n".
				"AND index.indisprimary = 't'";
			$query_resource_2 = $this->_sqloo->query( $query_string_2 );
			$row_2 = $query_resource_2->fetch( PDO::FETCH_ASSOC );
			if( $row_2 ) {
				$indkey_array = explode( " ", $row_2["indkey_string"] );
				foreach( $indkey_array as $indkey ) {
					$query_string_3 = 
						"SELECT attname\n".
						"FROM pg_attribute as column_class\n".
						"JOIN pg_class as table_class ON column_class.attrelid = table_class.oid\n".
						"WHERE table_class.relname = '".$table_name."'".
						"AND column_class.attnum = ".((int)$indkey);
					$query_resource_3 = $this->_sqloo->query( $query_string_3 );
					$row_3 = $query_resource_3->fetch( PDO::FETCH_ASSOC );
					if( $row_3 && array_key_exists( $row_3["attname"], $column_data ) )
						$column_data[ $row_3["attname"] ][Sqloo::COLUMN_PRIMARY_KEY] = TRUE;
					else {
						trigger_error( "For some reason the key failed to resolve", E_USER_ERROR );				
					}
				}
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
			
			$query_string = 
				"SELECT index_class.relname AS index_name, index.indkey AS indkey_string, index.indisunique AS is_unique\n".
				"FROM pg_index as index\n".
				"JOIN pg_class as table_class ON table_class.oid = index.indrelid\n".
				"JOIN pg_class as index_class ON index_class.oid = index.indexrelid\n".
				"WHERE table_class.relname = '".$table_name."'\n".
				"AND index.indisprimary = 'f'";
			$query_resource = $this->_sqloo->query( $query_string );
			while( $row = $query_resource->fetch( PDO::FETCH_ASSOC ) ) {
				$key_name = $row["index_name"];
				$is_unique = $row["is_unique"];
				$indkey_array = explode( " ", $row["indkey_string"] );
				$column_array = array();
				foreach( $indkey_array as $indkey ) {
					$query_string_2 = 
						"SELECT attname\n".
						"FROM pg_attribute as column_class\n".
						"JOIN pg_class as table_class ON column_class.attrelid = table_class.oid\n".
						"WHERE table_class.relname = '".$table_name."'".
						"AND column_class.attnum = ".((int)$indkey);
					$query_resource_2 = $this->_sqloo->query( $query_string_2 );
					$row_2 = $query_resource_2->fetch( PDO::FETCH_ASSOC );
					if( $row_2 && array_key_exists( "attname", $row_2 ) )
						$column_array[] = $row_2["attname"];
					else {
						var_dump( $row_2 );
						trigger_error( "For some reason the key failed to resolve", E_USER_ERROR );				
					}
				}
				$index_data[ $key_name ][ Sqloo::INDEX_COLUMN_ARRAY ] = $column_array;
				$index_data[ $key_name ][ Sqloo::INDEX_UNIQUE ] = $is_unique;
			}
			
			$index_data_array[$table_name] = $index_data;
		}
		return $index_data_array;
	}
	
	protected function _getForeignKeyDataArray()
	{
		$lookup_array = array( "a" => Sqloo::ACTION_NO_ACTION, "r" => Sqloo::ACTION_RESTRICT, "c" => Sqloo::ACTION_CASCADE, "n" => Sqloo::ACTION_SET_NULL );
		
		$foreign_key_data_array = array();
		$query_string = 
			"SELECT c.conname AS constraint_name, c.contype AS constraint_type, c.condeferrable AS is_deferrable, c.condeferred AS is_deferred, confupdtype AS on_update, confdeltype AS on_delete, confmatchtype AS match_type, t.relname AS table_name, a.attname AS column_name, t2.relname AS referenced_table_name, a2.attname AS referenced_column_name\n".
			"FROM pg_constraint c\n".
			"LEFT JOIN pg_class t  ON c.conrelid  = t.oid\n".
			"LEFT JOIN pg_class t2 ON c.confrelid = t2.oid\n".
			"LEFT JOIN pg_attribute a ON a.attnum = ANY( c.conkey ) AND c.conrelid = a.attrelid\n".
			"LEFT JOIN pg_attribute a2 ON a2.attnum = ANY( c.confkey ) AND c.confrelid = a2.attrelid\n".
			"WHERE c.contype = 'f'\n".
			"AND c.confrelid > 0";
		$query_resource = $this->_sqloo->query( $query_string );
		while( $row = $query_resource->fetch( PDO::FETCH_ASSOC ) ) {
			$foreign_key_data_array[ $row["table_name"] ][ $row["column_name"] ][ $row["constraint_name"] ] = array( 
				"target_table_name" => $row["referenced_table_name"],
				"target_column_name" => $row["referenced_column_name"],
				Sqloo::PARENT_ON_DELETE => $lookup_array[ $row["on_delete"] ],
				Sqloo::PARENT_ON_UPDATE => $lookup_array[ $row["on_update"] ]
			);
		}
		return $foreign_key_data_array;
	}	
	
}

?>