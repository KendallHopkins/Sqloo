<?php

/* SETUP */
	require_once( "setup.php" );

$sqloo_connection->beginTransaction();

	//SETUP THE DATA
	$people_array = array(
		array( "name" => "Kendall Hopkins" ),
		array( "name" => "Steve Jobs" ),
		array( "name" => "Bill Gates" ),
		array( "name" => "Linus Torvalds" )
	);
	foreach( $people_array as $person_attributes ) {
		$sqloo_connection->insert( "person", $person_attributes );
	}
	
	//INSERT DATA FROM ONE TABLE TO ANOTHER
	$item_query = $sqloo_connection->newQuery();
	$item_table_ref = $item_query->table( "person" );
	$item_query->column( "name", "$item_table_ref->name || ' Bobble Head'" );
	$sqloo_connection->insertQuery( "item", $item_query );
	
	//SETUP ITEM QUERY
	$item_query = $sqloo_connection->newQuery();
	$item_table_ref = $item_query->table( "item" );
	$item_query
		->column( "id", $item_table_ref->id )
		->column( "name", $item_table_ref->name )
		->column( "type", "'item'" );
	
	//SETUP PERSON QUERY	
	$person_query = $sqloo_connection->newQuery();
	$person_table_ref = $person_query->table( "person" );
	$person_query
		->column( "id", $person_table_ref->id )
		->column( "name", $person_table_ref->name )
		->column( "type", "'people'" );
		
	//SETUP UNION QUERY
	$union_query = $sqloo_connection->union( array( $item_query, $person_query ) );
	$union_table_ref = $union_query->getTable();
	$union_query
		->column( "id", $union_table_ref->id )
		->column( "name", $union_table_ref->name )
		->column( "type", $union_table_ref->type )
		->where( "$union_table_ref->name LIKE 'Steve Jobs%'" )
		->run();
	
	print $union_query;
	print_r( $union_query->fetchArray() );

$sqloo_connection->rollbackTransaction();

?>