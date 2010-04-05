<?php

$item = $sqloo->newTable( "item" );
$item->column = array(
	"name" => array(
		Sqloo_Schema::COLUMN_DATA_TYPE => array(
			"type" => Sqloo_Schema::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo_Schema::COLUMN_ALLOW_NULL => FALSE
	)
);
$item->index = array(
	array(
		Sqloo_Schema::INDEX_COLUMN_ARRAY => array( "name" ), 
		Sqloo_Schema::INDEX_UNIQUE => TRUE
	)
);

?>