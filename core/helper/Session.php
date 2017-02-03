<?php
namespace phpList\helper;

use phpList\Config;
use phpList\phpList;

class Session
{
    public function __construct()
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
                [&$mysql_handler, 'open'],
                [&$mysql_handler, 'close'],
                [&$mysql_handler, 'read'],
                [&$mysql_handler, 'write'],
                [&$mysql_handler, 'destroy'],
                [&$mysql_handler, 'gc']
            );
        }

        session_start();
    }

    private function checkMySQLSessionTable()
    {
        if (!phpList::DB()->tableExists(Config::SESSION_TABLENAME)) {
            phpList::DB()->createTableInDB(
                Config::SESSION_TABLENAME,
                [
                    'sessionid' => ['CHAR(32) NOT NULL PRIMARY KEY', ''],
                    'lastactive' => ['INTEGER NOT NULL', ''],
                    'data' => ['LONGTEXT', ''],
                ]
            );
        }
    }
}

class MySQLSessionHandler implements \SessionHandlerInterface
{
    /**
     * Close the session
     *
     * @link http://php.net/manual/en/sessionhandlerinterafce.close.php
     *
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
     * Destroy a session
     *
     * @link http://php.net/manual/en/sessionhandlerinterafce.destroy.php
     *
     * @param int $session_id The session ID being destroyed.
     *
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
     * Cleanup old sessions
     *
     * @link http://php.net/manual/en/sessionhandlerinterafce.gc.php
     *
     * @param int $maxlifetime <p>
     * Sessions that have not updated for
     * the last maxlifetime seconds will be removed.
     * </p>
     *
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
     * Initialize session
     *
     * @link http://php.net/manual/en/sessionhandlerinterafce.open.php
     *
     * @param string $save_path The path where to store/retrieve the session.
     * @param string $session_id The session id.
     *
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
     * Read session data
     *
     * @link http://php.net/manual/en/sessionhandlerinterafce.read.php
     *
     * @param string $session_id The session id to read data for.
     *
     * @return string <p>
     * Returns an encoded string of the read data.
     * If nothing was read, it must return an empty string.
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function read($session_id)
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT data FROM %s
                WHERE sessionid = \'%s\'',
                Config::SESSION_TABLENAME,
                addslashes($session_id)
            )
        );
        return $result->fetchColumn(0);
    }

    /**
     * Write session data
     *
     * @link http://php.net/manual/en/sessionhandlerinterafce.write.php
     *
     * @param string $session_id The session id.
     * @param string $session_data <p>
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * Please note sessions use an alternative serialization method.
     * </p>
     *
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     */
    public function write($session_id, $session_data)
    {
        $session_id = addslashes($session_id);
        $session_data = addslashes($session_data);

        $session_exists = phpList::DB()->query(
            sprintf(
                'SELECT COUNT(*) FROM  %s
                WHERE sessionid = \'%s\'',
                Config::SESSION_TABLENAME,
                addslashes($session_id)
            )
        )->fetchColumn(0);
        if ($session_exists <= 0) {
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
            if ($retval->rowCount() <= 0) {
                //TODO: correct error handling
                phpList::log()->notice('unable to update session data for session ' . $session_id);
                sendError('unable to update session data for session ' . $session_id);
            }
        }
        return $retval;
    }
}
