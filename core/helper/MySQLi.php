<?php
namespace phpList;

class MySQLi implements IDatabase
{
    private static $_instance;

    /* @var $db /mysqli */
    private $db;
    private $last_query;
    private $query_count = 0;

    private function __construct()
    {
        $this->connect();
    }

    public static function instance()
    {
        if (!MySQLi::$_instance instanceof self) {
            MySQLi::$_instance = new self();
        }
        return MySQLi::$_instance;
    }

    private function connect()
    {
        if (!function_exists('mysqli_connect')) {
            throw new \Exception('Fatal Error: MySQLi is not supported in your PHP, recompile and try again.');
        }

        $this->db = new \mysqli(
            Config::DATABASE_HOST,
            Config::DATABASE_USER,
            Config::DATABASE_PASSWORD,
            Config::DATABASE_NAME
        );

        if ($this->db->connect_error) {
            throw new \Exception('Cannot connect to Database, please check your configuration');
        }

        $this->db->query('SET NAMES \'utf8\'');
        $this->last_query = null;
    }

    function hasError()
    {
        return $this->db->errno;
    }

    //TODO: convert if needed
    function error()
    {
        if (Config::get('commandline', false) !== false) {
            /*
                output('DB error'.$errno);
                print debug_print_backtrace();
            */
            return '<div id="dberror">Database error ' . $this->db->errno . ' while doing query ' . $this->last_query . ' ' . $this->db->error . '</div>';
        } else {
            cl_output(
                'Database error ' . $this->db->errno . ' while doing query ' . $this->last_query . ' ' . $this->db->error
            );
        }
        if (function_exists('logevent')) {
            logevent('Database error: ' . $this->db->error);
        }

        #  return "<table class="x" border=1><tr><td class=\"error\">Database Error</td></tr><tr><td><!--$errno: -->$msg</td></tr></table>";
    }

    function checkError()
    {
        if ($this->db->connect_errno) {
            /*if (isset($GLOBALS['plugins']) && is_array($GLOBALS['plugins'])) {
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processDBerror($this->_db->connect_errno);
                }
            }*/
            switch ($this->db->connect_errno) {
                case 1049: # unknown database
                    Fatal_Error('Unknown database, cannot continue');
                    exit;
                case 1045: # access denied
                    Fatal_Error(
                        'Cannot connect to database, access denied. Please check your configuration or contact the administrator.'
                    );
                    exit;
                case 2002:
                    Fatal_Error(
                        'Cannot connect to database, Sql server is not running. Please check your configuration or contact the administrator.'
                    );
                    exit;
                case 1040: # too many connections
                    Fatal_Error('Sorry, the server is currently too busy, please try again later.');
                    exit;
                case 2005: # "unknown host"
                    Fatal_Error('Unknown database host to connected to, please check your configuration');
                    exit;
                case 2006: # "gone away"
                    Fatal_Error('Sorry, the server is currently too busy, please try again later.');
                    exit;
                case 0:
                    break;
                default:
                    Fatal_Error('Cannot connect to Database, please check your configuration');
            }
            return true;
        }
        return false;
    }

    function query($query, $ignore = false)
    {
        $this->last_query = null;

        if (Config::DEBUG) {

            $this->log($query, '/tmp/queries.log');

            # time queries to see how slow they are, so they can
            # be optimized
            $now = gettimeofday();
            $start = $now['sec'] * 1000000 + $now['usec'];
            $this->last_query = $query;
            /*
            # keep track of queries to see which ones to optimize
            if (function_exists('stripos')) {
                 if (!stripos($query, 'cache')) {
                     $store = $query;
                     $store = preg_replace('/\d+/', 'X', $store);
                     $store = trim($store);
                     $this->_db->query(sprintf('UPDATE querycount SET count = count + 1 WHERE query = "%s" and frontend = %d', $store));
                     if ($this->_db->affected_rows != 2) {
                         $this->_db->query(sprintf('INSERT INTO querycount SET count = count + 1 , query = "%s",phplist = 1', $store));
                     }
                 }
             }*/
        }

        # dbg($query);
        $this->query_count++;

        $result = $this->db->query($query);
        if (!$ignore) {
            if ($this->checkError()) {
                dbg("Sql error in $query");
                cl_output('Sql error ' . $query);
            }
        }
        //TODO: make usable
        /*
        if (Config::DEBUG) {
            # log time queries take
            $now = gettimeofday();
            $end = $now["sec"] * 1000000 + $now["usec"];
            $elapsed = $end - $start;
            if ($elapsed > 300000) {
                $query = substr($query, 0, 200);
                sqllog(' [' . $elapsed . '] ' . $query, "/tmp/phplist-sqltimer.log");
            } else {
                #      sqllog(' ['.$elapsed.'] '.$query,"/tmp/phplist-sqltimer.log");
            }
        }*/
        return $result;
    }

    function close()
    {
        $this->db->close();
    }

    function queryParams($query, $params, $ignore = 0)
    {
        #  dbg($query);
        #  dbg($params);

        if (!is_array($params)) {
            $params = Array($params);
        }

        foreach ($params as $index => $par) {
            $qmark = strpos($query, '?');
            if ($qmark === false) {
                dbg('Error, more parameters than placeholders');
            } else {
                ## first replace the ? with some other placeholder, in case the parameters contain ? themselves
                $query = substr($query, 0, $qmark) . '"PARAM' . $index . 'MARAP"' . substr($query, $qmark + 1);
            }
        }
        #  dbg($query);

        foreach ($params as $index => $par) {
            if (is_numeric($par)) {
                $query = str_replace('"PARAM' . $index . 'MARAP"', sql_escape($par), $query);
            } else {
                $query = str_replace('PARAM' . $index . 'MARAP', sql_escape($par), $query);
            }
        }

        #  dbg($query);
        #  print $query."<br/>";
        return $this->query($query, $ignore);
    }

    function log($msg, $logfile = "")
    {
        if (!$logfile) {
            return;
        }
        $fp = @fopen($logfile, 'a');
        $line = '[' . date('d M Y, H:i:s') . '] ' . getenv('REQUEST_URI') . '(' . $this->query_count . ") $msg \n";
        @fwrite($fp, $line);
        @fclose($fp);
    }

    function verboseQuery($query, $ignore = 0)
    {
        if (Config::DEBUG) {
            print "<b>$query</b><br>\n";
        }
        flush();
        if (Config::get('commandline')) {
            ob_end_clean();
            print "Sql: $query\n";
            ob_start();
        }
        return $this->query($query, $ignore);
    }

    /**
     * @param $result mysqli_result
     * @return array
     */
    function fetchArray($result)
    {
        return $result->fetch_array();
    }

    /**
     * @param $result mysqli_result
     * @return mixed
     */
    function fetchAssoc($result)
    {
        return $result->fetch_assoc();
    }

    /**
     * @param $result mysqli_result
     * @return mixed
     */
    function fetchRow($result)
    {
        return $result->fetch_row();
    }

    function fetchRowQuery($query, $ignore = 0)
    {
        $req = $this->query($query, $ignore);
        return $this->fetchRow($req);
    }

    function fetchArrayQuery($query, $ignore = 0)
    {
        $req = $this->query($query, $ignore);
        return $this->fetchArray($req);
    }

    function fetchAssocQuery($query, $ignore = 0)
    {
        $req = $this->query($query, $ignore);
        return $this->fetchAssoc($req);
    }

    function affectedRows()
    {
        return $this->db->affected_rows;
    }

    /**
     * @param $result mysqli_result
     */
    function numRows($result)
    {
        return $result->num_rows;
    }

    function insertedId()
    {
        return $this->db->insert_id;
    }

    /**
     * @param $result mysqli_result
     * @param $index int
     * @param $column int
     * @return mixed
     */
    function result($result, $index, $column)
    {
        $result->data_seek($index);
        $array = $result->fetch_array();
        return $array[$column];
    }

    /**
     * @param $result mysqli_result
     */
    function freeResult($result)
    {
        $result->free();
    }

    function tableExists($table, $refresh = 0)
    {
        ## table is the full table name including the prefix
        if (!empty($_GET['pi']) || $refresh || !isset($_SESSION) || !isset($_SESSION['dbtables']) || !is_array(
                $_SESSION['dbtables']
            )
        ) {
            $_SESSION['dbtables'] = array();

            # need to improve this. http://bugs.mysql.com/bug.php?id=19588
            $req = $this->query(
                sprintf(
                    'SELECT table_name FROM information_schema.tables WHERE table_schema = "%s"',
                    Config::DATABASE_NAME
                )
            );
            while ($row = $this->fetchRow($req)) {
                $_SESSION['dbtables'][] = $row[0];
            }
        }
        return in_array($table, $_SESSION['dbtables']);
    }

    function tableColumnExists($table, $column)
    {
        ## table is the full table name including the prefix
        if ($this->tableExists($table)) {
            # need to improve this. http://bugs.mysql.com/bug.php?id=19588
            $req = $this->query('SHOW columns FROM ' . $table);
            while ($row = $this->fetchRow($req)) {
                if ($row[0] == $column) {
                    return true;
                }
            }
        }
        return false;
    }

    function checkForTable($table)
    {
        ## table is the full table name including the prefix, or the abbreviated one without prefix
        return $this->tableExists($table) || $this->tableExists(Config::getTableName($table));
    }

    function createTable($table)
    {
        ## table is the abbreviated table name one without prefix
        include dirname(__FILE__) . '/structure.php';
        if (!empty($DBstruct[$table]) && is_array($DBstruct[$table])) {
            $this->createTableInDB(Config::getTableName($table), $DBstruct[$table]);
            return true;
        }
        return false;
    }

    function createTableInDB($table, $structure)
    {
        $query = "CREATE TABLE $table (\n";
        while (list($column, $val) = each($structure)) {
            if (preg_match('/index_\d+/', $column)) {
                $query .= 'index ' . $structure[$column][0] . ",";
            } elseif (preg_match('/unique_\d+/', $column)) {
                $query .= 'unique ' . $structure[$column][0] . ",";
            } else {
                $query .= "$column " . $structure[$column][0] . ",";
            }
        }
        # get rid of the last ,
        $query = substr($query, 0, -1);
        $query .= "\n) default character set utf8";
        # submit it to the database
        $res = $this->query($query, 1);
        unset($_SESSION['dbtables']);
    }

    function dropTable($table)
    {
        #  print '<br/>DROP '.$table;
        return $this->db->query('DROP TABLE IF EXISTS ' . $table);
    }

    function sqlEscape($text)
    {
        return $this->db->real_escape_string($text);
    }

    function replaceQuery($table, $values, $pk)
    {

        $query = ' REPLACE INTO ' . $table . ' SET ';
        foreach ($values as $key => $val) {
            if (is_numeric($val) || $val == 'CURRENT_TIMESTAMP') {
                $query .= ' ' . $key . '= ' . $this->sqlEscape($val) . ',';
            } else {
                $query .= ' ' . $key . '="' . $this->sqlEscape($val) . '",';
            }
        }
        $query = substr($query, 0, -1);
        # output($query);
        return $this->query($query);
    }

    function setSearchPath($searchpath)
    {
        return;
    }

    function getQueryCount()
    {
        return $this->query_count;
    }

    function getLastQuery()
    {
        return $this->last_query;
    }

    /**
     * @param array (tablename => columnname)
     * @param int|string $id
     */
    function deleteFromArray($tables, $id)
    {
        $query = 'DELETE FROM %s WHERE %s = ' . (is_string($id)) ? '"%s"' : '%d';
        foreach($tables as $table => $column){
            phpList::DB()->query(sprintf($query, $table, $column, $id));
        }
    }
}