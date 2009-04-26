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
		case Sqloo::DATATYPE_INTERGER: 
			return 	( ( ! array_key_exists( "size", $attributes_array ) ) ? "int(11)" :
					( $attributes_array["size"] <= 2 ? "smallint(6)" : 
					( $attributes_array["size"] <= 4 ? "int(11)" : 
			       	( $attributes_array["size"] <= 8 ? "bigint(20)" : 
			       	("numeric(".(int)( floor( log( $attributes_array["size"], 10 ) ) + 1 ).",0)" ) ) ) ) ); //fix me
		case Sqloo::DATATYPE_FLOAT: 
			return 	( $size <= 6 ? "real" : 
					( $size <= 15 ? "double" : 
					( trigger_error( "Size for a float is to large: ".$size, E_USER_ERROR ) ) ) );
		case Sqloo::DATATYPE_STRING: 
			return 	( ( ! array_key_exists( "size", $attributes_array ) ) ? "text" : 
					( $attributes_array["size"] <= 255 ? "varchar(".(int)$attributes_array["size"].")" : 
					( $attributes_array["size"] < ( pow( 2, 16 ) - 2 ) ? "text" : 
					( $attributes_array["size"] < ( pow( 2, 24 ) - 3 ) ? "mediumtext" : 
					( $attributes_array["size"] < ( pow( 2, 32 ) - 4 ) ? "longtext" : 
					( trigger_error( "Text size is to big for mysql", E_USER_ERROR ) ) ) ) ) ) );
					
		case Sqloo::DATATYPE_FILE: 
			return 	( ( ! array_key_exists( "size", $attributes_array ) ? "blob" : 
					( $attributes_array["size"] < ( pow( 2, 8 ) - 1 ) ? "tinyblob" : 
					( $attributes_array["size"] < ( pow( 2, 16 ) - 2 ) ? "blob" : 
					( $attributes_array["size"] < ( pow( 2, 24 ) - 3 ) ? "mediumblob" : 
					( $attributes_array["size"] < ( pow( 2, 32 ) - 4 ) ? "longblob" : 
					( trigger_error( "File size is to big for mysql", E_USER_ERROR ) ) ) ) ) ) ) );
		case Sqloo::DATATYPE_OVERRIDE:
			return $attributes_array["override"];
		default: trigger_error( "Bad types: ".$attributes_array["type"], E_USER_ERROR );
		}
		return NULL;
	}
	
}

?>