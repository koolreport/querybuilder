<?php

namespace koolreport\querybuilder;

class MySQL extends SQL
{
    protected $identifierQuotes=array("`","`");//For table name and column name
}