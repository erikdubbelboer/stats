
stats
=====

Simple UDP stats.

Config
------

Make sure to edit config.js

Data
----

You can send the following data to the collector:

Data can be a comma (,) seperated list of:

`key:val`  
a data point (assumes each point is the total increment over a second)

`key:inc|c`  
increment a counter by `inc`

`key:d`  
delete key

Once a new key is received it will automatically be added to the database and you'll be able to see it's graphs.

