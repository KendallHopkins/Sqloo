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

class Sqloo_Datatypes_Mysql implements Sqloo_Datatypes
{
	
	//http://dev.mysql.com/doc/refman/5.0/en/storage-requirements.html
	
	static function getTypeString( $attributes_array )
	{
		switch( $attributes_array["type"] ) {
		case Sqloo::DATATYPE_BOOLEAN: return "tinyint(1)";
		case Sqloo::DATATYPE_INTEGER: 
			return 	( ( ! array_key_exists( "size", $attributes_array ) ) ? "int(11)" :
					( $attributes_array["size"] <= 2 ? "smallint(6)" : 
					( $attributes_array["size"] <= 4 ? "int(11)" : 
			       	( $attributes_array["size"] <= 8 ? "bigint(20)" : 
			       	("numeric(".(int)( floor( log( $attributes_array["size"], 10 ) ) + 1 ).",0)" ) ) ) ) ); //fix me
		case Sqloo::DATATYPE_FLOAT: 
			if( ! array_key_exists( "size", $attributes_array ) ) return "double";
			else if( $size <= 6 ) return "double";
			else if( $size <= 15 ) return "double";
			else throw new Sqloo_Exception( "Size for a float is to large: ".$size, Sqloo_Exception::BAD_INPUT );
		case Sqloo::DATATYPE_STRING: 
			if( ! array_key_exists( "size", $attributes_array ) ) return "text";
			else if( $attributes_array["size"] <= ( pow( 2, 8 ) - 1 ) ) return "varchar(".(int)$attributes_array["size"].")";
			else if( $attributes_array["size"] <= ( pow( 2, 16 ) - 2 ) ) return "text";
			else if( $attributes_array["size"] <= ( pow( 2, 24 ) - 3 ) ) return "mediumtext";
			else if( $attributes_array["size"] <= ( pow( 2, 32 ) - 4 ) ) return "longtext"; 
			else throw new Sqloo_Exception( "Text size is to big for mysql", Sqloo_Exception::BAD_INPUT );
					
		case Sqloo::DATATYPE_FILE: 
			if( ! array_key_exists( "size", $attributes_array ) ) return "blob";
			else if( $attributes_array["size"] <= ( pow( 2, 8 ) - 1 ) ) return "tinyblob";
			else if( $attributes_array["size"] <= ( pow( 2, 16 ) - 2 ) ) return "blob"; 
			else if( $attributes_array["size"] <= ( pow( 2, 24 ) - 3 ) ) return "mediumblob";
			else if( $attributes_array["size"] <= ( pow( 2, 32 ) - 4 ) ) return "longblob";
			else throw new Sqloo_Exception( "File size is to big for mysql", Sqloo_Exception::BAD_INPUT );
		case Sqloo::DATATYPE_TIME:
			return "timestamp";
		case Sqloo::DATATYPE_OVERRIDE:
			return $attributes_array["override"];
		default:
			throw new Sqloo_Exception( "Bad types: ".$attributes_array["type"], Sqloo_Exception::BAD_INPUT );
		}
		return NULL;
	}
	
	static function getFunction( $function, $content )
	{
		switch( $function ) {
		case Sqloo_Datatypes::TO_UNIX_TIME: return "UNIX_TIMESTAMP( {$content} )";
		case Sqloo_Datatypes::FROM_UNIX_TIME: return "FROM_UNIXTIME( {$content} )";
		default: throw new Sqloo_Exception( "Bad function: ".$function, Sqloo_Exception::BAD_INPUT );
		}
	}
	
}

?>