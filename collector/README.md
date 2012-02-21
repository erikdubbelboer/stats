
Collector
========

Simple UDP data collector. Processes data and stores it in Redis.

Config
------

Make sure to edit config.js

Data
----

Data can be a comma (,) seperated list of:

`key:val`  
a data point (assumes each point is the total increment over a second)

`key:inc|c`  
increment a counter by `inc`

`key:d`  
delete key

