<?php

/* CONFIGURE */
	//include our headers
	require( "../Sqloo/Connection.php" );
	
/* SETUP */
	require( "./setup.php" );

/* QUERY EXAMPLES */

//Start outer transaction

$sqloo->beginTransaction();

//Simple insert
	$person_id = $sqloo->insert(
		"person",
		array( "fname" => "Kendall", "lname" => "Hopkins" )
	);

//simple insert foreach loop
	$insert_data_array = array(
		array( "address" => "123 Awesome Street", "owner" => $person_id ),
		array( "address" => "456 Notownedhouse Street", "owner" => NULL ),
		array( "address" => "Dir%ty'\" str^()\\ng", "owner" => NULL )
	);
	foreach( $insert_data_array as &$insert_data ) {
		 $inserted_id = $sqloo->insert( "house", $insert_data );
		 $insert_data["id"] = $inserted_id;
	}

//check our data is ok
	$query0 = $sqloo->newQuery();
	$house_table_ref = $query0->table( "house" );
	$query0->column = array(
		"id" => $house_table_ref->id,
		"address" => $house_table_ref->address,
		"owner" => $house_table_ref->owner
	);
	$query0->where = array( "$house_table_ref->id = :id" );
	foreach( $insert_data_array as $insert_data ) {
		$query0->run( array( "id" => $insert_data["id"] ) );
		$row = $query0->fetchRow();
		if( count( array_diff_assoc( $insert_data, $row ) ) ) {
			print "error!";
		}
	}

//simple query example
	//Find fname, lname by id
	$query1 = $sqloo->newQuery();
	$person_table_ref = $query1->table( "person" );
	$query1->column = array(
		"fname" => $person_table_ref->fname,
		"lname" => $person_table_ref->lname
	);
	$query1->where = array( "$person_table_ref->id = :id_number" );
	$non_escaped_value_array = array( "id_number" => $person_id );
	$query1->run( $non_escaped_value_array ); //run query
	$result_array1 = $query1->fetchArray();
	print_r( $result_array1 ); //do something with result_array1

$sqloo->beginTransaction();
$sqloo->beginTransaction();

//join query example
	//normalize the house, and person table.
	$query2 = $sqloo->newQuery();
	$house_table_ref = $query2->table( "house" );
	$person_table_ref = $house_table_ref->joinParent( "person", "owner", Sqloo_Query::JOIN_LEFT );
	$query2->column = array(
		"house_address" => $house_table_ref->address,
		"owner_fullname" => "$person_table_ref->fname || ' ' || $person_table_ref->lname"
	);
	$sqloo->insertQuery( "house_normalized", $query2 ); //insert query directly into database

$sqloo->commitTransaction();
$sqloo->commitTransaction();

$sqloo->rollbackTransaction(); //rollback outer transaction


?>