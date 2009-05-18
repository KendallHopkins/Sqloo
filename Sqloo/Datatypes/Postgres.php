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

/** @access private */

class Sqloo_Datatypes_Postgres implements Sqloo_Datatypes
{
	
	//http://www.postgresql.org/docs/8.3/interactive/datatype.html
	
	static function getTypeString( $attributes_array )
	{
		switch( $attributes_array["type"] ) {
		case Sqloo::DATATYPE_BOOLEAN:
			return "boolean";
		case Sqloo::DATATYPE_INTEGER: 
			return 	( ( ! array_key_exists( "size", $attributes_array ) ) ? "integer" :
					( $attributes_array["size"] <= 2 ? "smallint" : 
					( $attributes_array["size"] <= 4 ? "integer" : 
			       	( $attributes_array["size"] <= 8 ? "bigint" : 
			       	( "numeric(".(int)( floor( log( $attributes_array["size"], 10 ) ) + 1 ).",0)") ) ) ) ); //fix this line
		case Sqloo::DATATYPE_FLOAT: 
			return 	( $attributes_array["size"] <= 6 ? "real" : 
					( $attributes_array["size"] <= 15 ? "double precision" : 
					( trigger_error( "Size for a float is to large: ".$attributes_array["size"], E_USER_ERROR ) ) ) );
		case Sqloo::DATATYPE_STRING: 
			return	( ( ! array_key_exists( "size", $attributes_array ) ) ? "text" : 
					( "character varying(".(int)$attributes_array["size"].")" ) );
		case Sqloo::DATATYPE_FILE: 
			return "Oid";
		case Sqloo::DATATYPE_TIME:
			return "timestamp";
		case Sqloo::DATATYPE_OVERRIDE:
			return $attributes_array["override"];
		default: 
			trigger_error( "Bad types: ".$attributes_array["type"], E_USER_ERROR );
		}
	}
	
}

?>