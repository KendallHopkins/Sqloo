<?php

//require( "Sqloo/Schema.php" ); dynamically loaded
//require( "Sqloo/Query.php" ); dynamically loaded
require( "Sqloo/Pool.php" );
require( "Sqloo/Results.php" );
require( "Sqloo/Table.php" );

class Sqloo
{    
    public $tables = array();
	private $_sqloo_pool;
	private $_in_Transaction = 0;
	private $_enabled_transaction;
	
	function __construct( $enabled_transaction = TRUE, $schema_file_path = "/db-schema.php", $db_configure_file_path = "/configure/db.php" ) 
	{
		$sqloo = $this;
	    require( $_SERVER['DOCUMENT_ROOT'].$schema_file_path );
		require( $_SERVER['DOCUMENT_ROOT'].$db_configure_file_path );
		$this->_enabled_transaction = $enabled_transaction;
        $this->_sqloo_pool = new Sqloo_Pool( $this->_enabled_transaction, $master_pool, $slave_pool );
	}

	public function query( $query_string, $on_slave = FALSE )
	{
		$db = ( ( $on_slave == FALSE ) || ( $this->_in_Transaction > 0 ) ) ? $this->_sqloo_pool->getMasterResource() : $this->_sqloo_pool->getSlaveResource();
        $resource = mysql_query( $query_string, $db );
        if ( ! $resource ) { trigger_error( mysql_error( $db )." <b>".$query_string."</b>", E_USER_ERROR ); }
		return $resource;
	}
	
	private function versionChange( $table_name, $row_id, $type, $data_array )
	{
		$this->insert( "vo_versioning", array( "table" => $table_name, "row" => $row_id, "type" => $type, "data" => serialize( $data_array ) ), TRUE );
	}
	
	/*
	public function versionHistory( $table_name, $row_id )
	{
	    if( $this->tables[$table_name]->version === FALSE ) {
	    	trigger_error( "table does not support versioning", E_USER_ERROR );
	    }
		$query = $this->newQuery();
		$query->setTable( "vo_versioning" );
		$query->addColumn( "vo_versioning", "id", "version_id" );
		$query->addColumn( "vo_versioning", "type", "type" );
		$query->addColumn( "vo_versioning", "data", "data" );
		$query->addColumn( "vo_versioning", "added", "added" );
		$query->setOrder( "vo_versioning", "id", "asc" );
		$query->setWhereRaw( "vo_versioning.table = '".$table_name."' && vo_versioning.row = ".$row_id );
		$results = $query->run();
		$results_array = array();
		while( $row = $results->fetchRow() ) {
			$unserialized_data = unserialize( $row["data"] );
			$results_array[] = array( "version_id" => $row["version_id"], "data" => $unserialized_data, "added" => $row["added"], "type" => $row["type"] );
		}
		return $results_array;
	}
	
	public function versionRollback( $table_name, $row_id, $version_id )
	{
		if( $this->tables[$table_name]->version === FALSE ) {
	    	trigger_error( "table does not support versioning", E_USER_ERROR );
	    	return FALSE;
	    }
		$version_history = $this->versionHistory( $table_name, $row_id );
		$changes_array = array();
		$all_data = FALSE;
		foreach( $version_history as $version )
		{
			switch( $version["type"] ) {
			case "insert":
				$last_row_has_data = TRUE;
				break;
			case "update":
				$last_row_has_data = TRUE;
				break;
			case "delete":
				$last_row_has_data = FALSE;
				break;
			}
			
			if( $all_data == FALSE ) {
				if( $last_row_has_data == TRUE ) {
					foreach( $version["data"] as $key => $value ) {
						$changes_array[$key] = $value;
					}
				}
				if( $last_row_has_data != TRUE ) {
					$changes_array = NULL;
				}
			}
			
			//if that was the target version id, say we have all the data.
			if( $version["version_id"] == $version_id ){
				$all_data = TRUE;
			}
		}
		if( $all_data == FALSE ) {
			trigger_error( "bad version_id", E_USER_ERROR );
			return FALSE;
		}
		if( $changes_array == NULL ) {
			$this->delete( $table_name, array( $row_id ) );
		} else if( $last_row_has_data != TRUE) {
			$old_row_id = $row_id;
			$row_id = $this->insert( $table_name, $changes_array );
			$this->update( "vo_versioning", array( "row" => $row_id ), "vo_versioning.row = ".$old_row_id );
		} else {
			$this->update( $table_name, $changes_array, array( $row_id ) );
		}
		return $row_id;
	}
	*/
	
	public function startTransaction()
	{
        if ( ! $this->_enabled_transaction ) { trigger_error( "enable transactions to use startTransaction function", E_USER_NOTICE ); }
		$this->_in_Transaction++;
		$this->query( "BEGIN", FALSE );
	}
	
	public function rollbackTransaction()
	{
        if ( ! $this->_enabled_transaction ) { trigger_error( "enable transactions to use rollbackTransaction function", E_USER_NOTICE ); }
		$this->query( "ROLLBACK", FALSE );
		$this->_in_Transaction--;
	}
	
	public function commitTransaction()
	{
        if ( ! $this->_enabled_transaction ) { trigger_error( "enable transactions to use commitTransaction function", E_USER_NOTICE ); }
		$this->query( "COMMIT", FALSE );
		$this->_in_Transaction--;
	}
	
	public function newQuery()
	{
		require_once( "Sqloo/Query.php" );
		return new Sqloo_Query( $this );
	}
	
	public function insert( $table_name, $insert_array, $low_priority = FALSE )
	{
		if( $this->tables[$table_name]->version === TRUE ) {
			$version_array = $insert_array;
		}
		
		//check if we have a "magic" added field
		if( array_key_exists( "added", $this->tables[$table_name]->columns ) ) {
			$insert_array["added"] = "CURRENT_TIMESTAMP";
		}
		
		//check if we have a "magic" modifed field
		if( array_key_exists( "modified", $this->tables[$table_name]->columns ) ) {
			$insert_array["modified"] = "CURRENT_TIMESTAMP";
		}
		
		$insert_string = "INSERT ";
		if( $low_priority == TRUE ) {
			$insert_string .= "LOW_PRIORITY ";
		}
		$insert_string .= "INTO `".$table_name."`\n";
		
		//lets build both at the same time to avoid going iterating through it twice
		$key_string = "( "; 
		$value_string = "VALUES( ";
		foreach ( $insert_array as $key => $value ) {
			$key_string .= "`".$table_name."`.".$key.",";
			$value_string .= $this->processVariable( $value );
			$value_string .= ",";
		}
		$finished_key_string = substr($key_string, 0, -1)." )\n";
		$finished_value_string = substr($value_string, 0, -1)." )";
		$insert_string .= $finished_key_string.$finished_value_string;
        $this->query( $insert_string, FALSE );
        $row_id = mysql_insert_id( $this->_sqloo_pool->getMasterResource() );
        if( $this->tables[$table_name]->version === TRUE ) {
        	$this->versionChange( $table_name, $row_id, "insert", $version_array );
        }
        return $row_id;
	}
	
	public function update( $table_name, $update_array, $id_array_or_where_string, $limit = NULL )
	{
		if( $this->tables[$table_name]->version ) {
			$version_array = $update_array;
		}
		
		//check if we have a "magic" modifed field
		if( array_key_exists( "modified", $this->tables[$table_name]->columns ) ) {
			$update_array["modified"] = "CURRENT_TIMESTAMP";
		}
		
		/* make id_array */
		if( is_string( $id_array_or_where_string ) ) {
			$query = $this->newQuery();
			$query->setTable( $table_name );
			$query->addColumn( $table_name, "id", "id" );
			$query->setWhereRaw( $id_array_or_where_string );
			$results = $query->run();
			$id_array = array();
			while ( $row = $results->fetchRow() ) {
				$id_array[] = $row["id"];
			}
		} else if( is_array( $id_array_or_where_string ) ) {
			$id_array = $id_array_or_where_string;
		} else {
			trigger_error( "bad input", E_USER_ERROR );
			return;
		}
		
		$where_string = "";
		$array_count = count( $id_array );
		if ( $array_count <= 0 ) {
			//nothing affected....
			return;
		}
		
		/* create update string */
		$update_string = "UPDATE `".$table_name."`\n"."SET ";
		foreach ( $update_array as $key => $value ) {
			$update_string .= $key."=";
			$update_string .= $this->processVariable( $value );			
			$update_string .= ",\n";
		}
		$update_string = substr($update_string, 0, -2)." \n";
		$update_string .= "WHERE ".$where_string."\n";
		$update_string .= "id IN ".self::arrayToIn( $id_array )."\n";				
		$update_string .= "LIMIT ".$array_count."\n";
        $this->query( $update_string, FALSE );
        
        if( $this->tables[$table_name]->version === TRUE ) {
        	foreach( $id_array as $id ) {
               	$this->versionChange( $table_name, $id, "update", $version_array );
        	}
        }
	}
	
	public function delete( $table_name, $id_array_or_where_string )
	{
		/* make id_array */
		if( is_string( $id_array_or_where_string ) ) {
			$query = $this->newQuery();
			$query->setTable( $table_name );
			$query->addColumn( $table_name, "id", "id" );
			$query->setWhereRaw( $id_array_or_where_string );
			$results = $query->run();
			$id_array = array();
			while( $row = $results->fetchRow() ) {
				$id_array[] = $row["id"];
			}
		} else if( is_array( $id_array_or_where_string ) ) {
			$id_array = $id_array_or_where_string;
		} else {
			trigger_error( "bad input", E_USER_ERROR );
			return;
		}
        if( $this->tables[$table_name]->version === TRUE ) {
			$children_array = array();
			foreach( $this->tables as $table ) {
				foreach( $table->parents as $parent_name => $parent ) {
					if( ( $parent["table"] === $this->tables[$table_name] ) && ( $parent["table"]->version === TRUE ) ) {
						$query = $this->newQuery();
						$query->setTable( $table->name );
						$query->addColumn( $table->name, "id", "id" );
						$query->setWhereRaw( $table->name.".".$parent_name." IN ".self::arrayToIn( $id_array )."\n" );
						$results = $query->run();
						$delete_id_array = array();
						while( $row = $results->fetchRow() ) {
							$delete_id_array[] = $row["id"];
						}
						if( count( $delete_id_array ) > 0 ) {
							$this->delete( $table->name, $delete_id_array );
						}
						$children_array[] = array( "table" => $table->name, "id_array" => $delete_id_array );
					}
				}
			}
		}
		
		$array_count = count( $id_array );
		if ( $array_count <= 0 ) {
			trigger_error( "no id's passed to delete", E_USER_NOTICE );
			return;
		}
		$delete_string = "DELETE FROM `".$table_name."`\n";
		$delete_string .= "WHERE id IN ".self::arrayToIn( $id_array )."\n";
		$delete_string .= "LIMIT ".$array_count.";";
		$this->query( $delete_string, FALSE );
		
		if( $this->tables[$table_name]->version === TRUE ) {
        	foreach( $id_array as $id ) {
               	$this->versionChange( $table_name, $id, "delete", $children_array );
        	}
        }
	}
	
	public function union( $array_of_queries, $union_name = "union_name" )
	{
	    if( count( $array_of_queries ) < 2 ) {
	    	return;
	    }
	    
	    $union_string = "( ";
		foreach( $array_of_queries as $query ) {
			$union_string .= "( ";
			$union_string .= $query->getQueryString();
			$union_string .= " )\nUNION\n";
		}
		$union_string = substr( $union_string , 0, -6 )." ) ".$union_name; //strip off last "UNION"
		$query = $this->newQuery();
		$query->setFromRaw( $union_string );
		$query->addColumnRaw("*");
		return $query;
	}
	
	public function newTable( $name, $version = FALSE )
	{
		$this->tables[ $name ] = new Sqloo_Table( $name, $version );
		return $this->tables[ $name ];
	}
	
	public function newRelationshipTable( $table1, $table2 )
	{
		$many_to_many_table = $this->newTable( self::computeJoinTableName( $table1->name, $table2->name ) );
		$many_to_many_table->setParentArray(
			array(
				$table1->name => array(
					"table" => $table1, 
					Sqloo_Table::allow_null => FALSE, 
					Sqloo_Table::on_delete => Sqloo_Table::cascade, 
					Sqloo_Table::on_update => Sqloo_Table::cascade
				),
				$table2->name => array(
					"table" => $table2, 
					Sqloo_Table::allow_null => FALSE, 
					Sqloo_Table::on_delete => Sqloo_Table::cascade, 
					Sqloo_Table::on_update => Sqloo_Table::cascade
				),
			)
		);
		return $many_to_many_table;
	}
	
	public function nextId( $tableName )
	{
		$query = "SHOW TABLE STATUS WHERE name = '".$tableName."'";
		$resource = $this->query( $query );
		$array = @mysql_fetch_assoc( $resource );
		if ( $array == FALSE ) {
			return FALSE;
		} else {
			return $array[ "Auto_increment" ];
		}
	}
	
	//schema functions
	public function checkSchema()
	{
		require_once( "Sqloo/Schema.php" );
		$schema = new Sqloo_Schema( $this );
		return $schema->checkSchema();
	}
	
	static public function processVariable( $value )
	{
		if( is_bool($value) ) {
			return "'".(int)$value."'";
		} else if( is_null($value) ) {
			return "NULL";
		} else if( is_int($value) || is_float($value) ) {
			return $value;
		} else if( $value == "CURRENT_TIMESTAMP" ) {
			return "CURRENT_TIMESTAMP";
		} else if( is_string( $value ) ) {
			return "'".mysql_escape_string( $value )."'";
		} else if( get_class( $value ) == "Sqloo_Query" ) {
			return "(".$value->getQueryString().")";
		} else {
			trigger_error( "bad imput: ".var_export( $value, TRUE ), E_USER_ERROR );
		}
	}
	
	static public function computeJoinTableName( $first_Table, $second_table )
	{
		if( $first_Table < $second_table ) {
			$join_table = $first_Table."-".$second_table;
		} else {
			$join_table = $second_table."-".$first_Table;
		}
		return $join_table;
	}
	
	static public function arrayToIn( $id_array )
	{
		$in_string = "(";
		foreach( $id_array as $id ) {
			$in_string .= self::processVariable( $id ).",";
		}
		return substr( $in_string, 0, -1 ).")";
	}
	
	public function getMasterDatabaseName()
	{
		return $this->_sqloo_pool->getMasterDatabaseName();
	}

    function __destruct()
	{
		if ( $this->_in_Transaction > 0 ) {
			for( $i = 0; $i < $this->_in_Transaction; $i++ ) {
				$this->rollbackTransaction();
			}
			trigger_error( "Transaction was not close and was rolled back", E_USER_ERROR );
        }
    }
	
	function __clone() { trigger_error( "Clone is not allowed.", E_USER_ERROR ); }
}

?>