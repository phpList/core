<?php
namespace phpList\helper;

use phpList\Config;

class Database
{
    /* @var $db \PDO */
    private $db;
    private $query_count = 0;
    private $query_log = [];

    protected $config;


    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Connect to the database
     * @throws \Exception
     */
    private function connect()
    {
        if (!class_exists('PDO')) {
            throw new \Exception('Fatal Error: PDO is not supported in your PHP, recompile and try again.');
        }

        try {
            $this->db = new \PDO(
                $this->config->get('DATABASE_DSN'),
                $this->config->get('DATABASE_USER'),
                $this->config->get('DATABASE_PASSWORD')
            );
        } catch (\PDOException $e) {
            throw new \Exception('Cannot connect to Database, please check your configuration. ' . $e->getMessage());
        }
        //TODO: check compatibility with other db engines
        $this->db->query('SET NAMES \'utf8\'');

        $this->config->runAfterDBInitialised($this);
    }

    /**
     * Create prepared statement
     * @param $query
     * @return \PDOStatement
     */
    public function prepare($query)
    {
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
        if (DEBUG) {
            # time queries to see how slow they are, so they can
            # be optimized
            Timer::start('query_timer');
        }
        $result = $this->db->query($query);
        $this->query_count++;

        if (DEBUG) {
            # log time queries take
            $this->query_log[] = [
                'query' => $query,
                'time'  => Timer::get('query_timer')->elapsed()
            ];
        }
        return $result;
    }

    /**
     * Get an error message
     * @return array
     */
    public function getErrorMessage()
    {
        $error_info = $this->db->errorInfo();
        return 'Database error ' . $this->db->errorCode() . ' ("' . $error_info[2] . '") while doing query: ' . $this->getLastQuery();
    }

    /**
     * Close database connection
     */
    public function close()
    {
        $this->db = null;
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
            $result = $this->query(
                sprintf(
                    'SELECT table_name FROM information_schema.tables WHERE table_schema = "%s"',
                    //TODO: change this so it can be used with PDO dsn
                    $this->config->get('DATABASE_NAME')
                )
            );
            while ($col = $result->fetchColumn(0)) {
                $_SESSION['dbtables'][] = $col;
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
            $result = $this->query('SHOW columns FROM ' . $table);
            while ($col = $result->fetchColumn(0)) {
                if ($col == $column) {
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
        return $this->tableExists($table) || $this->tableExists($this->config->getTableName($table));
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
     * @note Duplicate key errors can be hidden by this method; commenting out
     * the return statement can reveal useful error messages
     */
    public function getLastQuery()
    {
        // Count the number of queries in the log
        $count = count($this->query_log);
        // Account for the fact array indexes start counting at 0, not 1
        $number = $count - 1;
        // Return the last query string that was executed
        return $this->query_log[$number]['query'];
    }

    /**
     * Get a list of executed queries
     * Will be empty when DEBUG is disabled
     * @return array
     */
    public function getQueryLog()
    {
        return $this->query_log;
    }

    /**
     * @param array (tablename => columnname)
     * @param int|string $id
     */
    public function deleteFromArray($tables, $id)
    {
        $query = 'DELETE FROM %s WHERE %s = ' . ((is_string($id)) ? '"%s"' : '%d');
        // Instantiate results var for collecting query outcomes
        $results = array();
        foreach ($tables as $table => $column) {
            $results [] = $this->db->query(sprintf($query, $table, $column, $id));
        }

        return $results;
    }
}
