<?php
namespace phpList;

class MySQLi implements IDatabase
{
    private static $_instance;

    /* @var $db \mysqli */
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

    public function hasError()
    {
        return $this->db->errno;
    }

    //TODO: convert if needed
    public function error()
    {
        if (Config::get('commandline', false) === false) {
            /*
                output('DB error'.$errno);
                print debug_print_backtrace();
            */
            Output::output('Database error ' . $this->db->errno . ' while doing query ' . $this->last_query . ' ' . $this->db->error);
        } else {
            Output::cl_output(
                'Database error ' . $this->db->errno . ' while doing query ' . $this->last_query . ' ' . $this->db->error
            );
        }
        Logger::logEvent('Database error: ' . $this->db->error);
    }

    public function checkError()
    {
        if ($this->db->connect_errno) {
            /*if (isset($GLOBALS['plugins']) && is_array($GLOBALS['plugins'])) {
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processDBerror($this->_db->connect_errno);
                }
            }*/
            switch ($this->db->connect_errno) {
                case 1049: # unknown database
                    throw new \mysqli_sql_exception('Unknown database, cannot continue');
                case 1045: # access denied
                    throw new \mysqli_sql_exception(
                        'Cannot connect to database, access denied. Please check your configuration or contact the administrator.'
                    );
                case 2002:
                    throw new \mysqli_sql_exception(
                        'Cannot connect to database, Sql server is not running. Please check your configuration or contact the administrator.'
                    );
                case 1040: # too many connections
                    throw new \mysqli_sql_exception('Sorry, the server is currently too busy, please try again later.');
                case 2005: # "unknown host"
                    throw new \mysqli_sql_exception('Unknown database host to connected to, please check your configuration');
                case 2006: # "gone away"
                    throw new \mysqli_sql_exception('Sorry, the server is currently too busy, please try again later.');
                case 0:
                    break;
                default:
                    throw new \mysqli_sql_exception('Cannot connect to Database, please check your configuration');
            }
            return true;
        }
        return false;
    }

    public function query($query, $ignore = false)
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
                Logger::logEvent("Sql error in $query");
                Output::cl_output('Sql error ' . $query);
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

    public function close()
    {
        $this->db->close();
    }

    public function queryParams($query, $params, $ignore = 0)
    {
        #  dbg($query);
        #  dbg($params);

        if (!is_array($params)) {
            $params = Array($params);
        }

        foreach ($params as $index => $par) {
            $qmark = strpos($query, '?');
            if ($qmark === false) {
                Logger::logEvent('Error, more parameters than placeholders');
            } else {
                ## first replace the ? with some other placeholder, in case the parameters contain ? themselves
                $query = substr($query, 0, $qmark) . '"PARAM' . $index . 'MARAP"' . substr($query, $qmark + 1);
            }
        }
        #  dbg($query);

        foreach ($params as $index => $par) {
            if (is_numeric($par)) {
                $query = str_replace('"PARAM' . $index . 'MARAP"', phpList::DB()->sqlEscape($par), $query);
            } else {
                $query = str_replace('PARAM' . $index . 'MARAP', phpList::DB()->sqlEscape($par), $query);
            }
        }

        #  dbg($query);
        #  print $query."<br/>";
        return $this->query($query, $ignore);
    }

    public function log($msg, $logfile = "")
    {
        if (!$logfile) {
            return;
        }
        $fp = @fopen($logfile, 'a');
        $line = '[' . date('d M Y, H:i:s') . '] ' . getenv('REQUEST_URI') . '(' . $this->query_count . ") $msg \n";
        @fwrite($fp, $line);
        @fclose($fp);
    }

    public function verboseQuery($query, $ignore = 0)
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
     * @param $result \mysqli_result
     * @return array
     */
    public function fetchArray($result)
    {
        return $result->fetch_array();
    }

    /**
     * @param $result \mysqli_result
     * @return mixed
     */
    public function fetchAssoc($result)
    {
        return $result->fetch_assoc();
    }

    /**
     * @param $result \mysqli_result
     * @return mixed
     */
    public function fetchRow($result)
    {
        return $result->fetch_row();
    }

    public function fetchRowQuery($query, $ignore = 0)
    {
        $req = $this->query($query, $ignore);
        return $this->fetchRow($req);
    }

    public function fetchArrayQuery($query, $ignore = 0)
    {
        $req = $this->query($query, $ignore);
        return $this->fetchArray($req);
    }

    public function fetchAssocQuery($query, $ignore = 0)
    {
        $req = $this->query($query, $ignore);
        return $this->fetchAssoc($req);
    }

    public function affectedRows()
    {
        return $this->db->affected_rows;
    }

    /**
     * @param $result \mysqli_result
     * @return int
     */
    public function numRows($result)
    {
        return $result->num_rows;
    }

    /**
     * @return int
     */
    public function insertedId()
    {
        return $this->db->insert_id;
    }

    /**
     * @param $result \mysqli_result
     * @param $index int
     * @param $column int
     * @return mixed
     */
    public function result($result, $index, $column)
    {
        $result->data_seek($index);
        $array = $result->fetch_array();
        return $array[$column];
    }

    /**
     * @param $result \mysqli_result
     */
    public function freeResult($result)
    {
        $result->free();
    }

    /**
     * Check if a table exists in the database
     * @param string $table
     * @param int $refresh
     * @return bool
     */
    public function tableExists($table, $refresh = 0)
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

    /**
     * Check if a column exists in the database
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function tableColumnExists($table, $column)
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

    /**
     * @param string $table The full table name including the prefix, or the abbreviated one without prefix
     * @return bool
     */
    public function checkForTable($table)
    {
        return $this->tableExists($table) || $this->tableExists(Config::getTableName($table));
    }

    /**
     * Create a table with given structure in the database
     * structure should be like:
     * array(
     *   'columnName' => array('type', 'comment')
     *   )
     *
     * @param string $table
     * @param array $structure
     */
    public function createTableInDB($table, $structure)
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
        $this->query($query, 1);
    }

    public function dropTable($table)
    {
        #  print '<br/>DROP '.$table;
        return $this->db->query('DROP TABLE IF EXISTS ' . $table);
    }

    public function sqlEscape($text)
    {
        return $this->db->real_escape_string($text);
    }

    public function replaceQuery($table, $values, $pk)
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

    public function setSearchPath($searchpath)
    {
        return;
    }

    public function getQueryCount()
    {
        return $this->query_count;
    }

    public function getLastQuery()
    {
        return $this->last_query;
    }

    /**
     * @param array (tablename => columnname)
     * @param int|string $id
     */
    public function deleteFromArray($tables, $id)
    {
        $query = 'DELETE FROM %s WHERE %s = ' . (is_string($id)) ? '"%s"' : '%d';
        foreach($tables as $table => $column){
            phpList::DB()->query(sprintf($query, $table, $column, $id));
        }
    }
}