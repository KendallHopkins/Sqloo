<?php

class Sqloo_Table
{
    public $name;
    public $columns;
    public $parents;
    public $children;
    public $version;
    
    //action
    const restrict = "RESTRICT";
    const cascade = "CASCADE";
    const set_null = "SET NULL";
    const no_action = "NO ACTION";
    
    //shared attributes
    const allow_null = 1;
    
    //parent attributes
    const on_delete = 2;
    const on_update = 3;
    
    //column attributes
    const data_type = 2;
    const indexed = 3;
    const default_value = 4;
    const primary_key = 5;
    const auto_increment = 6;
    
    function __construct( $table_name, $version ) 
    {
    	$this->name = $table_name;
    	$this->version = $version;
    	$this->columns = array();
    	$this->parents = array();
    }
    
    public function setTableArray( $column_and_parent_array )
    {
    	$this->columns = $column_and_parent_array["column"];
   		$this->parents = $column_and_parent_array["parent"];
    }
    
    public function addColumn( $name_string, $attribute_array )
    {
        $this->columns[ $name_string ] = $attribute_array;
    }
    
    public function setColumnArray( $column_array )
    {
    	$this->columns = $column_array;
    }
    
    public function addParent( $parent_name, $table, $allow_null = FALSE, $on_delete = self::cascade, $on_update = self::cascade )
    {
    	if( array_key_exists( $parent_name, $this->parents ) ) { trigger_error( "Bad parent name, intersects", E_USER_ERROR ); }
        $this->parents[ $parent_name ] = array( "table" => $table, Sqloo_Table::allow_null => $allow_null, Sqloo_Table::on_delete => $on_delete, Sqloo_Table::on_update => $on_update );
    }
    
    public function setParentArray( $parrent_array )
    {
    	 $this->parents = $parrent_array;
    }
    
    public function addParentArray( $parrent_array )
    {
    	 $this->parents = array_merge( $this->parents, $parrent_array );
    }
    
    
}

?>