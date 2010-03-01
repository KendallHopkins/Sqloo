<?php

interface Sqloo_CacheInterface
{
	public function set( $key, $data );
	public function get( $key, &$data );
	public function remove( $key );
}

?>