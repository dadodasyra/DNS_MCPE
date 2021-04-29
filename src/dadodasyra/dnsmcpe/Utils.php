<?php


namespace dadodasyra\dnsmcpe;


class Utils
{
    public static function parsemysql(string $query, array $listargs) : string
    {
        foreach($listargs as $k => $arg){
            $k++;
            foreach(["(", ")", "'", "\"", "-"] as $search){
                $arg = self::replace($search, "\\".$search, $arg);
            }

            $query = str_replace("{args$k}", "$arg", $query);
        }

        return $query;
    }

    public static function replace(string $search, string $replace, string $str) : string
    {
        $position = strpos($str, $search);
        if($position !== false && !self::contains(substr($str, $position-1, $position), "\\")){
            $str = str_replace($search, $replace, $str);
            if(self::contains($str, $search)) $str = self::replace($search, $replace, $str);
        }

        return $str;
    }

    public static function contains(string $str, string $search) : bool
    {
        if(strpos($str, $search) !== false){
            return true;
        } else {
            return false;
        }
    }

}