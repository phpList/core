<?php
/**
 * User: SaWey
 * Date: 6/12/13
 */
namespace phpList;

interface IDatabase
{
    public static function instance();

    function fetchArrayQuery($query, $ignore = 0);

    function error();

    function dropTable($table);

    function queryParams($query, $params, $ignore = 0);

    /**
     * @param $result \mysqli_result
     * @return array
     */
    function fetchArray($result);

    function fetchRowQuery($query, $ignore = 0);

    /**
     * @param $result \mysqli_result
     */
    function freeResult($result);

    function fetchAssocQuery($query, $ignore = 0);

    function createTableInDB($table, $structure);

    function affectedRows();

    function tableExists($table, $refresh = 0);

    /**
     * @param $result \mysqli_result
     */
    function numRows($result);

    function checkForTable($table);

    function checkError();

    function setSearchPath($searchpath);

    function insertedId();

    /**
     * @param $result \mysqli_result
     * @param $index int
     * @param $column int
     * @return mixed
     */
    function result($result, $index, $column);

    /**
     * @param $result \mysqli_result
     * @return mixed
     */
    function fetchAssoc($result);

    /**
     * @param $result \mysqli_result
     * @return mixed
     */
    function fetchRow($result);

    function tableColumnExists($table, $column);

    function verboseQuery($query, $ignore = 0);

    function hasError();

    function replaceQuery($table, $values, $pk);

    /**
     * @param $query
     * @param int $ignore
     * @return $result \mysqli_result
     */
    function query($query, $ignore = 0);

    function close();

    function sqlEscape($text);

    function getQueryCount();

    function getLastQuery();

    /**
     * @param array (tablename, columnname)
     * @param int|string $id
     */
    function deleteFromArray($array, $id);
}