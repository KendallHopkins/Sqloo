<?php

/*
The MIT License

Copyright (c) 2010 Kendall Hopkins

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

class Sqloo_Exception extends Exception
{
	
	const BAD_INPUT = -1;
	const CONNECTION_FAILED = -2;
	const TRANSACTION_REQUIRED = -3;

	//http://docstore.mik.ua/orelly/java-ent/jenut/ch08_06.htm
	const QUERY_NO_DATA = 0x02;
	const QUERY_DYNAMIC_SQL_ERROR = 0x07;
	const QUERY_CONNECTION_EXCEPTION = 0x08;
	const QUERY_FEATURE_NOT_SUPPORTED = 0x0A;
	const QUERY_CARDINALITY_VIOLATION = 0x21;
	const QUERY_DATA_EXCEPTION = 0x22;
	const QUERY_INTEGRITY_CONSTRAINT_VIOLATION = 0x23;
	const QUERY_INVALID_CURSOR_STATE = 0x24;
	const QUERY_INVALID_TRANSACTION_STATE = 0x25;
	const QUERY_INVALID_SQL_STATEMENT_NAME = 0x26;
	const QUERY_TRIGGERED_DATA_CHANGE_VIOLATION = 0x27;
	const QUERY_INVALID_AUTHORIZATION_SPECIFICATION = 0x28;
	const QUERY_SYNTAX_ERROR = 0x2A;
	const QUERY_DEPENDENT_PRIVILEGE_DESCRIPTORS_STILL_EXIST = 0x2B;
	const QUERY_INVALID_CHARACTER_SET_NAME = 0x2C;
	const QUERY_INVALID_TRANSACTION_TERMINATION = 0x2D;
	const QUERY_INVALID_CONNECTION_NAME = 0x2E;
	const QUERY_INVALID_SQL_DESCRIPTOR_NAME = 0x33;
	const QUERY_INVALID_CURSOR_NAME = 0x34;
	const QUERY_INVALID_CONDITION_NUMBER = 0x35;
	const QUERY_SYNTAX_ERROR_DYNAMIC = 0x37;
	const QUERY_AMBIGUOUS_CURSOR_NAME = 0x3C;
	const QUERY_INVALID_SCHEMA_NAME = 0x3F;
	const QUERY_TRANSACTION_ROLLBACK = 0x40;
	const QUERY_SYNTAX_ERROR_VIOLATION = 0x42;
	const QUERY_WITH_CHECK_OPTION_VIOLATION = 0x44;
	
}

?>