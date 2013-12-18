<?php
/**
 * User: SaWey
 * Date: 13/12/13
 */

namespace phpList;

class User
{
    public $id;
    public $email;
    public $confirmed;
    public $blacklisted;
    public $optedin;
    public $bouncecount;
    public $entered;
    public $modified;
    public $uniqid;
    public $htmlemail;
    public $subscribepage;
    public $rssfrequency;
    private $password;

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set password and encrypt it
     * For existing users, password will be written to database
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = phpList::encryptPass($password);
        if ($this->id != null) {
            $this->updatePassword();
        }
    }

    public $passwordchanged;
    public $disabled;
    public $extradata;
    public $foreignkey;
    private $attributes;

    public function __construct()
    {
    }

    /**
     * Get user by id
     * @param int $id
     * @return User
     */
    public static function getUser($id)
    {
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('user', true),
                $id
            )
        );
        return User::userFromArray($result);
    }

    /**
     * Get user by email
     * @param string $email
     * @return User
     */
    public static function getUserByEmail($email)
    {
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE email = "%s"',
                Config::getTableName('user', true),
                $email
            )
        );
        return User::userFromArray($result);
    }

    /**
     * Get user object from foreign key value
     * @param $fk string
     * @return User
     */
    public static function getUserByForeignKey($fk)
    {
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE foreignkey = %s',
                Config::getTableName('user', true),
                $fk
            )
        );
        return User::userFromArray($result);
    }

    /**
     * Add user to selected list
     * @param $list_id
     */
    public function addToList($list_id)
    {
        MailingList::addSubscriber($this->id, $list_id);
    }

    /**
     * Write user info to database
     * @return int
     */
    public function save()
    {
        if ($this->id != 0) {
            $this->update();
        } else {
            phpList::DB()->query(
                sprintf(
                    'INSERT INTO %s
                    (email, entered, modified, password, passwordchanged, disabled, htmlemail)
                    VALUES("%s", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, "%s", CURRENT_TIMESTAMP, 0, "%s, 1")',
                    Config::getTableName('user', true),
                    $this->email,
                    $this->password
                )
            );

            $this->id = phpList::DB()->insertedId();
            self::giveUniqueId($this->id);
        }

        return $this->id;
    }

    /**
     * Assign a unique id to a user
     * @param int $user_id
     */
    private static function giveUniqueId($user_id)
    {
        //TODO: make uniqueid a unique field in database
        do {
            $unique_id = md5(uniqid(mt_rand()));
        } while (!phpList::DB()->query(
            sprintf(
                'UPDATE % SET uniqid = "%s"
                WHERE id = %d',
                Config::getTableName('user', true),
                $unique_id,
                $user_id
            )
        ));
    }

    /**
     * Add new user to database
     * @param $email
     * @param $password
     */
    public static function addUser($email, $password)
    {
        $user = new User();
        $user->email = $email;
        $user->setPassword($password);
        $user->save();
    }

    /**
     * Check if email address exists in database
     * @param $email
     * @return bool
     */
    public function emailExists($email)
    {
        $result = Sql_Fetch_Row_Query(
            sprintf(
                'SELECT id FROM %s
                WHERE email = "%s"',
                Config::getTableName('user'),
                $email
            )
        );
        return !empty($result[0]);
    }

    /**
     * Save user info to database
     */
    public function update()
    {
        $query = sprintf(
            'UPDATE %s SET
                email = "%s",
                confirmed = "%s",
                blacklisted = "%s",
                optedin = "%s",
                modified = CURRENT_TIMESTAMP,
                htmlemail = "%s",
                extradata = "%s"',
            Config::getTableName('user', true),
            $this->email,
            $this->confirmed,
            $this->blacklisted,
            $this->optedin,
            $this->htmlemail,
            $this->extradata
        );

        phpList::DB()->query($query);
    }

    /**
     * Remove user from database
     */
    public function delete()
    {
        $query = sprintf(
            'DELETE FROM %s, %s, %s, %s
                                        WHERE userid = %d',
            Config::getTableName('listuser'),
            Config::getTableName('user_attribute', true),
            Config::getTableName('usermessage'),
            Config::getTableName('user_history', true),
            $this->id
        );
        phpList::DB()->query($query);
        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE user = %d',
                Config::getTableName('user_message_bounce', true),
                $this->id
            )
        );
        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE id = %d',
                Config::getTableName('user', true),
                $this->id
            )
        );


        if (phpList::DB()->tableExists(Config::getTableName('user_group'))) {
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM user_group
                    WHERE userid = %d',
                    $this->id
                ),
                1
            );
        }
    }

    public function isMemberOf()
    {
        return MailingList::getListsForUser($this->id);
    }


    /**
     *
     * @param $array
     * @return User
     */
    private static function userFromArray($array)
    {
        $user = new User();
        $user->id = $array['id'];
        $user->email = $array['email'];
        $user->confirmed = $array['confirmed'];
        $user->blacklisted = $array['blacklisted'];
        $user->optedin = $array['optedin'];
        $user->bouncecount = $array['bouncecount'];
        $user->entered = $array['entered'];
        $user->modified = $array['modified'];
        $user->uniqid = $array['uniqid'];
        $user->htmlemail = $array['htmlemail'];
        $user->subscribepage = $array['subscribepage'];
        $user->rssfrequency = $array['rssfrequency'];
        $user->password = $array['password'];
        $user->passwordchanged = $array['passwordchanged'];
        $user->disabled = $array['disabled'];
        $user->extradata = $array['extradata'];
        $user->foreignkey = $array['foreignkey'];
        return $user;
    }

    /**
     * Update password in db
     */
    private function updatePassword()
    {
        $query = sprintf(
            'UPDATE %s
            SET password = "%s", passwordchanged = CURRENT_TIMESTAMP
            WHERE id = %d',
            Config::getTableName('user'),
            $this->password,
            $this->id
        );
        phpList::DB()->query($query);
    }

    public function getAttribute($attribute)
    {
        if (empty($this->attributes)) {
            $this->getAttributes();
        }

        if (!isset($this->attributes[$attribute])) {
            return null;
        } else {
            return $this->attributes[$attribute];
        }
    }

    public function getAttributes()
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT a.id, a.name, ua.value
                FROM %s AS a
                    INNER JOIN %s AS ua
                    ON a.id = ua.attributeid
                WHERE ua.userid = %d
                ORDER BY listorder',
                Config::getTableName('attribute', true),
                Config::getTableName('user_attribute', true),
                $this->id
            )
        );
        while ($row = phpList::DB()->fetchAssoc($result)) {
            $this->attributes[$row['id']] = $row['value'];
            $this->attributes[$row['name']] = $row['name'];
        }
    }

    public function addAttribute($id, $value)
    {
        phpList::DB()->query(
            sprintf(
                'REPLACE INTO %s
                (userid,attributeid,value)
                VALUES(%d,%d,"%s")',
                Config::getTableName('user_attribute'),
                $this->id,
                $id,
                String::sqlEscape($value)
            )
        );
    }

    public static function addHistory($msg, $detail, $user_id)
    {
        if (empty($detail)) { ## ok duplicated, but looks better :-)
            $detail = $msg;
        }

        $sysinfo = "";
        $sysarrays = array_merge($_ENV, $_SERVER);
        //TODO: change this
        if (isset($GLOBALS['userhistory_systeminfo']) && is_array($GLOBALS['userhistory_systeminfo'])) {
            foreach ($GLOBALS['userhistory_systeminfo'] as $key) {
                if (isset($sysarrays[$key])) {
                    $sysinfo .= "\n$key = $sysarrays[$key]";
                }
            }
        } elseif (is_array(Config::$USERHISTORY_SYSTEMINFO)) {
            foreach (Config::$USERHISTORY_SYSTEMINFO as $key) {
                if ($sysarrays[$key]) {
                    $sysinfo .= "\n$key = $sysarrays[$key]";
                }
            }
        } else {
            $default = array('HTTP_USER_AGENT', 'HTTP_REFERER', 'REMOTE_ADDR', 'REQUEST_URI');
            foreach ($sysarrays as $key => $val) {
                if (in_array($key, $default))
                    $sysinfo .= "\n" . strip_tags($key) . ' = ' . htmlspecialchars($val);
            }
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '';
        }
        phpList::DB()->query(
            sprintf(
                'INSERT INTO %s
                (ip,userid,date,summary,detail,systeminfo)
                VALUES("%s",%d,now(),"%s","%s","%s")',
                Config::getTableName('user_history', true),
                $ip,
                $user_id,
                addslashes($msg),
                addslashes(htmlspecialchars($detail)),
                $sysinfo
            )
        );
    }

    /**
     * Get the unique id from a user
     * @param int $user_id
     * @return int unique_id
     */
    public static function getUniqueId($user_id)
    {
        $result = phpList::DB()->fetchRowQuery(
            sprintf(
                'SELECT uniqid FROM %s
                WHERE id = %d',
                Config::getTableName('user', true),
                $user_id
            )
        );
        return $result[0];
    }

    /**
     * Get the number users who's unique id has not been set
     * @return int
     */
    public static function checkUniqueIds()
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT id FROM %s
                WHERE uniqid IS NULL
                OR uniqid = ""',
                Config::getTableName('user', true)
            )
        );
        $num = phpList::DB()->affectedRows();
        if ($num > 0) {
            while ($row = phpList::DB()->fetchRow($result)) {
                self::giveUniqueId($row[0]);
            }
        }
        return $num;
    }

    /**
     * Check if an email addres is blacklisted
     * $immediate specifies if a gracetime is allowed for a last message
     * @param string $email
     * @param bool $immediate
     * @return bool
     */
    public static function isBlackListed($email, $immediate = true)
    {
        //TODO: @Michiel why is there a check on the blacklist table?
        if (!phpList::DB()->tableExists(Config::getTableName('user_blacklist'))) return false;
        if (!$immediate) {
            # allow 5 minutes to send the last message acknowledging unsubscription
            $gracetime = sprintf('%d', Config::BLACKLIST_GRACETIME);
            if (!$gracetime || $gracetime > 15 || $gracetime < 0) {
                $gracetime = 5;
            }
        } else {
            $gracetime = 0;
        }
        $row = phpList::DB()->fetchRowQuery(
            sprintf(
                'SELECT COUNT(email) FROM %s
                WHERE email = "%s"
                AND date_add(added, interval %d minute) < CURRENT_TIMESTAMP)',
                Config::getTableName('user_blacklist'),
                String::sqlEscape($email),
                $gracetime
            )
        );
        return ($row[0] == 0) ? false : true;
    }

    /**
     * Check if the user with given id is blacklisted
     * @param int $user_id
     * @return bool
     */
    public static function isBlackListedID($user_id = 0)
    {
        $user = User::getUser($user_id);
        return ($user == null || $user->blacklisted == 0) ? false : true;
    }

    /**
     * Blacklist a user by his email address
     * @param string $email
     * @param string $reason
     */
    public static function blacklistUser($email, $reason = '')
    {
        $email = addslashes($email);
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET blacklisted = 1
                WHERE email = "%s"',
                Config::getTableName('user', true),
                $email
            )
        );
        #0012262: blacklist only email when email bounces. (not users): Function split so email can be blacklisted without blacklisting user

        $row = phpList::DB()->fetchRowQuery(
            sprintf(
                'SELECT id FROM %s
                WHERE email = "%s"',
                Config::getTableName('user', true),
                $email
            )
        );
        User::addHistory(s('Added to blacklist'), s('Added to blacklist for reason %s', $reason), $row[0]);
    }

    /**
     * Blacklist an email address
     * @param string $email
     * @param string $reason
     * @param string $date
     */
    public static function blacklistEmail($email, $reason = '', $date = '')
    {
        if (empty($date)) {
            $sqldate = 'CURRENT_TIMESTAMP';
        } else {
            $sqldate = '"' . $date . '"';
        }
        $email = String::sqlEscape($email);

        #0012262: blacklist only email when email bounces. (not users): Function split so email can be blacklisted without blacklisting user
        phpList::DB()->query(
            sprintf(
                'INSERT IGNORE INTO %s (email,added)
                VALUES("%s",%s)',
                Config::getTableName('user_blacklist'),
                String::sqlEscape($email),
                $sqldate
            )
        );

        # save the reason, and other data
        phpList::DB()->query(
            sprintf(
                'INSERT IGNORE INTO %s (email, name, data)
                VALUES("%s","%s","%s"),
                ("%s","%s","%s")',
                Config::getTableName('user_blacklist_data'),
                $email,
                'reason',
                addslashes($reason),
                $email,
                'REMOTE_ADDR',
                addslashes($_SERVER['REMOTE_ADDR'])
            )
        );

        /*foreach (array("REMOTE_ADDR") as $item ) { # @@@do we want to know more?
            if (isset($_SERVER['REMOTE_ADDR'])) {
                phpList::DB()->Sql_Query(sprintf(
                    'INSERT IGNORE INTO %s (email, name, data)
                    VALUES("%s","%s","%s")',
                    Config::getTableName('user_blacklist_data'),addslashes($email),
                    $item,addslashes($_SERVER['REMOTE_ADDR'])));
            }
        }*/
        //when blacklisting only an email address, don't add this to the history, only do this when blacklisting a user
        //addUserHistory($email,s('Added to blacklist'),s('Added to blacklist for reason %s',$reason));
    }

    /**
     * Remove user from blacklist
     * @param int $user_id
     * @param string $admin_name
     */
    public static function unBlackList($user_id = 0, $admin_name = '')
    {
        if (!$user_id) return;
        $user = User::getUser($user_id);

        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s, %s
                WHERE email = "%s"',
                Config::getTableName('user_blacklist'),
                Config::getTableName('user_blacklist_data'),
                $user->email
            )
        );

        $user->blacklisted = 0;
        $user->update();

        if ($admin_name != '') {
            $msg = s("Removed from blacklist by %s", $admin_name);
        } else {
            $msg = s('Removed from blacklist');
        }
        User::addHistory($msg, '', $user->id);
    }

}