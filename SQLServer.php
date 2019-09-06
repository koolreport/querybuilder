<?php

namespace koolreport\querybuilder;

class SQLServer extends SQL
{
    protected $identifierQuotes=array('"','"');//For table name and column name

    protected function buildSelectQuery()
    {
        $sql = "SELECT ";
        if ($this->query->distinct) {
            $sql.="DISTINCT ";
        }
        if (count($this->query->columns)>0) {
            $sql.=$this->getSelect($this->query->columns);
        } else {
            $sql.="*";
        }
        if (count($this->query->tables)>0) {
            $sql.=" FROM ".$this->getFrom($this->query->tables);
        } else {
            throw new \Exception("No table available in SQL Query");
        }

        if (count($this->query->joins)>0) {
            $sql.=$this->getJoin($this->query->joins);
        }

        if (count($this->query->conditions)>0) {
            $sql.=" WHERE ".$this->getWhere($this->query->conditions);
        }

        if (count($this->query->groups)>0) {
            $sql.=" GROUP BY ".$this->getGroupBy($this->query->groups);
        }

        if ($this->query->having) {
            $sql.=" HAVING ".$this->getHaving($this->query->having);
        }


        if (count($this->query->orders)>0) {
            $sql.=" ORDER BY ".$this->getOrderBy($this->query->orders);
        }

        if ($this->query->offset!==null) {
            $sql.=" OFFSET ".$this->query->offset." ROWS";
        }

        if ($this->query->limit!==null) {
            $sql.=" FETCH NEXT ".$this->query->limit." ROWS ONLY";
        }


        if (count($this->query->unions)>0) {
            $sql.=$this->getUnions($this->query->unions);
        }
        return $sql;
    }
}