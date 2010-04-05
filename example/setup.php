<?php

	//setup our database pooling function
	function master_pool()
	{
		return array(
			"address" => "127.0.0.1",
			"username" => "root",
			"password" => "password",
			"name" => "sqloo",
			"type" => 0 ? "pgsql" : "mysql"
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
	
	//simple local cache
	require( "./Cache.php" );
	$cache_class = new CacheExample();
	
	//We init Sqloo with functions to get database configuration and load tables dynamically
	$sqloo = new Sqloo_Connection( "master_pool", NULL, $cache_class );

	require_once( "../Sqloo/Database.php" );

	$database = new Sqloo_Database( "load_table", "list_all_tables" );
	
/* CHECK SCHEMA */
	//This is ONLY required to do when the schema is updated
	//Running "checkSchema" is costly and should only be done when needed
	print $database->checkSchema( $sqloo );

?>