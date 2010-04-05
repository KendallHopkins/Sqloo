<?php

$person = $sqloo->newTable( "person" );
$person->column = array(
	"fname" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => FALSE
	),
	"lname" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => FALSE
	)
);
$person->index = array(
	array(
		Sqloo_Schema::INDEX_COLUMN_ARRAY => array( "fname", "lname" ), 
		Sqloo_Schema::INDEX_UNIQUE => FALSE
	),
	array(
		Sqloo_Schema::INDEX_COLUMN_ARRAY => array( "lname", "fname" ), 
		Sqloo_Schema::INDEX_UNIQUE => FALSE
	)
);

?>