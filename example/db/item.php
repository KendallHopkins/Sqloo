<?php

$item = $sqloo->newTable( "item" );
$item->column = array(
	"name" => array(
		Sqloo::COLUMN_DATA_TYPE => array(
			"type" => Sqloo::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo::COLUMN_ALLOW_NULL => FALSE
	)
);
$item->index = array(
	array(
		Sqloo::INDEX_COLUMN_ARRAY => array( "name" ), 
		Sqloo::INDEX_UNIQUE => TRUE
	)
);

?>