<?php

$house_normalized = $sqloo_database->newTable( "house_normalized" );
$house_normalized->column = array(
	"house_address" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 128
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => FALSE
	),
	"owner_fullname" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 65
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => TRUE
	)
);

$house_normalized->index = array(
	array(
		Sqloo_Schema::INDEX_COLUMN_ARRAY => array( "house_address" ), 
		Sqloo_Schema::INDEX_UNIQUE => TRUE
	)
);

?>