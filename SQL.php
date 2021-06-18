<?php

namespace koolreport\querybuilder;

use \koolreport\core\Utility as Util;

class SQL
{
    protected $query;
    protected $identifierQuotes = null;
    protected $escapeValue = true;

    public function __construct($query, $quoteIdentifier = false)
    {
        $this->query = $query;
        if (!$quoteIdentifier) {
            $this->identifierQuotes = null;
        } elseif (gettype($quoteIdentifier) === "array") {
            $this->identifierQuotes = $quoteIdentifier;
        }
    }

    protected function renderValue($value)
    {
        if ($value === null) {
            return "NULL";
        } elseif (gettype($value) === "array") {
            for ($i = 0; $i < count($value); $i++) {
                if (gettype($value[$i]) == "string") {
                    $value[$i] = $this->coverValue($this->escapeString($value[$i]));
                }
            }
            return "(" . implode(",", $value) . ")";
        } elseif (gettype($value) === "string") {
            return $this->coverValue($this->escapeString($value));
        } elseif (gettype($value) === "boolean") {
            return ($value === true) ? 1 : 0;
        }
        return $value;
    }

    protected function escapeString($string)
    {
        return DB::escapeString($string);
    }
    protected function coverValue($value)
    {
        return "'$value'";
    }
    protected function quoteIdentifier($name)
    {
        if ($this->identifierQuotes === null || $this->identifierQuotes === array()) {
            return $name;
        }
        $dot = strpos($name, ".");
        if ($dot === false) {
            return $this->identifierQuotes[0] . $name . $this->identifierQuotes[1];
        } else {
            $table = substr($name, 0, $dot);
            $column = str_replace($table . ".", "", $name);
            return $this->identifierQuotes[0] . $table . $this->identifierQuotes[1]
                . "." . $this->identifierQuotes[0] . $column . $this->identifierQuotes[1];
        }
    }

    protected function getWhere($conditions, $options = [])
    {
        $useSQLParams = Util::get($options, "useSQLParams", false); //"named parameter", "question mark"
        if (
            $useSQLParams !== false
            && $useSQLParams !== "name"
            && $useSQLParams !== "question mark"
        ) {
            $useSQLParams = "name";
        }
        // echo "useSQLParams=$useSQLParams<br>";
        // var_dump($this->query); echo "<br>";
        $queryId = gettype($this->query) === 'object' ? $this->query->id : 0;
        $result = "";
        foreach ($conditions as $condition) {
            if (gettype($condition) == "array") {
                switch ($condition[0]) {
                    case "[{exists}]":
                        $class = get_class($this);
                        $object = new $class($condition[1], $this->identifierQuotes);
                        $result .= "exists( " . $object->buildQuery() . " )";
                        break;
                    case "[{raw}]":
                        $result .= $condition[1];
                        break;
                    default:
                        $field = $condition[0];
                        $operator = Util::get($condition, 1);
                        if (gettype($operator) === 'string') $operator = strtoupper(trim($operator));
                        $value = Util::get($condition, 2);
                        // var_dump($operator); echo "<br>";
                        // var_dump($value); echo "<br>";
                        $part = "{key} {operator} {value}";
                        $part = str_replace("{key}", $this->quoteIdentifier($field), $part);
                        $part = str_replace("{operator}", $operator, $part);
                        if (gettype($value) == "string" && strpos($value, "[{colName}]") === 0) {
                            $part = str_replace("{value}", str_replace("[{colName}]", "", $this->quoteIdentifier($value)), $part);
                        } else {
                            // echo "operator=$operator<br>";
                            if ($useSQLParams === 'name') {
                                if (($operator === "IN" || $operator === "NOT IN")
                                    && gettype($value) === "array"
                                ) {
                                    // echo "in not in<br>";
                                    $valueLength = count($value);
                                    $paramNames = [];
                                    $paramCount = $this->query->paramCount++;
                                    for ($i = 0; $i < $valueLength; $i++) {
                                        $paramName = ":{$queryId}_param_{$paramCount}_$i";
                                        $paramNames[] = $paramName;
                                        $this->query->sqlParams[$paramName] = $value[$i];
                                    }
                                    $paramNames = implode(", ", $paramNames);
                                    $part = str_replace("{value}", "(" . $paramNames . ")", $part);
                                } else if (
                                    ($operator === "IS" || $operator === "IS NOT")
                                    && $value === null
                                ) {
                                    // echo "is null is not null<br>";
                                    $part = str_replace("{value}", $this->renderValue($value), $part);
                                } else {
                                    $paramName = ":" . $queryId . "_param_" . ($this->query->paramCount++);
                                    // echo "paramName=$paramName<br>";
                                    $part = str_replace("{value}", $paramName, $part);
                                    $this->query->sqlParams[$paramName] = $value;
                                }
                            } else if ($useSQLParams === 'question mark') {
                                if (
                                    ($operator === "IN" || $operator === "NOT IN")
                                    && gettype($value) === "array"
                                ) {
                                    $valueLength = count($value);
                                    $paramNames = [];
                                    $paramCount = $this->query->paramCount++;
                                    for ($i = 0; $i < $valueLength; $i++) {
                                        $paramName = "?";
                                        $paramNames[] = $paramName;
                                        $this->query->sqlParams[] = $value[$i];
                                    }
                                    $paramNames = implode(", ", $paramNames);
                                    $part = str_replace("{value}", "(" . $paramNames . ")", $part);
                                } else if (
                                    ($operator === "IS" || $operator === "IS NOT")
                                    && $value === null
                                ) {
                                    $part = str_replace("{value}", $this->renderValue($value), $part);
                                } else {
                                    $part = str_replace("{value}", "?", $part);
                                    $this->query->sqlParams[] = $value;
                                }
                            } else {
                                $part = str_replace("{value}", $this->renderValue($value), $part);
                            }
                        }
                        $result .= $part;
                        break;
                }
            } elseif (gettype($condition) == "string") {
                $result .= " $condition ";
            } elseif (is_a($condition, 'koolreport\querybuilder\Query')) {
                $result .= "(" . $this->getWhere($condition->conditions, $options) . ")";
            }
        }
        return $result;
    }

    protected function getFrom($tables)
    {
        $array = array();
        foreach ($tables as $table) {
            if (gettype($table) == "array") {
                $class = get_class($this);
                $interpreter = new $class($table[0], $this->identifierQuotes);
                array_push($array, "(" . $interpreter->buildQuery() . ") " . $this->quoteIdentifier($table[1]));
            } else {
                array_push($array, $this->quoteIdentifier($table));
            }
        }
        return implode(", ", $array);
    }

    protected function getOrderBy($orders)
    {
        $array = array();
        foreach ($orders as $order) {
            if ($order[0] == "[{raw}]") {
                array_push($array, $order[1]);
            } else {
                array_push($array, $this->quoteIdentifier($order[0]) . " " . $order[1]);
            }
        }
        return implode(", ", $array);
    }

    protected function getGroupBy($groups)
    {
        $array = array();
        foreach ($groups as $group) {
            if (strpos($group, "[{raw}]") !== false) {
                $group = str_replace("[{raw}]", "", $group);
                array_push($array, $group);
            } else {
                array_push($array, $this->quoteIdentifier($group));
            }
        }
        return implode(", ", $array);
    }

    protected function getHaving($having, $options = [])
    {
        return $this->getWhere($having->conditions, $options);
    }

    protected function getJoin($joins)
    {
        $array = array();
        foreach ($joins as $join) {
            $class = get_class($this);
            $object = new $class($join[1], $this->identifierQuotes);
            $part = " $join[0] " . $this->quoteIdentifier($join[1]);
            if (isset($join[2])) {
                $part .= " ON " . $object->getWhere($join[2]->conditions);
            }
            array_push($array, $part);
        }
        return implode(" ", $array);
    }

    protected function getSelect($columns)
    {
        $array = array();
        foreach ($columns as $column) {
            $part = "";
            if (gettype($column[0]) == "array") {
                //Raw or aggregate
                if ($column[0][0] == "[{raw}]") {
                    $part .= $column[0][1];
                } else if ($column[0][0] == "COUNT" && $column[0][1] == 1) {
                    $part .= "COUNT(1)";
                } else if ($column[0][0] == "COUNT DISTINCT") {
                    $part .= "COUNT(DISTINCT " . $this->quoteIdentifier($column[0][1]) . ")";
                } else {
                    $part .= $column[0][0] . "(" . $this->quoteIdentifier($column[0][1]) . ")";
                }
            } else {
                $part .= $this->quoteIdentifier($column[0]);
            }
            if (isset($column[1])) {
                $part .= " AS " . $this->quoteIdentifier($column[1]);
            }
            array_push($array, $part);
        }
        return implode(", ", $array);
    }
    protected function getUnions($unions)
    {
        $res = "";
        $class = get_class($this);
        foreach ($unions as $union) {
            $interpreter = new $class($union, $this->identifierQuotes);
            $res .= " UNION (" . $interpreter->buildQuery() . ")";
        }
        return $res;
    }

    protected function getUpdateSet($list)
    {
        $array = array();
        foreach ($list as $key => $value) {
            $part = "";
            if (gettype($value) == "array") {
                $part .= $this->quoteIdentifier($key) . " = " . $this->quoteIdentifier($value[0]) . " $value[1] $value[2]";
            } elseif (gettype($value) == "string") {
                $part .= $this->quoteIdentifier($key) . " = " . $this->coverValue($this->escapeString($value));
            } elseif (gettype($value) == "boolean") {
                $part .= $this->quoteIdentifier($key) . " = " . (($value === true) ? "1" : "0");
            } else {
                $part .= $this->quoteIdentifier($key) . " = " . (($value !== null) ? $value : "NULL");
            }
            array_push($array, $part);
        }
        return implode(", ", $array);
    }

    protected function getInsertValues($list)
    {
        $keys = array_keys($list);
        $values = array_values($list);
        for ($i = 0; $i < count($values); $i++) {
            if (gettype($values[$i]) == "string") {
                $values[$i] = $this->coverValue($this->escapeString($values[$i]));
            } elseif (gettype($values[$i]) == "boolean") {
                $values[$i] = ($values[$i] === true) ? "1" : "0";
            }
        }
        for ($i = 0; $i < count($keys); $i++) {
            $keys[$i] = $this->quoteIdentifier($keys[$i]);
        }

        return ' (' . implode(", ", $keys) . ')' . ' VALUES ' . '(' . implode(", ", $values) . ')';
    }

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

        if ($this->query->limit !== null) {
            $sql .= " LIMIT " . $this->query->limit;
        }

        if ($this->query->offset !== null) {
            $sql .= " OFFSET " . $this->query->offset;
        }

        if (count($this->query->unions) > 0) {
            $sql .= $this->getUnions($this->query->unions);
        }
        if ($this->query->lock) {
            $sql .= " " . $this->query->lock;
        }
        return $sql;
    }

    protected function buildUpdateQuery($options = [])
    {
        $sql = "UPDATE ";
        if (count($this->query->tables) == 1) {
            $sql .= $this->getFrom(array($this->query->tables[0]));
        } elseif (count($this->query->tables) > 1) {
            throw new \Exception("Only one table is updated");
        } else {
            throw new \Exception("Update query need table specified");
        }
        if (count($this->query->values) > 0) {
            $sql .= " SET " . $this->getUpdateSet($this->query->values);
        }

        if (count($this->query->conditions) > 0) {
            $sql .= " WHERE " . $this->getWhere($this->query->conditions);
        }
        return $sql;
    }

    protected function buildInsertQuery($options = [])
    {
        $sql = "INSERT INTO ";
        if (count($this->query->tables) == 1) {
            $sql .= $this->getFrom(array($this->query->tables[0]));
        } elseif (count($this->query->tables) > 1) {
            throw new \Exception("Only one table is inserted");
        } else {
            throw new \Exception("Insert query need a table specified");
        }
        if (count($this->query->values) > 0) {
            $sql .= $this->getInsertValues($this->query->values);
        }
        if (count($this->query->conditions) > 0) {
            $sql .= " WHERE " . $this->getWhere($this->query->conditions);
        }
        return $sql;
    }
    protected function buildDeleteQuery($options = [])
    {
        $sql .= "DELETE FROM ";
        if (count($this->query->tables) == 1) {
            $sql .= $this->getFrom(array($this->query->tables[0]));
        } elseif (count($this->query->tables) > 1) {
            throw new \Exception("Only one table is deleted");
        } else {
            throw new \Exception("Delete query need a table specified");
        }
        if (count($this->query->conditions) > 0) {
            $sql .= " WHERE " . $this->getWhere($this->query->conditions);
        }
        return $sql;
    }

    protected function buildProcedureQuery($options = [])
    {
        $sql = "";
        foreach($this->query->procedures as $proc) {
            $statement = "CALL ".$proc[0]."(@params);";
            $params = [];
            foreach($proc[1] as $value) {
                if(gettype($value)==="string") {
                    array_push($params,$this->coverValue($this->escapeString($value)));
                } else {
                    array_push($params,$value);
                }
            }
            $statement = str_replace("@params",implode(",",$params),$statement);
            $sql.=$statement;
        }
        return $sql;
    }

    public function buildQuery($options = [])
    {
        $this->query->paramCount = 0;
        $this->query->sqlParams = [];
        switch ($this->query->type) {
            case "select":
                return $this->buildSelectQuery($options);
            case "update":
                return $this->buildUpdateQuery($options);
            case "insert":
                return $this->buildInsertQuery($options);
            case "delete":
                return $this->buildDeleteQuery($options);
            case "procedure":
                return $this->buildProcedureQuery($options);    
        }
        return "Unknown query type";
    }

    public static function type($query, $quoteIdentifier = false)
    {
        $class = get_called_class();
        $interpreter = new $class($query, $quoteIdentifier);
        return $interpreter->buildQuery();
    }
}
