<?php

/* CONFIGURE */
	//include our headers
	require( "../Sqloo.php" );
	
	//setup our database pooling function
	function master_pool()
	{
		return 1 ? array(
			"address" => "127.0.0.1",
			"username" => "ken",
			"password" => "password",
			"name" => "sqloo",
			"type" => "pgsql"
		) : array(
			"address" => "127.0.0.1",
			"username" => "root",
			"password" => "password",
			"name" => "sqloo",
			"type" => "mysql"
		);
	}
	
	//simple load table function
	function load_table( $table_name, $sqloo )
	{
		require( "db/".$table_name.".php" );
	}
	
	//simple list table function
	function list_all_tables()
	{
		return array( "house", "person", "house-person", "house_normalized" );
	}
	
	//We init Sqloo with functions to get database configuration and load tables dynamically
	$sqloo = new Sqloo( "master_pool", NULL, "load_table", "list_all_tables" );

/* CHECK SCHEMA */
	//This is ONLY required to do when the schema is updated
	//Running "checkSchema" is costly and should only be done when needed
	//print $sqloo->checkSchema();

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
		array( "address" => "456 Notownedhouse Street", "owner" => NULL )
	);
	$house_id_array = array();
	foreach( $insert_data_array as $insert_data ) {
		$house_id_array[] = $sqloo->insert( "house", $insert_data );
	}

//simple query example
	//Find fname, lname by id
	$query1 = $sqloo->newQuery();
	$person_table_ref = $query1->table( "person" );
	$query1->column["fname"] = $person_table_ref->fname;
	$query1->column["lname"] = $person_table_ref->lname;
	$query1->where[] = "$person_table_ref->id = :id_number";
	$non_escaped_value_array = array( "id_number" => $person_id );
	$result1 = $query1->execute( $non_escaped_value_array ); //run query and return results
	$result_array1 = $result1->fetchArray(); //do something with result_array1
	print_r( $result_array1 );

//join query example
	//normalize the house, and person table.
	$query2 = $sqloo->newQuery();
	$house_table_ref = $query2->table( "house" );
	$person_table_ref = $house_table_ref->joinParent( "person", "owner", Sqloo::JOIN_LEFT );
	$query2->column["house_address"] = $house_table_ref->address;
	$query2->column["owner_fullname"] = "$person_table_ref->fname || ' ' || $person_table_ref->lname";
	$sqloo->insert( "house_normalized", $query2 ); //insert query directly into database

$sqloo->rollbackTransaction(); //rollback outer transaction


?>