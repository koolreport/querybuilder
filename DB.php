<?php 

namespace koolreport\querybuilder;

class DB 
{
    static function table()
    {
        $params = func_get_args();
        $query = new Query();
        call_user_func_array(array($query,"from"),$params);
        return $query;
    }
    static function raw($text,$params=null)
    {
        if($params!=null)
        {
            foreach($params as $value)
            {
                if(gettype($value)=="string")
                {
                    $value = DB::escapeString($value);
                    $text = preg_replace("/\?/", "'$value'", $text, 1);
                }
                else
                {
                    $text = preg_replace("/\?/", $value, $text, 1);
                }
            }    
        }
        return "[{raw}]".$text;
    }
    static function escapeString($string)
    {
        $search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
        $replace = array("\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z");
    
        return str_replace($search, $replace, $string);
    }
    
}