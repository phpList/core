<?php
namespace phpList\helper;

use phpList\phpList;
use phpList\Config;

class Database
{
    private static $_instance;

    /* @var $db \PDO */
    private $db;
    private $last_query;
    private $query_count = 0;

    private function __construct()
    {
        $this->connect();
    }

    /**
     * Get an instance of this class
     * @return Database
     */
    public static function instance()
    {
        if (!Database::$_instance instanceof self) {
            Database::$_instance = new self();
        }
        return Database::$_instance;
    }

    /**
     * Connect to the database
     * @throws \Exception
     */
    private function connect()
    {
        if(!class_exists('PDO')){
            throw new \Exception('Fatal Error: PDO is not supported in your PHP, recompile and try again.');
        }

        try {
            $this->db = new \PDO(
                Config::DATABASE_DSN,
                Config::DATABASE_USER,
                Config::DATABASE_PASSWORD
            );
        } catch (PDOException $e) {
            throw new \Exception('Cannot connect to Database, please check your configuration. ' . $e->getCampaign());
        }
        //TODO: check compatibility with other db engines
        $this->db->query('SET NAMES \'utf8\'');

        $this->last_query = null;
    }

    /**
     * Generate an error message and write it to screen or console and log
     */
    public function error()
    {
        $error = 'Database error ' . $this->db->errorCode() . ' while doing query ' . $this->last_query . ' ' . $this->db->errorInfo()[2];
        if (Config::get('commandline', false) === false) {
            Output::output($error);
        } else {
            Output::cl_output($error);
        }
        Logger::logEvent('Database error: ' . $this->db->errorInfo()[2]);
    }

    /**
     * Create prepared statement
     * @param $query
     * @return \PDOStatement
     */
    public function prepare($query){
        return $this->db->prepare($query);
    }

    /**
     * Query the database
     * if ignore is true, possible failure will not be notified.
     * @param $query
     * @param bool $ignore
     * @return null|\PDOStatement
     */
    public function query($query, $ignore = false)
    {
        $this->last_query = $result = null;

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

        try{
            $result = $this->db->query($query);
        }catch (\PDOException $e){
            if (!$ignore) {
                Logger::logEvent("Sql error in $query");
                Output::cl_output('Sql error ' . $query);
            }
        }

        # dbg($query);
        $this->query_count++;

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

    /**
     * Close database connection
     */
    public function close()
    {
        $this->db = null;
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

    //TODO: move to logging class or something
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

    /**
     * Execute query and print statement
     * @param $query
     * @param int $ignore
     * @return null|\PDOStatement
     */
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
     * @return int
     */
    public function insertedId()
    {
        return $this->db->lastInsertId();
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

    /**
     * escape a string - should be depricated in favour of prepared statements
     * @param $text
     * @return string
     */
    public function sqlEscape($text)
    {
        return $this->db->quote($text);
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

    /**
     * Get the number of queries run until now
     * @return int
     */
    public function getQueryCount()
    {
        return $this->query_count;
    }

    /**
     * Get last executed query
     * @return mixed
     */
    public function getLastQuery()
    {
        return $this->last_query;
    }

    /**
     * @param array (tablename => columnname)
     * @param int|string $id
     * @return int
     */
    public function deleteFromArray($tables, $id)
    {
        $query = 'DELETE FROM %s WHERE %s = ' . ((is_string($id)) ? '"%s"' : '%d');
        $count = 0;
        foreach($tables as $table => $column){
            $result = $this->db->query(sprintf($query, $table, $column, $id));
            $count += $result->rowCount();
        }
        return $count;
    }
}