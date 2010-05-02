<?php
	
/* SETUP */
	require_once( "./setup.php" );

/* QUERY EXAMPLES */

//Once Upon a Time
$sqloo_connection->beginTransaction();

//There were three little pigs
	$pig_array = array(
		1 => array( "name" => "Pig 1" ),
		2 => array( "name" => "Pig 2" ),
		3 => array( "name" => "Pig 3" )
	);
	foreach( $pig_array as &$insert_data ) {
		 $inserted_id = $sqloo_connection->insert( "person", $insert_data );
		 $insert_data["id"] = $inserted_id;
	}
	
	foreach( $pig_array as $pig_number => $pig_attributes ) {
		switch( $pig_number ) {
//The first pig built his house of straw
			case 1:
				$street_name = "House-of-straw Lane";
				break;

//The second pig built his house of sticks
			case 2:
				$street_name = "House-of-sticks Lane";
				break;

//The third pig built his house of bricks
			case 3:
				$street_name = "House-of-bricks Lane";
				break;
		}
		
//Each pig finished his house
		$house_id = $sqloo_connection->insert(
			"house",
			array( "address" => $street_name, "owner" => $pig_attributes["id"] )
		);
		
//And moved in
		$sqloo_connection->insert(
			"house-person",
			array( "person" => $pig_attributes["id"], "house" => $house_id )
		);
	}
	
//Along came a wolf
	$wolf_attributes = array( "name" => "Big bad wolf" );
	$wolf_attributes["id"] = $sqloo_connection->insert( "person", array( "name" => "Big bad wolf" ) );
	
//He looked for the house of the first pig
	$wolfs_target_house_owner = "Pig 1";
	$lookup_house_query = $sqloo_connection->newQuery();
	$house_table_ref = $lookup_house_query->table( "house" );
	$person_table_ref = $house_table_ref->joinParent( "person", "owner" );
	
	$lookup_house_query
		->where( $person_table_ref->name." = ".$lookup_house_query->parameter( $wolfs_target_house_owner ) )
		->column( "id", $house_table_ref->id )
		->column( "name", $house_table_ref->name )
		->limit( 1 );
		
	$lookup_house_query->run();
	$pig_1_house_attributes = $lookup_house_query->fetchRow();

//Wolf said to the first pig, "Little pig, little pig, let me come in."
	$sqloo_connection->beginTransaction();
	$sqloo_connection->insert(
		"house-person",
		array( "person" => $wolf_attributes["id"], "house" => $pig_1_house_attributes["id"] )
	);
//First pigs said, "Not by the hair of my chiny chin chin."
	$sqloo_connection->rollbackTransaction();
	
//So the wolf huffed and puff and blew his house in and ate up the little pig.
	$sqloo_connection->delete( "house", array( $pig_1_house_attributes["id"] ) );
	$sqloo_connection->deleteWhere( "person", "name = 'Pig 1'" );

//Then the wolf went to the second pig's house
	$wolfs_target_house_owner = "Pig 2";
	$lookup_house_query->run();
	$pig_2_house_attributes = $lookup_house_query->fetchRow();
	
//The wolf huffed and puffed his house over too
	$sqloo_connection->delete( "house", array( $pig_2_house_attributes["id"] ) );
	
//The second pig ran out the house as he could with only some wood from his house
	$wood_id = $sqloo_connection->insert(
		"item",
		array( "name" => "wood", "owner" => $pig_array[2]["id"] )
	);
	
//Then the wolf went to the third pig's house
	$wolfs_target_house_owner = "Pig 3";
	$lookup_house_query->run();
	$pig_3_house_attributes = $lookup_house_query->fetchRow();
	
//When wolf arived he found the pig gave the wood to the third pig and ran inside the house
	$sqloo_connection->update( "item", array( "owner" => $pig_array[3]["id"] ), array( $wood_id ) );
	$sqloo_connection->insert( "house-person", array( "person" => $pig_array[2]["id"], "house" => $pig_3_house_attributes["id"] ) );
	
//Wolf tried to huff and puff the third pig's house down
	$sqloo_connection->beginTransaction();
	$sqloo_connection->delete( "house", array( $pig_3_house_attributes["id"] ) );
//But he failed
	$sqloo_connection->rollbackTransaction();
	
//The wolf climbed up onto the roof of the house tring to find a way in
//But the third pig was to clever for him and quickly started the fireplace
	$sqloo_connection->beginTransaction();
	$sqloo_connection->delete( "item", array( $wood_id ) );
	$sqloo_connection->update( "house", array( "fireplace_on" => TRUE ), array( $pig_3_house_attributes["id"] ) );
	$sqloo_connection->commitTransaction();
	
//When the wolf found the opening in the chimmy he climbed in and KERSPLASH
//And that was the end of the big bad wolf
	$sqloo_connection->delete( "person", array( $wolf_attributes["id"] ) );

//The end
$sqloo_connection->rollbackTransaction();

?>