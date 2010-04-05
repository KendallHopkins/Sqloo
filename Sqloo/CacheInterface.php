<?php

interface Sqloo_CacheInterface
{
	public function set( $key, $data ); //should set key to data, always
	public function get( $key, &$data ); //should return bool if $key was found and set $data
	public function remove( $key ); //should unset key
}

?>