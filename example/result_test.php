<?php
	
/* SETUP */
	require_once( "./setup.php" );

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
		->lock( Sqloo_Query::SELECT_LOCK_NONE )
		->run();
	
	print_r( $query->count() );
	print_r( $query->fetchArray() );
	
	$query->run();
	foreach( $query as $row ) {
		print_r( $row );
	}	

	$sqloo_connection->rollbackTransaction();

?>