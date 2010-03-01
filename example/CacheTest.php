<?php

/* CONFIGURE */
	//include our headers
	require( "../Sqloo.php" );
	
/* SETUP */
	require( "./setup.php" );
	
/* Cache Test */
	$sqloo->cacheSet( "outsidekey", "value1" );
	$sqloo->beginTransaction();
	$sqloo->cacheSet( "insidekey", "value2" );
	
	$sqloo->beginTransaction();
	$sqloo->cacheSet( "insidekey", "value3" );
	$sqloo->rollbackTransaction();

	if( ! $sqloo->cacheGet( "outsidekey", $value ) || ( $value !== "value1" )) {
		throw new Exception( "failed test 1" );
	}
	
	if( ! $sqloo->cacheGet( "insidekey", $value ) || ( $value !== "value2" )) {
		throw new Exception( "failed test 2" );
	}
	
	if( $cache_class->get( "insidekey", $key1_value ) ) {
		throw new Exception( "failed test 3" );
	}
	
	$sqloo->beginTransaction();
	$sqloo->cacheSet( "insidekey", "value4" );
	$sqloo->commitTransaction();
	
	if( ! $sqloo->cacheGet( "insidekey", $value ) || ( $value !== "value4" )) {
		throw new Exception( "failed test 4" );
	}
	
	$sqloo->beginTransaction();

	$sqloo->cacheRemove( "outsidekey" );
	

	$sqloo->rollbackTransaction();
	

	$sqloo->commitTransaction();

	var_dump( $cache_class );


?>