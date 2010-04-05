<?php

$house = $sqloo->newTable( "house" );
$house->column = array(
	"address" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 128
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => FALSE
	)
);
$house->parent = array(
	"owner" => array(
		Sqloo_Schema::PARENT_TABLE_NAME => "person", 
		Sqloo_Schema::PARENT_ALLOW_NULL => TRUE,
		Sqloo_Schema::PARENT_DEFAULT_VALUE => NULL,
		Sqloo_Schema::PARENT_ON_DELETE => Sqloo_Schema::ACTION_SET_NULL, 
	)
);
$house->index = array(
	array(
		Sqloo_Schema::INDEX_COLUMN_ARRAY => array( "address" ), 
		Sqloo_Schema::INDEX_UNIQUE => TRUE
	)
);

?>