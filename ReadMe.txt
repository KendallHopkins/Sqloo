Features:
-Object Oriented Queries
    -With the ability to code SQL with OO you gain many bonuses
        -Simplify creating queries that change based on conditionals.
        -Pass queries between functions.
        -Much easier to reuse code.
        -Joins with less code.
        -Simplify N:M joins.
        -No mysql code in your php documents.
        -Automatically process variables to help protect from query injections.
        -Less likely to make SQL syntax mistakes.
        -Less likely to mistakingly doing a cartesian cross product instead of a normal join.
-Self Generating/Altering Database Schema
    -Create a database based on a schema setup in the code
    -Update a database if there are differences between code schema and database schema.

Subfeatures:
-"Magic" Created/Modified columns
    -Can automatically set created/modified columns on updates/inserts transparently.
-Transactional Support
    -Full support for mysql transactions
-Database Pool
    -Automatically distributes queries between master and slave databases