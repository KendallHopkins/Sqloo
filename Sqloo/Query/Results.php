<?php

/*
The MIT License

Copyright (c) 2008 Kendall Hopkins

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

class Sqloo_Query_Results
{

	private $_resource;
	private $_buffered;
	
	/**
	*	The constructor function
	*
	*	Should not be called outside of the Sqloo framework
	*
	*	@param	resource	Query resource
	*	@param	resource	If query is buffered or not
	*/
	
	public function __construct( $resource, $buffered = TRUE )
	{
		$this->_resource = $resource;
		$this->_buffered = $buffered;
	}
	
	/**
	*	Destruct function
	*
	*	Releases memory from query
	*
	*	@ignore
	*/
	
	public function __destruct()
	{
		mysql_free_result( $this->_resource );
	}
	
	/**
	*	Count of rows returned in query
	*
	*	Only works if query is buffered
	*
	*	@return	int	Count of rows in query
	*/
	
	public function count()
	{
		if( $this->_buffered !== TRUE ) trigger_error( "query is unbuffered, doesn't allow counting", E_USER_ERROR );
		return mysql_num_rows( $this->_resource );
	}
	
	/**
	*	Fetches a row
	*
	*	@return	array	associtated array
	*/
	
	public function fetchRow()
	{
		return mysql_fetch_assoc( $this->_resource );
	}
	
	/**
	*	Fetches an array of rows
	*
	*	@return	array	array of associtated array
	*/
	
	public function fetchArray()
	{
		$row_array = array();
		while( $row = mysql_fetch_assoc( $this->_resource ) ) $row_array[] = $row;
		return $row_array;
	}
		
}

?>