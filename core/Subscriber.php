<?php
/**
 * User: SaWey
 * Date: 13/12/13
 */

namespace phpList;

use phpList\helper\String;
use phpList\helper\Validation;
use phpList\helper\Util;

class Subscriber
{
    public $id;
    private $email_address;

    /**
     * @param string $email_address
     * @throws \InvalidArgumentException
     */
    public function setEmailAddress($email_address)
    {
        if(!Validation::validateEmail($email_address)){
            throw new \InvalidArgumentException('Invalid email address provided');
        }
        $this->email_address = $email_address;
    }

    /**
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->email_address;
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
     * For existing subscribers, password will be written to database
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
     * Used for looping over all usable subscriber attributes
     * for replacing in messages, urls etc. (@see fetchUrl)
     * @var array
     */
    public static $DB_ATTRIBUTES = array(
        'id', 'email', 'confirmed', 'blacklisted', 'optedin', 'bouncecount',
        'entered', 'modified', 'uniqid', 'htmlemail', 'subscribepage', 'rssfrequency',
        'extradata', 'foreignkey'
    );

    /**
     * @param string $email_address
     * @throws \InvalidArgumentException
     */
    public function __construct($email_address)
    {
        $this->setEmailAddress($email_address);
    }

    /**
     * Get subscriber by id
     * @param int $id
     * @return Subscriber
     */
    public static function getSubscriber($id)
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('user', true),
                $id
            )
        );
        return Subscriber::subscriberFromArray($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Get subscriber by email address
     * @param string $email_address
     * @return Subscriber
     */
    public static function getSubscriberByEmailAddress($email_address)
    {
        return Subscriber::getSubscriberBy('email', $email_address);
    }

    /**
     * Get Subscriber object from foreign key value
     * @param string $fk
     * @return Subscriber
     */
    public static function getSubscriberByForeignKey($fk)
    {
        return Subscriber::getSubscriberBy('foreignkey', $fk);
    }

    /**
     * Get Subscriber object from unique id value
     * @param string $unique_id
     * @return Subscriber
     */
    public static function getSubscriberByUniqueId($unique_id)
    {
        return Subscriber::getSubscriberBy('uniqueid', $unique_id);
    }

    /**
     * Get a Subscriber by searching for a value in a given column
     * @param string $column
     * @param string $value
     * @return Subscriber
     */
    private static function getSubscriberBy($column, $value)
    {
        $result = phpList::DB()->prepare(sprintf(
                'SELECT * FROM %s
                WHERE :key = :value',
                Config::getTableName('Subscriber', true)
            ));
        $result->bindParam(':key',$column);
        $result->bindParam(':value',$value);
        $result->execute();

        return Subscriber::subscriberFromArray($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Add Subscriber to selected list
     * @param $list_id
     */
    public function addToList($list_id)
    {
        MailingList::addSubscriber($this->id, $list_id);
    }

    /**
     * Write Subscriber info to database
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
                $this->email_address,
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
     * Save Subscriber info to database
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
            $this->email_address,
            $this->confirmed,
            $this->blacklisted,
            $this->optedin,
            $this->htmlemail,
            $this->extradata
        );

        phpList::DB()->query($query);
    }
    /**
     * Assign a unique id to a Subscriber
     * @param int $subscriber_id
     * @return string unique id
     */
    private static function giveUniqueId($subscriber_id)
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
                $subscriber_id
            )
        ));
        return $unique_id;
    }

    /**
     * Add new Subscriber to database
     * @param $email_address
     * @param $password
     * @throws \InvalidArgumentException
     */
    public static function addSubscriber($email_address, $password)
    {
        $subscriber = new Subscriber($email_address);
        $subscriber->setPassword($password);
        $subscriber->save();
    }

    /**
     * Remove subscriber from database
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
     * Get the lists this subscriber is subscribed for
     * @return array
     */
    public function isMemberOf()
    {
        return MailingList::getListsForSubscriber($this->id);
    }


    /**
     * Create a Subscriber object from database values
     * @param $array
     * @return Subscriber
     */
    private static function subscriberFromArray($array)
    {
        if(!empty($array)){
            $subscriber = new Subscriber($array['email']);
            $subscriber->id = $array['id'];
            $subscriber->confirmed = $array['confirmed'];
            $subscriber->blacklisted = $array['blacklisted'];
            $subscriber->optedin = $array['optedin'];
            $subscriber->bouncecount = $array['bouncecount'];
            $subscriber->entered = $array['entered'];
            $subscriber->modified = $array['modified'];
            $subscriber->uniqid = $array['uniqid'];
            $subscriber->htmlemail = $array['htmlemail'];
            $subscriber->subscribepage = $array['subscribepage'];
            $subscriber->rssfrequency = $array['rssfrequency'];
            $subscriber->password = $array['password'];
            $subscriber->passwordchanged = $array['passwordchanged'];
            $subscriber->disabled = $array['disabled'];
            $subscriber->extradata = $array['extradata'];
            $subscriber->foreignkey = $array['foreignkey'];
            return $subscriber;
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
     * Get a subscriber attribute
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
     * Load this subscribers attributes from the database
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

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $this->attributes[$row['id']] = $row['value'];
            $this->attributes[$row['name']] = $row['name'];
        }
    }

    /**
     * Get all subscriber attributes
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
     * Add an attribute to the database for this subscriber
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
     * Add a history item for this subscriber
     * @param string $msg
     * @param string $detail
     * @param string $subscriber_id
     */
    public static function addHistory($msg, $detail, $subscriber_id)
    {
        if (empty($detail)) { ## ok duplicated, but looks better :-)
            $detail = $msg;
        }

        $sysinfo = "";
        $sysarrays = array_merge($_ENV, $_SERVER);
        if (is_array(Config::$subscriberHISTORY_SYSTEMINFO)) {
            foreach (Config::$subscriberHISTORY_SYSTEMINFO as $key) {
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
                $subscriber_id,
                addslashes($msg),
                addslashes(htmlspecialchars($detail)),
                $sysinfo
            )
        );
    }

    /**
     * Get the unique id from a subscriber
     * @param int $subscriber_id
     * @return int unique_id
     */
    public static function getUniqueId($subscriber_id)
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT uniqid FROM %s
                WHERE id = %d',
                Config::getTableName('user', true),
                $subscriber_id
            )
        );
        return $result->fetch();
    }

    /**
     * Check if this subscriber is allowed to send mails to
     * @return bool
     */
    public function allowsReceivingMails()
    {
        $confirmed = $this->confirmed && !$this->disabled;
        return (!$this->blacklisted && $confirmed);
    }

}