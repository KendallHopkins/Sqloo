<?php

$person = $sqloo->newTable( "person" );
$person->column = array(
	"fname" => array(
		Sqloo::COLUMN_DATA_TYPE => array(
			"type" => Sqloo::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo::COLUMN_ALLOW_NULL => FALSE
	),
	"lname" => array(
		Sqloo::COLUMN_DATA_TYPE => array(
			"type" => Sqloo::DATATYPE_STRING,
			"size" => 32
		),
		Sqloo::COLUMN_ALLOW_NULL => FALSE
	)
);
$person->index = array(
	array(
		Sqloo::INDEX_COLUMN_ARRAY => array( "fname", "lname" ), 
		Sqloo::INDEX_UNIQUE => FALSE
	),
	array(
		Sqloo::INDEX_COLUMN_ARRAY => array( "lname", "fname" ), 
		Sqloo::INDEX_UNIQUE => FALSE
	)
);

?>