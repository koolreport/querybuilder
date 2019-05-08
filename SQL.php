<?php

namespace koolreport\querybuilder;

class SQL
{
    protected $query;
    protected $indentifierCover=array("`","`");//For table name and column name
    protected $escapeValue = true;    
    public function __construct($query)
    {
        $this->query = $query;
    }
    
    protected function renderValue($value)
    {
        if($value===null)
        {
            return "NULL";
        }
        elseif(gettype($value)==="array")
        {
            for($i=0;$i<count($value);$i++)
            {
                if(gettype($value[$i])=="string")
                {
                    $value[$i] = $this->coverValue($this->escapeString($value[$i]));
                }
            }
            return "(".implode(",",$value).")";    
        }
        elseif(gettype($value)==="string")
        {

            return $this->coverValue($this->escapeString($value));
        }
        elseif(gettype($value)==="boolean")
        {
            return ($value===true)?1:0;
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
    protected function coverIndentifier($name)
    {
        $dot = strpos($name,".");
        if($dot===false)
        {
            return $this->indentifierCover[0].$name.$this->indentifierCover[1];
        }
        else
        {
            $table = substr($name,0,$dot);
            $column = str_replace($table.".","",$name);
            return $this->indentifierCover[0].$table.$this->indentifierCover[1]
                    .".".$this->indentifierCover[0].$column.$this->indentifierCover[1];
        }
    }

    protected function getWhere($conditions)
    {
        $result = "";
        foreach($conditions as $condition)
        {
            if(gettype($condition)=="array")
            {
                switch($condition[0])
                {
                    case "[{exists}]":
                        $class = get_class($this);
                        $object = new $class($condition[1]);
                        $result.="exists( ".$object->buildQuery()." )";
                    break;
                    case "[{raw}]":
                        $result.=$condition[1];
                    break;
                    default:
                        $part = "{key} {operator} {value}";
                        $part = str_replace("{key}",$this->coverIndentifier($condition[0]),$part);
                        $part = str_replace("{operator}",$condition[1],$part);
                        if(gettype($condition[2])=="string" && strpos($condition[2],"[{colName}]")===0)
                        {
                            $part = str_replace("{value}",str_replace("[{colName}]","",$this->coverIndentifier($condition[2])),$part);
                        }
                        else
                        {
                            $part = str_replace("{value}",$this->renderValue($condition[2]),$part);
                        }
                        $result.=$part;
                    break;
                }
            }
            else if(gettype($condition)=="string")
            {
                $result .= " $condition ";
            }
            else if(is_a($condition,'koolreport\querybuilder\Query'))
            {
                $result.="(".$this->getWhere($condition->conditions).")";
            }
        }
        return $result;
    }

    protected function getFrom($tables)
    {
        $array = array();
        foreach($tables as $table)
        {
            if(gettype($table)=="array")
            {
                $class = get_class($this);
                $interpreter = new $class($table[0]);
                array_push($array,"(".$interpreter->buildQuery().") ".$this->coverIndentifier($table[1]));
            }
            else
            {
                array_push($array,$this->coverIndentifier($table));
            }
        }
        return implode(", ",$array);
    }

    protected function getOrderBy($orders)
    {
        $array = array();
        foreach($orders as $order)
        {
            if($order[0]=="[{raw}]")
            {
                array_push($array,$order[1]);
            }
            else
            {
                array_push($array,$this->coverIndentifier($order[0])." ".$order[1]);
            }
        }
        return implode(", ",$array);
    }

    protected function getGroupBy($groups)
    {
        $array = array();
        foreach($groups as $group)
        {
            array_push($array,$this->coverIndentifier($group));
        }
        return implode(", ",$array);
    }

    protected function getHaving($having)
    {
        return $this->getWhere($having->conditions);
    }

    protected function getJoin($joins)
    {
        $array = array();
        foreach($joins as $join)
        {
            $class = get_class($this);
            $object = new $class($join[1]);
            $part = " $join[0] ".$this->coverIndentifier($join[1]);
            if(isset($join[2]))
            {
                $part.=" ON ".$object->getWhere($join[2]->conditions);
            }
            array_push($array,$part);
        }
        return implode(" ",$array);
    }

    protected function getSelect($columns)
    {
        $array = array();
        foreach($columns as $column)
        {
            $part = "";
            if(gettype($column[0])=="array")
            {
                //Aggregate
                if($column[0][0]=="COUNT" && $column[0][1]==1)
                {
                    $part .= "COUNT(1)";    
                }
                else
                {
                    $part .= $column[0][0]."(".$this->coverIndentifier($column[0][1]).")";
                }
            }
            else
            {
                $part .= $this->coverIndentifier($column[0]);
            }
            if(isset($column[1]))
            {
                $part .= " AS ".$this->coverIndentifier($column[1]);
            }
            array_push($array,$part);
        }
        return implode(", ",$array);
    }
    protected function getUnions($unions)
    {
        $res = "";
        $class = get_class($this);
        foreach($unions as $union)
        {
            $interpreter = new $class($union);
            $res.=" UNION (".$interpreter->buildQuery().")";
        }
        return $res;
    }

    protected function getUpdateSet($list)
    {
        $array = array();
        foreach($list as $key=>$value)
        {
            
            $part = "";
            if(gettype($value)=="array")
            {
                $part.=$this->coverIndentifier($key)." = ".$this->coverIndentifier($value[0])." $value[1] $value[2]";
            }
            elseif(gettype($value)=="string")
            {
                $part .= $this->coverIndentifier($key)." = ".$this->coverValue($this->escapeString($value));
            }
            elseif(gettype($value)=="boolean")
            {
                $part .= $this->coverIndentifier($key)." = ". (($value===true)?"1":"0"); 
            }            
            else
            {
                $part .= $this->coverIndentifier($key)." = ".(($value!==null)?$value:"NULL");
            }
            array_push($array,$part);
        }
        return implode(", ",$array);
    }

    protected function getInsertValues($list)
    {
        $keys = array_keys($list);
        $values = array_values($list);
        for($i=0;$i<count($values);$i++)
        {
            if(gettype($values[$i])=="string")
            {
                $values[$i] = $this->coverValue($this->escapeString($values[$i]));
            }
            else if(gettype($values[$i])=="boolean")
            {
                $values[$i] = ($values[$i]===true)?"1":"0"; 
            }            
        }
        for($i=0;$i<count($keys);$i++)
        {
            $keys[$i] = $this->coverIndentifier($keys[$i]);
        }
        
        return ' ('.implode(", ",$keys).')'.' VALUES '.'('.implode(", ",$values).')';
    }

    public function buildQuery()
    {
        $sql="";
        switch($this->query->type)
        {
            case "select":
                $sql .= "SELECT ";
                if($this->query->distinct)
                {
                    $sql.="DISTINCT ";
                }
                if(count($this->query->columns)>0)
                {
                    $sql.=$this->getSelect($this->query->columns);
                }
                else
                {
                    $sql.="*";
                }
                if(count($this->query->tables)>0)
                {
                    $sql.=" FROM ".$this->getFrom($this->query->tables);
                }
                else
                {
                    throw new \Exception("No table available in SQL Query");
                }

                if(count($this->query->joins)>0)
                {
                    $sql.=$this->getJoin($this->query->joins);
                }

                if(count($this->query->conditions)>0)
                {
                    $sql.=" WHERE ".$this->getWhere($this->query->conditions);
                }

                if(count($this->query->groups)>0)
                {
                    $sql.=" GROUP BY ".$this->getGroupBy($this->query->groups);
                }

                if($this->query->having)
                {
                    $sql.=" HAVING ".$this->getHaving($this->query->having);
                }


                if(count($this->query->orders)>0)
                {
                    $sql.=" ORDER BY ".$this->getOrderBy($this->query->orders);
                }

                if($this->query->limit!==null)
                {
                    $sql.=" LIMIT ".$this->query->limit;
                }

                if($this->query->offset!==null)
                {
                    $sql.=" OFFSET ".$this->query->offset;
                }

                if(count($this->query->unions)>0)
                {
                    $sql.=$this->getUnions($this->query->unions);
                }
                if($this->query->lock)
                {
                    $sql.=" ".$this->query->lock;
                }
            break;
            case "update":
                $sql .= "UPDATE ";
                if(count($this->query->tables)==1)
                {
                    $sql .= $this->getFrom(array($this->query->tables[0]));
                }
                elseif(count($this->query->tables)>1)
                {
                    throw new \Exception("Only one table is updated");
                }
                else
                {
                    throw new \Exception("Update query need table specified");
                }
                if(count($this->query->values)>0)
                {
                    $sql .= " SET ".$this->getUpdateSet($this->query->values);
                }
                
                if(count($this->query->conditions)>0)
                {
                    $sql .= " WHERE ".$this->getWhere($this->query->conditions);
                }
            break;
            case "insert":
                $sql .= "INSERT INTO ";
                if(count($this->query->tables)==1)
                {
                    $sql .= $this->getFrom(array($this->query->tables[0]));
                }
                elseif(count($this->query->tables)>1)
                {
                    throw new \Exception("Only one table is inserted");
                }
                else
                {
                    throw new \Exception("Insert query need a table specified");
                }
                if(count($this->query->values)>0)
                {
                    $sql .= $this->getInsertValues($this->query->values);
                }
                if(count($this->query->conditions)>0)
                {
                    $sql .= " WHERE ".$this->getWhere($this->query->conditions);
                }
            break;
            case "delete":
                $sql .= "DELETE FROM ";
                if(count($this->query->tables)==1)
                {
                    $sql .= $this->getFrom(array($this->query->tables[0]));
                }
                elseif(count($this->query->tables)>1)
                {
                    throw new \Exception("Only one table is deleted");
                }
                else
                {
                    throw new \Exception("Delete query need a table specified");
                }
                if(count($this->query->conditions)>0)
                {
                    $sql .= " WHERE ".$this->getWhere($this->query->conditions);
                }
            break;            
        }
        return $sql;
    }

    static function type($query)
    {
        $class = get_called_class();
        $interpreter = new $class($query);
        return $interpreter->buildQuery();
    }
}