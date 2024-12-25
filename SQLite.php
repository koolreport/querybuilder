<?php

namespace koolreport\querybuilder;

class SQLite extends SQL
{
    protected $escapeValue = false;
    protected $identifierQuotes=array("`","`");//For table name and column name
}