<?php

namespace koolreport\querybuilder;

class SQLite extends SQL
{
    protected $escapeValue = true;
    protected $identifierQuotes=array("`","`");//For table name and column name

    protected function escapeString($string)
    {
        if($this->escapeValue) {
            return str_replace("'","''",$string);
        }
        return $string;
    }
}