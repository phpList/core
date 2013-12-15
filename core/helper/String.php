<?php
/**
 * User: Sander
 * Date: 15/12/13
 */

namespace phpList;

/**
 * Class StringFunctions
 * Class containing string helper functions
 * @package phpList
 */
class String  {
    /**
     * Wrapper function for phpList::DB()->Sql_Escape();
     * @param string $var
     * @return string sqlEscaped string
     */
    public static function sqlEscape($var){
        return phpList::DB()->Sql_Escape($var);
    }

    /**
     * Normalize text
     * @param string $var
     * @return string normalized var
     */
    public static function normalize($var) {
        $var = str_replace(" ","_",$var);
        $var = str_replace(";","",$var);
        return $var;
    }

    /**
     * Clean the input string
     * @param string $value
     * @return string
     */
    public static function clean ($value) {
        $value = trim($value);
        $value = preg_replace("/\r/","",$value);
        $value = preg_replace("/\n/","",$value);
        $value = str_replace('"',"&quot;",$value);
        $value = str_replace("'","&rsquo;",$value);
        $value = str_replace("`","&lsquo;",$value);
        $value = stripslashes($value);
        return $value;
    }
} 