<?php
/**
 * User: SaWey
 * Date: 6/12/13
 */
namespace phpList;

interface IDatabase
{
    function Sql_Fetch_Array_Query($query, $ignore = 0);

    function Sql_Error();

    function Sql_Drop_Table($table);

    function Sql_Query_Params($query, $params, $ignore = 0);

    /**
     * @param $result \mysqli_result
     * @return array
     */
    function Sql_Fetch_Array($result);

    function Sql_Fetch_Row_Query($query, $ignore = 0);

    /**
     * @param $result \mysqli_result
     */
    function Sql_Free_Result($result);

    function Sql_Fetch_Assoc_Query($query, $ignore = 0);

    function Sql_create_Table($table, $structure);

    function Sql_Affected_Rows();

    function Sql_Table_exists($table, $refresh = 0);

    /**
     * @param $result \mysqli_result
     */
    function Sql_Num_Rows($result);

    function Sql_Check_For_Table($table);

    function Sql_Check_error();

    function Sql_Set_Search_Path($searchpath);

    function Sql_Insert_Id();

    /**
     * @param $result \mysqli_result
     * @param $index int
     * @param $column int
     * @return mixed
     */
    function Sql_Result($result, $index, $column);

    /**
     * @param $result \mysqli_result
     * @return mixed
     */
    function Sql_Fetch_Assoc($result);

    /**
     * @param $result \mysqli_result
     * @return mixed
     */
    function Sql_Fetch_Row($result);

    public static function Instance();

    function createTable($table);

    function Sql_Table_Column_Exists($table, $column);

    function Sql_Verbose_Query($query, $ignore = 0);

    function Sql_has_error();

    function Sql_Replace($table, $values, $pk);

    /**
     * @param $query
     * @param int $ignore
     * @return $result \mysqli_result
     */
    function Sql_Query($query, $ignore = 0);

    function Sql_Close();

    function Sql_Escape($text);

    function getQueryCount();

    function getLastQuery();
}