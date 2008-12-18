<?php

class Sqloo_Table
{
	
	public $name;
	public $columns = array();
	public $parents = array();
	
	function __construct( $table_name ) { $this->name = $table_name; }
	
	public function column( $column_name, $data_type, $allow_null, $default_value, $indexed = FALSE )
	{
		if( array_key_exists( $column_name, $this->columns ) ) trigger_error( "Bad column name, intersects", E_USER_ERROR );
		$this->columns[ $column_name ] = array(
			Sqloo::data_type => $data_type,
			Sqloo::allow_null => $allow_null,
			Sqloo::default_value => $default_value,
			Sqloo::indexed => $indexed
		);
	}
	
	public function parent( $parent_name, $table_class, $allow_null = FALSE, $on_delete = self::cascade, $on_update = self::cascade )
	{
		if( array_key_exists( $parent_name, $this->parents ) ) trigger_error( "Bad parent name, intersects", E_USER_ERROR );
		$this->parents[ $parent_name ] = array(
			Sqloo::table_class => $table_class, 
			Sqloo::allow_null => $allow_null, 
			Sqloo::on_delete => $on_delete, 
			Sqloo::on_update => $on_update
		);
	}
	
}

?>