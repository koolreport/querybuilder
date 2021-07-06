<?php

namespace koolreport\querybuilder;

use \koolreport\core\Utility as Util;

class Query
{
    protected static $instanceId = 0;

    public $id;
    public $paramCount = 0;
    public $sqlParams = [];

    public $type = "select"; //"update","delete","insert","procedure"
    public $tables;
    public $columns;
    public $conditions;
    public $orders;
    public $groups;
    public $having;
    public $limit = null;
    public $offset = null;
    public $joins;
    public $distinct = false;
    public $unions;
    public $values;
    public $lock = null;
    public $procedures;

    protected $schemas;

    public function __construct()
    {
        $this->id = "qb_" . ($this::$instanceId++);

        $this->tables = array();
        $this->columns = array();
        $this->conditions = array();
        $this->orders = array();
        $this->groups = array();
        $this->having = null;
        $this->joins = array();
        $this->unions = array();
        $this->values = array();
        $this->procedures = array();
    }

    public function call($procedureName,$params=array()) {
        $this->type = "procedure";
        array_push($this->procedures,array(
            $procedureName,
            $params
        ));
        return $this;
    }

    public function setSchemas($schemas)
    {
        $this->schemas = $schemas;
        // Util::prettyPrint($schemas);
        return $this;
    }

    public function getSchemas()
    {
        return $this->schemas;
    }

    public function isTableInSchemas($table)
    {
        if (!isset($this->schemas)) return true;
        foreach ($this->schemas as $schema) {
            $tableInfos = Util::get($schema, 'tables', []);
            if (isset($tableInfos[$table])) return true;
        }
        return false;
    }

    public function isFieldInSchemas($field)
    {
        // echo "isFieldInSchemas field=$field<br>";
        if (!isset($this->schemas)) return true;
        foreach ($this->schemas as $schema) {
            $tableInfos = Util::get($schema, 'tables', []);
            foreach ($tableInfos as $table => $fieldInfos) {
                foreach ($fieldInfos as $f => $fieldInfo) {
					$exp = Util::get($fieldInfo, "expression", $f);
                    if ($f === $field || "$table.$f" === $field
						|| $exp === $field) return true;
                }
            }
        }
        return false;
    }

    public function distinct()
    {
        $this->distinct = true;
        return $this;
    }

    public function from()
    {
        $params = func_get_args();
        if (count($params) > 1) {
            foreach ($params as $param) $this->from($param);
        } elseif (gettype($params[0]) == "string") {
            if ($this->isTableInSchemas($params[0])) $this->tables = $params;
        } elseif (gettype($params[0]) == "array") {
            foreach ($params[0] as $key => $value) {
                if (gettype($value) == "string") {
                    if ($this->isTableInSchemas($params[0])) {
                        array_push($this->tables, $value);
                    }
                } elseif (is_callable($value)) {
                    $query = new Query;
                    $value($query);
                    array_push($this->tables, array($query, $key));
                }
            }
        }
        return $this;
    }

    public function select()
    {
        $params = func_get_args();
        foreach ($params as $columnName) {
            if ($this->isFieldInSchemas($columnName)) {
                array_push($this->columns, array($columnName));
            }
        }
        return $this;
    }
    public function selectRaw($text, $params = array())
    {
        array_push($this->columns, array(DB::raw($text, $params)));
        return $this;
    }

    public function addSelect()
    {
        call_user_func_array(array($this, "select"), func_get_args());
        return $this;
    }
    public function addSelectRaw($text, $params = array())
    {
        return $this->selectRaw($text, $params);
    }

    protected function aggregate($method, $params)
    {
        foreach ($params as $name) {
            array_push($this->columns, array(array($method, $name)));
        }
        return $this;
    }

    public function alias($name)
    {
        $index = count($this->columns) - 1;
        if ($index > -1) {
            array_push($this->columns[$index], $name);
        }
        return $this;
    }


    public function count()
    {
        $params = func_get_args();
        if (count($params) == 0) {
            $params = array("1");
        }
        return $this->aggregate("COUNT", $params);
    }

    public function count_distinct()
    {
        return $this->aggregate("COUNT DISTINCT", func_get_args());
    }

    public function sum()
    {
        return $this->aggregate("SUM", func_get_args());
    }
    public function avg()
    {
        return $this->aggregate("AVG", func_get_args());
    }
    public function max()
    {
        return $this->aggregate("MAX", func_get_args());
    }
    public function min()
    {
        return $this->aggregate("MIN", func_get_args());
    }

    protected function andCondition()
    {
        // if (count($this->conditions) > 0) {
        //     array_push($this->conditions, "AND");
        //     return $this;
        // }
        for ($i = count($this->conditions) - 1; $i >= 0; $i--) {
            $condition = $this->conditions[$i];
            if ($condition !== "(") break;
        }
        // echo "before condition = ";
        // \koolreport\core\Utility::prettyPrint($this->conditions);
        // echo "i=$i<br>";
        if ($i > -1) array_splice($this->conditions, $i + 1, 0, "AND");
        // echo "after condition = ";
        // \koolreport\core\Utility::prettyPrint($this->conditions);
        // echo "<br><br>";
        return $this;
    }

    protected function orCondition()
    {
        // if (count($this->conditions) > 0) {
        //     array_push($this->conditions, "OR");
        //     return $this;
        // }
        for ($i = count($this->conditions) - 1; $i >= 0; $i--) {
            $condition = $this->conditions[$i];
            if ($condition !== "(") break;
        }
        // echo "before condition = ";
        // \koolreport\core\Utility::prettyPrint($this->conditions);
        // echo "i=$i<br>";
        if ($i > -1) array_splice($this->conditions, $i + 1, 0, "OR");
        // echo "after condition = ";
        // \koolreport\core\Utility::prettyPrint($this->conditions);
        // echo "<br><br>";
        return $this;
    }

    public function whereOpenBracket()
    {
        array_push($this->conditions, "(");
        return $this;
    }

    public function whereCloseBracket()
    {
        $lastcondition = end($this->conditions);
        if ($lastcondition === "(") {
            array_pop($this->conditions);
        } else {
            array_push($this->conditions, ")");
        }
        return $this;
    }

    public function havingOpenBracket()
    {
        $params = func_get_args();
        if (!$this->having) {
            $this->having = new Query;
        }
        call_user_func_array(array($this->having, "whereOpenBracket"), $params);
        return $this;
    }

    public function havingCloseBracket()
    {
        $params = func_get_args();
        if (!$this->having) {
            $this->having = new Query;
        }
        call_user_func_array(array($this->having, "whereCloseBracket"), $params);
        return $this;
    }

    public function isStandardConditionValid($params)
    {
        $field = $params[0];
        $compareOperator = strtolower(trim($params[1]));
        // $value1 = Util::get($params, 2);
        // $value2 = Util::get($params, 3);
        if (!$this->isFieldInSchemas($field)) return false;
        $compareOperators = array_flip([
            "=", "<", "<=", ">=", ">", "!=", "<>",
            "is", "is not", "between", "not between", 
            "in", "not in", "like", "not like"
        ]);
        if (!isset($compareOperators[$compareOperator])) return false;
        return true;
    }

    protected function pushStandardCondition($params)
    {
        if ($params[2] === null && $params[1] === "=") {
            $params[1] = "IS";
        }
        $field = $params[0];
        if ($this->isFieldInSchemas($field)) {
            array_push($this->conditions, $params);
        }
    }


    public function where()
    {
        $params = func_get_args();
        switch (count($params)) {
            case 1:
                if (gettype($params[0]) == "array") {
                    $query = new Query;
                    foreach ($params[0] as $where) {
                        call_user_func_array(array($query, "where"), $where);
                    }
                    $this->andCondition();
                    array_push($this->conditions, $query);
                } elseif (is_callable($params[0])) {
                    $query = new Query;
                    $params[0]($query);
                    $this->andCondition();
                    array_push($this->conditions, $query);
                }
                break;
            case 2:
                $this->where($params[0], "=", $params[1]);
                break;
            case 3:
            case 4:
            case 5:
                if ($this->isStandardConditionValid($params)) {
                    $this->andCondition();
                    $this->pushStandardCondition($params);
                } else {
                    // echo "isStandardConditionValid = false<br>";
                }
                break;
        }
        return $this;
    }
    public function orWhere()
    {
        $params = func_get_args();
        switch (count($params)) {
            case 1:
                if (gettype($params[0]) == "array") {
                    $query = new Query;
                    foreach ($params[0] as $where) {
                        call_user_func_array(array($query, "where"), $where);
                    }
                    $this->orCondition();
                    array_push($this->conditions, $query);
                } elseif (is_callable($params[0])) {
                    $query = new Query;
                    $params[0]($query);
                    $this->orCondition();
                    array_push($this->conditions, $query);
                }
                break;
            case 2:
                $this->orWhere($params[0], "=", $params[1]);
                break;
            case 3:
                if ($this->isStandardConditionValid($params)) {
                    $this->orCondition();
                    $this->pushStandardCondition($params);
                }
                break;
        }
        return $this;
    }

    //Null
    public function whereNull($name)
    {
        return $this->where($name, 'IS', null);
    }
    public function whereNotNull($name)
    {
        return $this->where($name, 'IS NOT', null);
    }
    public function orWhereNull($name)
    {
        return $this->orWhere($name, 'IS', null);
    }
    public function orWhereNotNull($name)
    {
        return $this->orWhere($name, 'IS NOT', null);
    }

    //In
    public function whereIn($name, $array)
    {
        return $this->where($name, 'IN', $array);
    }
    public function whereNotIn($name, $array)
    {
        return $this->where($name, 'NOT IN', $array);
    }
    public function orWhereIn($name, $array)
    {
        return $this->orWhere($name, 'IN', $array);
    }
    public function orWhereNotIn($name, $array)
    {
        return $this->orWhere($name, 'NOT IN', $array);
    }

    //Between
    public function whereBetween($name, $array)
    {
        return $this->where(array(
            array($name, ">=", $array[0]),
            array($name, "<=", $array[1])
        ));
    }
    public function whereNotBetween($name, $array)
    {
        $this->where(function ($query) use ($name, $array) {
            $query->where($name, "<", $array[0])
                ->orWhere($name, ">", $array[1]);
        });
        return $this;
    }
    public function orWhereBetween($name, $array)
    {
        return $this->orWhere(array(
            array($name, ">=", $array[0]),
            array($name, "<=", $array[1])
        ));
    }
    public function orWhereNotBetween($name, $array)
    {
        $this->orWhere(function ($query) use ($name, $array) {
            $query->where($name, "<", $array[0])
                ->orWhere($name, ">", $array[1]);
        });
        return $this;
    }

    //Datetime

    protected function whereFunction($name, $params)
    {
        $c = count($params);
        if ($c > 0 && !$this->isFieldInSchemas($params[0])) return $this;
        if ($c == 1) {
            return $this->where("$name($params[0])", "=", date("Y-m-d"));
        } elseif ($c == 2) {
            return $this->where("$name($params[0])", "=", $params[1]);
        } elseif ($c > 2) {
            return $this->where("$name($params[0])", $params[1], $params[2]);
        }
        return $this;
    }
    protected function orWhereFunction($name, $params)
    {
        $c = count($params);
        if ($c == 1) {
            return $this->orWhere("$name($params[0])", "=", date("Y-m-d"));
        } elseif ($c == 2) {
            return $this->orWhere("$name($params[0])", "=", $params[1]);
        } elseif ($c > 2) {
            return $this->orWhere("$name($params[0])", $params[1], $params[2]);
        }
        return $this;
    }

    public function whereDate()
    {
        return $this->whereFunction("DATE", func_get_args());
    }
    public function whereDay()
    {
        return $this->whereFunction("DAY", func_get_args());
    }
    public function whereMonth()
    {
        return $this->whereFunction("MONTH", func_get_args());
    }
    public function whereYear()
    {
        return $this->whereFunction("YEAR", func_get_args());
    }
    public function whereTime()
    {
        return $this->whereFunction("TIME", func_get_args());
    }
    public function orWhereDate()
    {
        return $this->whereFunction("DATE", func_get_args());
    }
    public function orWhereDay()
    {
        return $this->orWhereFunction("DAY", func_get_args());
    }
    public function orWhereMonth()
    {
        return $this->orWhereFunction("MONTH", func_get_args());
    }
    public function orWhereYear()
    {
        return $this->orWhereFunction("YEAR", func_get_args());
    }
    public function orWhereTime()
    {
        return $this->orWhereFunction("TIME", func_get_args());
    }

    //Column
    public function whereColumn()
    {
        $params = func_get_args();
        switch (count($params)) {
            case 1:
                if (gettype($params[0]) == "array") {
                    $query = new Query;
                    foreach ($params[0] as $where) {
                        call_user_func_array(array($query, "whereColumn"), $where);
                    }
                    $this->andCondition();
                    array_push($this->conditions, $query);
                }
                break;
            case 2:
                if (
                    $this->isFieldInSchemas($params[0])
                    && $this->isFieldInSchemas($params[1])
                ) {
                    $this->whereColumn($params[0], "=", $params[1]);
                }
                break;
            case 3:
                if (
                    $this->isFieldInSchemas($params[0])
                    && $this->isFieldInSchemas($params[2])
                ) {
                    $this->andCondition();
                    $params[2] = "[{colName}]" . $params[2];
                    array_push($this->conditions, $params);
                }
                break;
        }
        return $this;
    }

    public function orWhereColumn()
    {
        $params = func_get_args();
        switch (count($params)) {
            case 1:
                if (gettype($params[0]) == "array") {
                    $query = new Query;
                    foreach ($params[0] as $where) {
                        call_user_func_array(array($query, "orWhereColumn"), $where);
                    }
                    $this->orCondition();
                    array_push($this->conditions, $query);
                }
                break;
            case 2:
                if (
                    $this->isFieldInSchemas($params[0])
                    && $this->isFieldInSchemas($params[1])
                ) {
                    $this->orWhereColumn($params[0], "=", $params[1]);
                }
                break;
            case 3:
                if (
                    $this->isFieldInSchemas($params[0])
                    && $this->isFieldInSchemas($params[2])
                ) {
                    $this->orCondition();
                    $params[2] = "[{colName}]" . $params[2];
                    array_push($this->conditions, $params);
                }
                break;
        }
        return $this;
    }


    //Exists
    public function whereExists($table)
    {
        if (is_callable($table)) {
            $query = new Query;
            $table($query);
            $this->andCondition();
            array_push($this->conditions, array("[{exists}]", $query));
        } else {
            throw new \Exception("whereExists() required function as parameter");
        }
        return $this;
    }
    public function orWhereExists($table)
    {
        if (is_callable($table)) {
            $query = new Query;
            $table($query);
            $this->orCondition();
            array_push($this->conditions, array("[{exists}]", $query));
        } else {
            throw new \Exception("whereExists() required function as parameter");
        }
        return $this;
    }

    //Raw
    public function whereRaw($raw, $params = null)
    {
        $this->andCondition();
        array_push($this->conditions, DB::raw($raw, $params));
        return $this;
    }
    public function orWhereRaw($raw, $params = null)
    {
        $this->orCondition();
        array_push($this->conditions, DB::raw($raw, $params));
        return $this;
    }


    //------------------//
    public function orderBy()
    {
        $params = func_get_args();
        if (count($params) == 1) {
            if (gettype($params[0]) == "array") {
                foreach ($params[0] as $order) {
                    call_user_func_array(array($this, "orderBy"), $order);
                }
            } else {
                $field = $params[0];
                if ($this->isFieldInSchemas($field)) {
                    $this->orderBy($field, 'asc');
                }
            }
        } elseif (count($params) > 1) {
            array_push($this->orders, $params);
        }
        return $this;
    }

    public function orderByRaw($raw, $params = null)
    {
        array_push($this->orders, DB::raw($raw, $params));
        return $this;
    }

    public function latest($name = 'created_at')
    {
        return $this->orderBy($name, 'desc');
    }

    public function oldest($name = 'created_at')
    {
        return $this->orderBy($name, 'asc');
    }

    //--------------------//
    public function groupBy()
    {
        $params = func_get_args();
        foreach ($params as $group) {
            if (
                !in_array($group, $this->groups)
                && $this->isFieldInSchemas($group)
            ) {
                array_push($this->groups, $group);
            }
        }
        return $this;
    }

    public function groupByRaw()
    {
        $params = func_get_args();
        foreach ($params as $group) {
            if (!in_array("[{raw}]" . $group, $this->groups)) {
                array_push($this->groups, "[{raw}]" . $group);
            }
        }
        return $this;
    }

    public function having()
    {
        $params = func_get_args();

        if (!$this->having) {
            $this->having = new Query;
        }
        call_user_func_array(array($this->having, "where"), $params);
        return $this;
    }

    public function orHaving()
    {
        $params = func_get_args();
        if (!$this->having) {
            $this->having = new Query;
        }

        call_user_func_array(array($this->having, "orWhere"), $params);
        return $this;
    }

    public function havingRaw($raw, $params = null)
    {
        if (!$this->having) {
            $this->having = new Query;
        }
        $this->having->whereRaw($raw, $params);
        return $this;
    }

    public function orHavingRaw($raw, $params = null)
    {
        if (!$this->having) {
            $this->having = new Query;
        }
        $this->having->orWhereRaw($raw, $params);
        return $this;
    }

    //---------------//
    public function skip($number)
    {
        return $this->offset($number);
    }
    public function offset($number)
    {
        if (is_numeric($number)) {
            $this->offset = $number;
        }
        return $this;
    }

    public function limit($number)
    {
        if (is_numeric($number)) {
            $this->limit = $number;
        }
        return $this;
    }

    public function take($number)
    {
        return $this->limit($number);
    }

    public function first()
    {
        $this->limit = 1;
        return $this;
    }

    //--------------//
    public function when($condition, $trueExecution, $falseExecution = null)
    {
        if ($condition) {
            if (is_callable($trueExecution)) {
                $trueExecution($this);
            }
        } else {
            if (is_callable($falseExecution)) {
                $falseExecution($this);
            }
        }
        return $this;
    }

    public function branch($value, $array)
    {
        if (isset($array[$value]) && is_callable($array[$value])) {
            $array[$value]($this);
        }
        return $this;
    }

    //---------------//
    protected function allJoin($method, $params)
    {
        $table = $params[0];
        if (!$this->isTableInSchemas($table)) return $this;
        $join = array($method, $table);
        array_splice($params, 0, 1);
        if (count($params) == 1 && is_callable($params[0])) {
            $query = new Query;
            $params[0]($query);
            array_push($join, $query);
        } elseif (count($params) > 1) {
            $query = new Query;
            call_user_func_array(array($query, "on"), $params);
            array_push($join, $query);
        }
        array_push($this->joins, $join);
        return $this;
    }
    public function on()
    {
        call_user_func_array(array($this, "whereColumn"), func_get_args());
        return $this;
    }
    public function orOn()
    {
        call_user_func_array(array($this, "orWhereColumn"), func_get_args());
        return $this;
    }

    public function join()
    {
        return $this->allJoin('JOIN', func_get_args());
    }
    public function leftJoin()
    {
        return $this->allJoin('LEFT JOIN', func_get_args());
    }
    public function rightJoin()
    {
        return $this->allJoin('RIGHT JOIN', func_get_args());
    }
    public function crossJoin($tableName)
    {
        return $this->allJoin('CROSS JOIN', array($tableName));
    }
    public function innerJoin()
    {
        return $this->allJoin('INNER JOIN', func_get_args());
    }
    public function outerJoin()
    {
        return $this->allJoin('OUTER JOIN', func_get_args());
    }

    //-------------------//
    public function union($query)
    {
        array_push($this->unions, $query);
        return $this;
    }
    //-------------------//
    public function insert($values)
    {
        $this->type = "insert";
        $this->values = array_merge($this->values, $values);
        return $this;
    }

    public function update($values)
    {
        $this->type = "update";
        $this->values = array_merge($this->values, $values);
        return $this;
    }

    public function decrement($name, $value = 1)
    {
        $this->type = "update";
        $this->values[$name] = array($name, "-", $value);
        return $this;
    }

    public function increment($name, $value = 1)
    {
        $this->type = "update";
        $this->values[$name] = array($name, "+", $value);
        return $this;
    }

    public function delete()
    {
        $this->type = "delete";
        return $this;
    }

    public function truncate()
    {
        $this->type = "delete";
        $this->conditions = array();
        return $this;
    }

    public function sharedLock()
    {
        $this->lock = "LOCK IN SHARE MODE";
        return $this;
    }

    public function lockForUpdate()
    {
        $this->lock = "FOR UPDATE";
        return $this;
    }
    //------------------//
    public function toSQL($options = [])
    {
        // echo "options = ";
        // Util::prettyPrint($options);
        if (gettype($options) === 'boolean') $quoteIdentifier = false;
        else $quoteIdentifier = Util::get($options, 'quoteIdentifier', false);
        $interpreter = new SQL($this, $quoteIdentifier);
        return $interpreter->buildQuery($options);
    }
    public function toOracle($options = [])
    {
        if (gettype($options) === 'boolean') $quoteIdentifier = false;
        else $quoteIdentifier = Util::get($options, 'quoteIdentifier', false);
        $interpreter = new Oracle($this, $quoteIdentifier);
        return $interpreter->buildQuery($options);
    }

    public function toMySQL($options = [])
    {
        if (gettype($options) === 'boolean') $quoteIdentifier = false;
        else $quoteIdentifier = Util::get($options, 'quoteIdentifier', false);
        $interpreter = new MySQL($this, $quoteIdentifier);
        return $interpreter->buildQuery($options);
    }

    public function toPostgreSQL($options = [])
    {
        if (gettype($options) === 'boolean') $quoteIdentifier = false;
        else $quoteIdentifier = Util::get($options, 'quoteIdentifier', false);
        $interpreter = new PostgreSQL($this, $quoteIdentifier);
        return $interpreter->buildQuery($options);
    }
    public function toSQLServer($options = [])
    {
        if (gettype($options) === 'boolean') $quoteIdentifier = false;
        else $quoteIdentifier = Util::get($options, 'quoteIdentifier', false);
        $interpreter = new SQLServer($this, $quoteIdentifier);
        return $interpreter->buildQuery($options);
    }

    public function getSQLParams()
    {
        return $this->sqlParams;
    }

    public function __toString()
    {
        return $this->toSQL();
    }


    /**
     * Check if there is sub serialized query
     * and convert it to query
     */
    protected function rebuildSubQueries($arr)
    {
        if (!gettype($arr) == "array") {
            return $arr;
        }

        foreach ($arr as $key => $value) {
            if (gettype($value) == "array") {

                if (
                    isset($value["type"])
                    && isset($value["tables"])
                    && isset($value["columns"])
                    && isset($value["conditions"])
                    && isset($value["groups"])
                    && isset($value["orders"])
                ) {
                    $class = get_class($this);
                    $arr[$key] = $class::create($value);
                } else {
                    $arr[$key] = $this->rebuildSubQueries($value);
                }
            }
        }
        return $arr;
    }

    public function fill($arr)
    {
        if ($arr !== null) {
            $arr = $this->rebuildSubQueries($arr);
            foreach ($arr as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public static function create($arr)
    {
        $query = new Query;
        $query->fill($arr);
        return $query;
    }

    public function toArray($obj = null)
    {
        if (!isset($obj)) $obj = $this;
        $arr = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($arr as $key => $val) {
            $recursive = !empty($val) && (is_array($val) || is_object($val));
            $val = $recursive ? $this->toArray($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }
}
