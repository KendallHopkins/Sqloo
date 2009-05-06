<?php

$house_normalized = $sqloo->newTable( "house_normalized" );
$house_normalized->column = array(
	"house_address" => array(
		Sqloo::COLUMN_DATA_TYPE => array(
			"type" => Sqloo::DATATYPE_STRING,
			"size" => 128
		),
		Sqloo::COLUMN_ALLOW_NULL => FALSE
	),
	"owner_fullname" => array(
		Sqloo::COLUMN_DATA_TYPE => array(
			"type" => Sqloo::DATATYPE_STRING,
			"size" => 65
		),
		Sqloo::COLUMN_ALLOW_NULL => TRUE
	)
);

$house_normalized->index = array(
	array(
		Sqloo::INDEX_COLUMN_ARRAY => array( "house_address" ), 
		Sqloo::INDEX_UNIQUE => TRUE
	)
);

?>