<?php

/*
The MIT License

Copyright (c) 2009 Kendall Hopkins

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

	private $_result_object;
	
	/**
	*	The constructor function
	*
	*	Should not be called outside of the Sqloo framework
	*
	*	@param	resource	Query resource
	*	@access	private
	*/
	
	public function __construct( $result_object )
	{
		$this->_result_object = $result_object;
	}
	
	/**
	*	Count of rows returned in query
	*
	*	@return	int	Count of rows in query
	*/
	
	public function count()
	{
		return $this->_result_object->rowCount();
	}
	
	/**
	*	Fetches a row
	*
	*	@return	array	associtated array
	*/
	
	public function fetchRow()
	{
		return $this->_result_object->fetch( PDO::FETCH_ASSOC );
	}
	
	/**
	*	Fetches an array of rows
	*
	*	@return	array	array of associtated array
	*/
	
	public function fetchArray()
	{
		return $this->_result_object->fetchAll( PDO::FETCH_ASSOC );
	}
		
}

?>