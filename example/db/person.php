<?php

$person = $sqloo_database->newTable( "person" );
$person->column = array(
	"name" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => FALSE
	)
);
$person->index = array(
	array(
		Sqloo_Schema::INDEX_COLUMN_ARRAY => array( "name" ), 
		Sqloo_Schema::INDEX_UNIQUE => TRUE
	)
);

?>