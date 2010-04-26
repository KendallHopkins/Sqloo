<?php
	
/* SETUP */
	$require_start = microtime( TRUE );
	require_once( "./setup.php" );
	$require_stop = microtime( TRUE );

/* QUERY EXAMPLES */
	$sqloo_connection->beginTransaction();

	$item_array = array(
		array( "name" => "LightSaver" ),
		array( "name" => "MacBookPro" ),
		array( "name" => "MacBook" ),
		array( "name" => "iPod" ),
		array( "name" => "LightSaber" )
	);
	foreach( $item_array as &$insert_data ) {
		 $inserted_id = $sqloo_connection->insert( "item", $insert_data );
		 $insert_data["id"] = $inserted_id;
	}
	
	$query_start = microtime( TRUE );
	$query_generation_start = microtime( TRUE );
	$query = $sqloo_connection->newQuery();
	$item_table_ref = $query->table( "item" );
	$sub_string_expression = "SUBSTRING( $item_table_ref->name, 1, 3 )";
	$search_string = "%Mac%";
	$query
		->column( "name", $sub_string_expression )
		->column( "count", "COUNT(*)" )
		->where( $item_table_ref->name." LIKE ".$query->parameter( $search_string ) )
		->order( $sub_string_expression, Sqloo_Query::ORDER_ASCENDING )
		->group( $sub_string_expression )
		->having( "COUNT(*) > 1" )
		->limit( 1, 0 )
		->offset( 0 )
		->distinct( FALSE )
		->lock( Sqloo_Query::SELECT_LOCK_NONE );
		;
	$query_generation_stop = microtime( TRUE );
	
	$query_run_start = microtime( TRUE );
	$query->run();
	$query_run_stop = microtime( TRUE );
	
	$query_read_start = microtime( TRUE );
	$count = count( $query->fetchArray() );
	$query_read_stop = microtime( TRUE );
	$query_stop = microtime( TRUE );
	
	print sprintf( "Require Time %f\n".
				   "Total Time %f\n".
				   "Generation Time %f %f\n".
				   "Run Time %f %f\n".
				   "Read Time %f %f\n",
				   ($require_stop-$require_start),
				   ($query_stop-$query_start),
				   ($query_generation_stop-$query_generation_start), ($query_generation_stop-$query_generation_start)/($query_stop-$query_start),
				   ($query_run_stop-$query_run_start), ($query_run_stop-$query_run_start)/($query_stop-$query_start),
				   ($query_read_stop-$query_read_start), ($query_read_stop-$query_read_start)/($query_stop-$query_start)
				  );
	
	$sqloo_connection->rollbackTransaction();

?>