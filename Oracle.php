<?php

namespace koolreport\querybuilder;

class Oracle extends SQL
{
    protected function buildSelectQuery($options = [])
    {
        $sql = "SELECT ";
        if ($this->query->distinct) {
            $sql .= "DISTINCT ";
        }
        if (count($this->query->columns) > 0) {
            $sql .= $this->getSelect($this->query->columns);
        } else {
            $sql .= "*";
        }
        if (count($this->query->tables) > 0) {
            $sql .= " FROM " . $this->getFrom($this->query->tables);
        } else {
            throw new \Exception("No table available in SQL Query");
        }

        if (count($this->query->joins) > 0) {
            $sql .= $this->getJoin($this->query->joins);
        }

        if (count($this->query->conditions) > 0) {
            $where = trim($this->getWhere($this->query->conditions, $options));
            if (!empty($where)) $sql .= " WHERE " . $where;
        }

        if (count($this->query->groups) > 0) {
            $groupBy = trim($this->getGroupBy($this->query->groups));
            if (!empty($groupBy)) $sql .= " GROUP BY " . $groupBy;
        }

        if ($this->query->having) {
            $having = trim($this->getHaving($this->query->having, $options));
            if (!empty($having)) $sql .= " HAVING " . $having;
        }


        if (count($this->query->orders) > 0) {
            $orderBy = trim($this->getOrderBy($this->query->orders));
            if (!empty($orderBy)) $sql .= " ORDER BY " . $orderBy;
        }

        if ($this->query->offset !== null) {
            $sql .= " OFFSET ".$this->query->offset." ROWS ";
        }

        if ($this->query->limit !== null) {
            $sql .= " FETCH NEXT ".$this->query->limit." ROWS ONLY ";
        }


        if (count($this->query->unions) > 0) {
            $sql .= $this->getUnions($this->query->unions);
        }
        if ($this->query->lock) {
            $sql .= " " . $this->query->lock;
        }
        return $sql;
    }

}