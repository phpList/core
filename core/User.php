<?php
/**
 * User: SaWey
 * Date: 13/12/13
 */

namespace phpList;

use phpList\helper\Validation;
use phpList\helper\Util;

class User
{
    public $id;
    private $email;

    /**
     * @param string $email
     * @throws \InvalidArgumentException
     */
    public function setEmail($email)
    {
        if(!Validation::validateEmail($email)){
            throw new \InvalidArgumentException('Invalid email address provided');
        }
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }
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
     * Set password and encrypt it
     * For existing users, password will be written to database
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = Util::encryptPass($password);
        if ($this->id != null) {
            $this->updatePassword();
        }
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }


    public $passwordchanged;
    public $disabled;
    public $extradata;
    public $foreignkey;
    private $attributes;
    /**
     * Used for looping over all usable user attributes
     * for replacing in messages, urls etc. (see fetchUrl)
     * @var array
     */
    public static $DB_ATTRIBUTES = array(
        'id', 'email', 'confirmed', 'blacklisted', 'optedin', 'bouncecount',
        'entered', 'modified', 'uniqid', 'htmlemail', 'subscribepage', 'rssfrequency',
        'extradata', 'foreignkey'
    );

    /**
     * @param string $email
     * @throws \InvalidArgumentException
     */
    public function __construct($email)
    {
        $this->setEmail($email);
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
        return User::getUserBy('email', $email);
    }

    /**
     * Get user object from foreign key value
     * @param string $fk
     * @return User
     */
    public static function getUserByForeignKey($fk)
    {
        return User::getUserBy('foreignkey', $fk);
    }

    /**
     * Get user object from unique id value
     * @param string $unique_id
     * @return User
     */
    public static function getUserByUniqueId($unique_id)
    {
        return User::getUserBy('uniqueid', $unique_id);
    }

    /**
     * Get a user by searching for a value in a given column
     * @param string $column
     * @param string $value
     * @return User
     */
    private static function getUserBy($column, $value)
    {
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE %s = "%s"',
                Config::getTableName('user', true),
                $column,
                phpList::DB()->sqlEscape($value)
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
     * @return bool
     */
    public function save()
    {
        if ($this->id != 0) {
            $this->update();
        } else {
            $query = sprintf(
                'INSERT INTO %s
                (email, entered, modified, password, passwordchanged, disabled, htmlemail)
                VALUES("%s", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, "%s", CURRENT_TIMESTAMP, 0, 1)',
                Config::getTableName('user', true),
                $this->email,
                $this->password
            );
            if(phpList::DB()->query($query)){
                $this->id = phpList::DB()->insertedId();
                $this->uniqid = self::giveUniqueId($this->id);
            }else{
                return false;
            }
        }
        return true;
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
     * Assign a unique id to a user
     * @param int $user_id
     * @return string unique id
     */
    private static function giveUniqueId($user_id)
    {
        //TODO: make uniqueid a unique field in database
        do {
            $unique_id = md5(uniqid(mt_rand()));
        } while (!phpList::DB()->query(
            sprintf(
                'UPDATE %s SET uniqid = "%s"
                WHERE id = %d',
                Config::getTableName('user', true),
                $unique_id,
                $user_id
            )
        ));
        return $unique_id;
    }

    /**
     * Add new user to database
     * @param $email
     * @param $password
     * @throws \InvalidArgumentException
     */
    public static function addUser($email, $password)
    {
        $user = new User($email);
        $user->setPassword($password);
        $user->save();
    }

    /**
     * Remove user from database
     */
    public function delete()
    {
        $tables = array(
            Config::getTableName('listuser') => 'userid',
            Config::getTableName('user_attribute', true) => 'userid',
            Config::getTableName('usermessage') => 'userid',
            Config::getTableName('user_history', true) => 'userid',
            Config::getTableName('user_message_bounce', true) => 'user',
            Config::getTableName('user', true) => 'id'
        );
        if (phpList::DB()->tableExists(Config::getTableName('user_group'))) {
            $tables[Config::getTableName('user_group')] = 'userid';
        }
        phpList::DB()->deleteFromArray($tables, $this->id);
    }

    /**
     * Get the lists this user is subscribed for
     * @return array
     */
    public function isMemberOf()
    {
        return MailingList::getListsForUser($this->id);
    }


    /**
     * Create a User object from database values
     * @param $array
     * @return User
     */
    private static function userFromArray($array)
    {
        if(!empty($array)){
            $user = new User($array['email']);
            $user->id = $array['id'];
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
        }else{
            return false;
        }
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

    /**
     * Get a user attribute
     * @param string $attribute
     * @return string|null
     */
    public function getAttribute($attribute)
    {
        if (empty($this->attributes)) {
            $this->loadAttributes();
        }

        if (!isset($this->attributes[$attribute])) {
            return null;
        } else {
            return $this->attributes[$attribute];
        }
    }

    /**
     * Load this users attributes from the database
     */
    public function loadAttributes()
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

    /**
     * Get all user attributes
     * @return array
     */
    public function getCleanAttributes()
    {
        $this->loadAttributes();
        $clean_attributes = array();
        foreach ($this->attributes as $key => $val) {
            ## in the help, we only list attributes with "strlen < 20"
            if (strlen($key) < 20) {
                $clean_attributes[String::cleanAttributeName($key)] = $val;
            }
        }
        return $clean_attributes;
    }

    /**
     * Add an attribute to the database for this user
     * @param int $id
     * @param string $value
     */
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

    /**
     * Add a history item for this user
     * @param string $msg
     * @param string $detail
     * @param string $user_id
     */
    public static function addHistory($msg, $detail, $user_id)
    {
        if (empty($detail)) { ## ok duplicated, but looks better :-)
            $detail = $msg;
        }

        $sysinfo = "";
        $sysarrays = array_merge($_ENV, $_SERVER);
        if (is_array(Config::$USERHISTORY_SYSTEMINFO)) {
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
     * Check if this user is allowed to send mails to
     * @return bool
     */
    public function allowsReceivingMails()
    {
        $confirmed = $this->confirmed && !$this->disabled;
        return (!$this->blacklisted && $confirmed);
    }

}