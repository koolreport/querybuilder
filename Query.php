<?php

namespace koolreport\querybuilder;

class Query
{
    public $type = "select";//"update","delete","insert"
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

    public function __construct()
    {
        $this->tables = array();        
        $this->columns = array();
        $this->conditions = array();
        $this->orders = array();
        $this->groups = array();
        $this->having = null;
        $this->joins = array();
        $this->unions = array();
        $this->values = array();
    }

    public function distinct()
    {
        $this->distinct = true;
        return $this;
    }

    public function from()
    {
        $params = func_get_args();
        if(count($params)>1)
        {
            $this->tables = $params;
        }
        elseif(gettype($params[0])=="string")
        {
            $this->tables = $params;
        }
        elseif(gettype($params[0])=="array")
        {
            foreach($params[0] as $key=>$value)
            {
                if(gettype($value)=="string")
                {
                    array_push($this->tables,$value);
                }
                elseif(is_callable($value))
                {
                    $query = new Query;
                    $value($query);
                    array_push($this->tables,array($query,$key));
                }
            }    
        }
        return $this;
    }

    public function select()
    {
        $params = func_get_args();
        foreach($params as $columnName)
        {
            array_push($this->columns,array($columnName));
        }
        return $this;
    }
    public function selectRaw($text,$params=array())
    {
        array_push($this->columns,array(DB::raw($text,$params)));
        return $this;
    }

    public function addSelect()
    {
        call_user_func_array(array($this,"select"),func_get_args());
        return $this;
    }
    public function addSelectRaw($text,$params=array())
    {
        return $this->selectRaw($text,$params);
    }

    protected function aggregate($method,$params)
    {
        foreach($params as $name)
        {
            array_push($this->columns,array(array($method,$name)));
        }
        return $this;
    }

    public function alias($name)
    {
        $index = count($this->columns)-1;
        if($index>-1)
        {
            array_push($this->columns[$index],$name);
        }
        return $this;
    }


    public function count()
    {
        $params = func_get_args();
        if(count($params)==0)
        {
            $params = array("1");
        }
        return $this->aggregate("COUNT",$params);
    }

    public function sum()
    {
        return $this->aggregate("SUM",func_get_args());
    }
    public function avg()
    {
        return $this->aggregate("AVG",func_get_args());
    }
    public function max()
    {
        return $this->aggregate("MAX",func_get_args());
    }
    public function min()
    {
        return $this->aggregate("MIN",func_get_args());
    }



    protected function andCondition()
    {
        if(count($this->conditions)>0)
        {
            array_push($this->conditions,"AND");
        }
        return $this;
    }

    protected function orCondition()
    {
        if(count($this->conditions)>0)
        {
            array_push($this->conditions,"OR");
        }
        return $this;
    }

    protected function pushStandardCondition($params)
    {
        if($params[2]===null && $params[1]==="=")
        {
            $params[1] = "IS";
        }
        array_push($this->conditions,$params);
    }


    public function where()
    {
        $params = func_get_args();
        switch(count($params))
        {
            case 1:
                if(gettype($params[0])=="array")
                {
                    $query = new Query;
                    foreach($params[0] as $where)
                    {
                        call_user_func_array(array($query,"where"),$where);
                    }
                    $this->andCondition();
                    array_push($this->conditions,$query);                        
                }
                elseif(is_callable($params[0]))
                {
                    $query = new Query;
                    $params[0]($query);
                    $this->andCondition();
                    array_push($this->conditions,$query);                        
                }
            break;
            case 2:
                $this->where($params[0],"=",$params[1]);
                break;
            case 3:
                $this->andCondition();
                $this->pushStandardCondition($params);
            break;            
        }
        return $this;
    }
    public function orWhere()
    {
        $params = func_get_args();
        switch(count($params))
        {
            case 1:
                if(gettype($params[0])=="array")
                {
                    $query = new Query;
                    foreach($params[0] as $where)
                    {
                        call_user_func_array(array($query,"where"),$where);
                    }
                    $this->orCondition();
                    array_push($this->conditions,$query);                        
                }
                elseif(is_callable($params[0]))
                {
                    $query = new Query;
                    $params[0]($query);
                    $this->orCondition();
                    array_push($this->conditions,$query);                        
                }
            break;
            case 2:
                $this->orWhere($params[0],"=",$params[1]);
            break;
            case 3:
                $this->orCondition();
                $this->pushStandardCondition($params);
            break;            
        }
        return $this;
    }

    //Null
    public function whereNull($name)
    {
        return $this->where($name,'IS',null);
    }
    public function whereNotNull($name)
    {
        return $this->where($name,'IS NOT',null);
    }

    //In
    public function whereIn($name,$array)
    {
        return $this->where($name,'IN',$array);
    }
    public function whereNotIn()
    {
        return $this->where($name,'NOT IN',$array);
    }

    //Between
    public function whereBetween($name,$array)
    {
        return $this->where(array(
            array($name,">=",$array[0]),
            array($name,"<=",$array[1])
        ));
    }
    public function whereNotBetween($name,$array)
    {
        $this->where(function($query) use ($name,$array){
            $query->where($name,"<",$array[0])
            ->orWhere($name,">",$array[1]);
        });
        return $this;
    }

    //Datetime

    protected function whereFunction($name,$params)
    {
        $c = count($params);
        if($c==1)
        {
            return $this->where("$name($params[0])","=",date("Y-m-d"));
        }
        elseif($c==2)
        {
            return $this->where("$name($params[0])","=",$params[1]);
        }
        elseif($c>2)
        {
            return $this->where("$name($params[0])",$params[1],$params[2]);
        }
        return $this;
    }

    public function whereDate()
    {
        return $this->whereFunction("DATE",func_get_args());
    }
    public function whereDay()
    {
        return $this->whereFunction("DAY",func_get_args());
    }
    public function whereMonth()
    {
        return $this->whereFunction("MONTH",func_get_args());
    }
    public function whereYear()
    {
        return $this->whereFunction("YEAR",func_get_args());    
    }
    public function whereTime()
    {
        return $this->whereFunction("TIME",func_get_args());
    }

    //Column
    public function whereColumn()
    {
        $params = func_get_args();
        switch(count($params))
        {
            case 1:
                if(gettype($params[0])=="array")
                {
                    $query = new Query;
                    foreach($params[0] as $where)
                    {
                        call_user_func_array(array($query,"whereColumn"),$where);
                    }
                    $this->andCondition();
                    array_push($this->conditions,$query);                        
                }
            break;
            case 2:
                $this->whereColumn($params[0],"=",$params[1]);
            break;
            case 3:
                $this->andCondition();
                $params[2] = "[{colName}]".$params[2]; 
                array_push($this->conditions,$params);
            break;
        }
        return $this;
    }

    public function orWhereColumn()
    {
        $params = func_get_args();
        switch(count($params))
        {
            case 1:
                if(gettype($params[0])=="array")
                {
                    $query = new Query;
                    foreach($params[0] as $where)
                    {
                        call_user_func_array(array($query,"orWhereColumn"),$where);
                    }
                    $this->orCondition();
                    array_push($this->conditions,$query);                   
                }
            break;
            case 2:
                $this->orWhereColumn($params[0],"=",$params[1]);
            break;
            case 3:
                $this->orCondition();
                $params[2] = "[{colName}]".$params[2]; 
                array_push($this->conditions,$params);
            break;
        }
        return $this;
    }
    

    //Exists
    public function whereExists($table)
    {
        if(is_callable($table))
        {
            $query = new Query;
            $table($query);
            $this->andCondition();
            array_push($this->conditions,array("[{exists}]",$query));
        }
        else
        {
            throw new \Exception("whereExists() required function as parameter");
        }
        return $this;
    }
    //Raw
    public function whereRaw($raw,$params=null)
    {
        $this->andCondition();
        array_push($this->conditions,array("[{raw}]",$raw));
        return $this;
    }
    public function orWhereRaw($raw)
    {
        $this->orCondition();
        array_push($this->conditions,array("[{raw}]",$raw));
        return $this;
    }
    

    //------------------//
    public function orderBy()
    {
        $params = func_get_args();
        if(count($params)==1)
        {
            if(gettype($params[0])=="array")
            {
                foreach($params[0] as $order)
                {
                    call_user_func_array(array($this,"orderBy"),$order);
                }
            }
            else
            {
                $this->orderBy($params[0],'asc');
            }
        }
        elseif(count($params)>1)
        {
            array_push($this->orders,$params);
        }
        return $this;
    }

    public function orderByRaw($raw)
    {
        array_push($this->orders,array("[{raw}]",$raw));
        return $this;
    }

    public function latest($name='created_at')
    {
        return $this->orderBy($name,'desc');
    }

    public function oldest($name='created_at')
    {
        return $this->orderBy($name,'asc');
    }

    //--------------------//
    public function groupBy()
    {
        $params = func_get_args();
        foreach($params as $group)
        {
            if(!in_array($group,$this->groups))
            {
                array_push($this->groups,$group);
            }
        }
        return $this;
    }
    public function having()
    {
        $params = func_get_args();

        if(!$this->having)
        {
            $this->having = new Query;
        }
        call_user_func_array(array($this->having,"where"),$params);
        return $this;
    }

    public function orHaving()
    {
        $params = func_get_args();
        if(!$this->having)
        {
            $this->having = new Query;
        }

        call_user_func_array(array($this->having,"orWhere"),$params);
        return $this;
    }

    public function havingRaw($raw)
    {
        if(!$this->having)
        {
            $this->having = new Query;
        }
        $this->having->whereRaw($raw);
        return $this;        
    }

    public function orHavingRaw($raw)
    {
        if(!$this->having)
        {
            $this->having = new Query;
        }
        $this->having->orWhereRaw($raw);
        return $this;        
    }

    //---------------//
    public function skip($number)
    {
        return $this->offset($number);
    }
    public function offset($number)
    {
        $this->offset = $number;
        return $this;
    }

    public function limit($number)
    {
        $this->limit = $number;
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
    public function when($condition,$trueExecution,$falseExecution=null)
    {
        if($condition)
        {
            if(is_callable($trueExecution))
            {
                $trueExecution($this);
            }
        }
        else
        {
            if(is_callable($falseExecution))
            {
                $falseExecution($this);
            }
        }
        return $this;
    }

    public function branch($value,$array)
    {
        if(isset($array[$value]) && is_callable($array[$value]))
        {
            $array[$value]($this);
        }
        return $this;
    }

    //---------------//
    protected function allJoin($method,$params)
    {
        $join = array($method,$params[0]);
        array_splice($params,0,1);
        if(count($params)==1 && is_callable($params[0]))
        {
            $query = new Query;
            $params[0]($query);
            array_push($join,$query);
        }
        elseif(count($params)>1)
        {
            $query = new Query;
            call_user_func_array(array($query,"on"),$params);
            array_push($join,$query);    
        }
        array_push($this->joins,$join);
        return $this;
    }
    public function on()
    {
        call_user_func_array(array($this,"whereColumn"),func_get_args());
        return $this;
    }
    public function orOn()
    {
        call_user_func_array(array($this,"orWhereColumn"),func_get_args());
        return $this;
    }

    public function join()
    {
        return $this->allJoin('JOIN',func_get_args());
    }
    public function leftJoin()
    {
        return $this->allJoin('LEFT JOIN',func_get_args());
    }
    public function rightJoin()
    {
        return $this->allJoin('RIGHT JOIN',func_get_args());
    }
    public function crossJoin($tableName)
    {
        return $this->allJoin('CROSS JOIN',array($tableName));
    }
    public function innerJoin()
    {
        return $this->allJoin('INNER JOIN',func_get_args());
    }
    public function outerJoin()
    {
        return $this->allJoin('OUTER JOIN',func_get_args());
    }

    //-------------------//
    public function union($query)
    {
        array_push($this->unions,$query);
        return $this;
    }
    //-------------------//
    public function insert($values)
    {
        $this->type = "insert";
        $this->values = array_merge($this->values,$values);
        return $this;
    }

    public function update($values)
    {
        $this->type = "update";
        $this->values = array_merge($this->values,$values);
        return $this;
    }

    public function decrement($name,$value=1)
    {
        $this->type = "update";
        $this->values[$name] = array($name,"-",$value);
        return $this;
    }

    public function increment($name,$value=1)
    {
        $this->type = "update";
        $this->values[$name] = array($name,"+",$value);
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
    public function toSQL()
    {
        $interpreter = new SQL($this);
        return $interpreter->buildQuery();
    }
    public function toMySQL()
    {
        $interpreter = new MySQL($this);
        return $interpreter->buildQuery();
    }
    public function toPostgreSQL()
    {
        $interpreter = new PostgreSQL($this);
        return $interpreter->buildQuery();
    }
    public function toSQLServer()
    {
        $interpreter = new PostgreSQL($this);
        return $interpreter->buildQuery();
    }

    public function __toString()
    {
        return $this->toSQL();
    }
    
}