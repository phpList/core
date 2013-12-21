<?php
/**
 * User: SaWey
 * Date: 15/12/13
 */

namespace phpList;


class Session
{
    function __construct()
    {
        if (Config::SESSION_STORE == 'mysql') {
            //TODO: maybe better to only use this function once, when installing?
            $this->checkMySQLSessionTable();

            $mysql_handler = new MySQLSessionHandler();
            /*
             * TODO: would it be favorable to check the php version
             * and give the handler object to session_set_save_handler?
             */
            session_set_save_handler(
                array(&$mysql_handler, 'open'),
                array(&$mysql_handler, 'close'),
                array(&$mysql_handler, 'read'),
                array(&$mysql_handler, 'write'),
                array(&$mysql_handler, 'destroy'),
                array(&$mysql_handler, 'gc')
            );
        }

        session_start();
    }

    private function checkMySQLSessionTable()
    {
        if (!phpList::DB()->tableExists(Config::SESSION_TABLENAME)) {
            phpList::DB()->createTableInDB(
                Config::SESSION_TABLENAME,
                array(
                    'sessionid' => array('CHAR(32) NOT NULL PRIMARY KEY', ''),
                    'lastactive' => array('INTEGER NOT NULL', ''),
                    'data' => array('LONGTEXT', ''),
                )
            );
        }
    }
}

//PHP >= 5.4.0 can use a SessionHandlerInerface implementation
class MySQLSessionHandler /*implements \SessionHandlerInterface*/
{
    /**
     * PHP >= 5.4.0<br/>
     * Close the session
     * @link http://php.net/manual/en/sessionhandlerinterafce.close.php
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function close()
    {
        return true;
    }

    /**
     * PHP >= 5.4.0<br/>
     * Destroy a session
     * @link http://php.net/manual/en/sessionhandlerinterafce.destroy.php
     * @param int $session_id The session ID being destroyed.
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function destroy($session_id)
    {
        $retval = phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE sessionid = \'%s\'',
                Config::SESSION_TABLENAME,
                addslashes($session_id)
            )
        );
        return $retval;
    }

    /**
     * PHP >= 5.4.0<br/>
     * Cleanup old sessions
     * @link http://php.net/manual/en/sessionhandlerinterafce.gc.php
     * @param int $maxlifetime <p>
     * Sessions that have not updated for
     * the last maxlifetime seconds will be removed.
     * </p>
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function gc($maxlifetime)
    {
        $cutoff_time = time() - $maxlifetime;
        $retval = phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE lastactive < %s',
                Config::SESSION_TABLENAME,
                $cutoff_time
            )
        );
        return $retval;
    }

    /**
     * PHP >= 5.4.0<br/>
     * Initialize session
     * @link http://php.net/manual/en/sessionhandlerinterafce.open.php
     * @param string $save_path The path where to store/retrieve the session.
     * @param string $session_id The session id.
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function open($save_path, $session_id)
    {
        return true;
    }

    /**
     * PHP >= 5.4.0<br/>
     * Read session data
     * @link http://php.net/manual/en/sessionhandlerinterafce.read.php
     * @param string $session_id The session id to read data for.
     * @return string <p>
     * Returns an encoded string of the read data.
     * If nothing was read, it must return an empty string.
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function read($session_id)
    {
        $session_data_req = phpList::DB()->query(
            sprintf(
                'SELECT data FROM %s
                WHERE sessionid = \'%s\'',
                Config::SESSION_TABLENAME,
                addslashes($session_id)
            )
        );
        $data = phpList::DB()->fetchRow($session_data_req);
        return $data[0];
        /*if (phpList::DB()->Sql_Affected_Rows() == 1) {

            $data = phpList::DB()->Sql_Fetch_Row($session_data_req);
            return $data[0];
        } else {
            return '';
        }*/
    }

    /**
     * PHP >= 5.4.0<br/>
     * Write session data
     * @link http://php.net/manual/en/sessionhandlerinterafce.write.php
     * @param string $session_id The session id.
     * @param string $session_data <p>
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * Please note sessions use an alternative serialization method.
     * </p>
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function write($session_id, $session_data)
    {
        $session_id = addslashes($session_id);
        $session_data = addslashes($session_data);

        $session_exists = phpList::DB()->fetchRowQuery(
            sprintf(
                'SELECT COUNT(*) FROM  %s
                WHERE sessionid = \'%s\'',
                Config::SESSION_TABLENAME,
                addslashes($session_id)
            )
        );
        if ($session_exists[0] == 0) {
            $retval = phpList::DB()->query(
                sprintf(
                    'INSERT INTO %s (sessionid,lastactive,data)
                    VALUES("%s",UNIX_TIMESTAMP(NOW()),"%s")',
                    Config::SESSION_TABLENAME,
                    $session_id,
                    $session_data
                )
            );
        } else {
            $retval = phpList::DB()->query(
                sprintf(
                    'UPDATE %s
                    SET data = "%s", lastactive = UNIX_TIMESTAMP(NOW())
                    WHERE sessionid = "%s"',
                    Config::SESSION_TABLENAME,
                    $session_id,
                    $session_data
                )
            );
            if (phpList::DB()->affectedRows() < 0) {
                //TODO: correct logging/error handling
                logError('unable to update session data for session ' . $session_id);
                sendError('unable to update session data for session ' . $session_id);
            }
        }
        return $retval;
    }
}