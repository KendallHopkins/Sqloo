<?php

$house = $sqloo->newTable( "house" );
$house->column = array(
	"address" => array(
		Sqloo::COLUMN_DATA_TYPE => array(
			"type" => Sqloo::DATATYPE_STRING,
			"size" => 128
		),
		Sqloo::COLUMN_ALLOW_NULL => FALSE
	)
);
$house->parent = array(
	"owner" => array(
		Sqloo::PARENT_TABLE_NAME => "person", 
		Sqloo::PARENT_ALLOW_NULL => TRUE,
		Sqloo::PARENT_DEFAULT_VALUE => NULL,
		Sqloo::PARENT_ON_DELETE => Sqloo::ACTION_SET_NULL, 
		Sqloo::PARENT_ON_UPDATE => Sqloo::ACTION_CASCADE
	)
);
$house->index = array(
	array(
		Sqloo::INDEX_COLUMN_ARRAY => array( "address" ), 
		Sqloo::INDEX_UNIQUE => TRUE
	)
);

?>