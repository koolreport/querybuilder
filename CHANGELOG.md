# Change Log

## Version 2.0.0

1. `Query`: Fix the selectRaw rendering
2. `Query`: Adding parameters to `whereRaw()`, `havingRaw()`, `orderByRaw()` methods
3. `SQL`: Add options to set quotes for identifiers
4. `SQL`: Make default not use quotes for identifiers
5. `SQLServer`: Generate correct query for limit and offset
6. `Query`: Add `groupByRaw()` method
7. `Query`: Add `create()` static function to create query from array
8. `Query`: Adding `toArray()` function to export query to array format
9. `Query`: Add `fill()` method to quickly fill query with array
10. `Query`: Fix the whereRaw binding params

## Version 1.5.0

1. Change README

## Version 1.4.0

1. Fix aggregate

## Version 1.3.0

1. Fix error of `where` condition when value is boolean type
2. Fix the cover identifier for [table.column] format

## Version 1.2.0

1. Change function "as" to "alias",
2. Change function "switch" to "branch"
3. Render query correctly for update and insert when encounter boolean value
4. Fix the PostgresQL interpreter


## Version 1.1.0

1. When value of where condition is null, change from "=" to is null

## Version 1.0.0