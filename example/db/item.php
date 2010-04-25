<?php

$item = $sqloo_database->newTable( "item" );
$item->column = array(
	"name" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => FALSE
	)
);
$item->parent = array(
	"owner" => array(
		Sqloo_Schema::PARENT_TABLE_NAME => "person", 
		Sqloo_Schema::PARENT_ALLOW_NULL => TRUE,
		Sqloo_Schema::PARENT_DEFAULT_VALUE => NULL,
		Sqloo_Schema::PARENT_ON_DELETE => Sqloo_Schema::ACTION_SET_NULL, 
	)
);
$item->index = array(
	array(
		Sqloo_Schema::INDEX_COLUMN_ARRAY => array( "name" ), 
		Sqloo_Schema::INDEX_UNIQUE => TRUE
	)
);

?>