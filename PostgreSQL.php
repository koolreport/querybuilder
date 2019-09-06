<?php

namespace koolreport\querybuilder;

class PostgreSQL extends SQL
{
    protected $identifierQuotes=array('"','"');//For table name and column name
}