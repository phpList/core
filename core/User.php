<?php
/**
 * User: SaWey
 * Date: 13/12/13
 */

namespace phpList;

class User {
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
    public $passwordchanged;
    public $disabled;
    public $extradata;
    public $foreignkey;
    private $attributes;

    public function __construct(){}

    /**
     * Get user by id
     * @param int $id
     * @return User
     */
    public static function getUser($id){
        $result = phpList::DB()->Sql_Fetch_Assoc_Query(sprintf(
            'SELECT * FROM %s
            WHERE id = %d',
            Config::getTableName('user', true), $id));
        return User::userFromArray($result);
    }

    /**
     * Get user object from foreign key value
     * @param $fk string
     * @return User
     */
    public static function getUserByForeignKey($fk){
        $result = phpList::DB()->Sql_Fetch_Assoc_Query(sprintf(
            'SELECT * FROM %s
            WHERE foreignkey = %s',
            Config::getTableName('user', true), $fk));
        return User::userFromArray($result);
    }

    /**
     * Add user to selected list
     * @param $list_id
     */
    public function addToList($list_id){
        MailingList::addSubscriber($this->id, $list_id);
    }

    /**
     * Write user info to database
     * @return int
     */
    public function create(){
        do{
            $unique_id = md5(uniqid(mt_rand()));
        }while(!phpList::DB()->Sql_Query(sprintf(
            'INSERT INTO %s
            (email, entered, modified, password, passwordchanged, disabled, uniqid, htmlemail)
            VALUES("%s", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, "%s", CURRENT_TIMESTAMP, 0, "%s, 1")',
            Config::getTableName('user', true), $this->email, $this->password, $unique_id)));

        $this->id = phpList::DB()->Sql_Insert_Id();
        return $this->id;
    }

    /**
     * Add new user to database
     * @param $email
     * @param $password
     */
    public static function add($email, $password){
        $user = new User();
        $user->email = $email;
        $user->setPassword($password);
        $user->create();
    }

    /**
     * Check if email address exists in database
     * @param $email
     * @return bool
     */
    public function emailExists($email){
        $result = Sql_Fetch_Row_Query(sprintf(
            'SELECT id FROM %s
            WHERE email = "%s"',
            Config::getTableName('user'),$email));
        return !empty($result[0]);
    }

    /**
     * Save user info to database
     */
    public function save(){
        //TODO: what to save?
        $query = sprintf(
            'UPDATE %s SET email = "%s", confirmed = "%s", blacklisted = "%s", optedin = "%s", modified = CURRENT_TIMESTAMP, htmlemail = "%s", subscribepage = "%s", rssfrequency = "%s", password = "%s", passwordchanged = "%s", extradata = "%s"');

        //phpList::DB()->Sql_Query($query);
    }

    /**
     * Remove user from database
     */
    public function delete(){
        $query = sprintf('DELETE FROM %s, %s, %s, %s
                            WHERE userid = %d',
                            Config::getTableName('listuser'),
                            Config::getTableName('user_attribute', true),
                            Config::getTableName('usermessage'),
                            Config::getTableName('user_history', true),
                            $this->id);
        phpList::DB()->Sql_Query($query);
        phpList::DB()->Sql_Query(sprintf(
            'DELETE FROM %s
            WHERE user = %d',
            Config::getTableName('user_message_bounce', true),$this->id));
        phpList::DB()->Sql_Query(sprintf(
            'DELETE FROM %s
            WHERE id = %d',
            Config::getTableName('user', true),$this->id));


        if (phpList::DB()->Sql_table_exists(Config::getTableName('user_group'))) {
            phpList::DB()->Sql_Query(sprintf(
                'DELETE FROM user_group
                WHERE userid = %d',
                $this->id),1);
        }
    }

    public function isMemberOf(){
        return MailingList::getListsForUser($this->id);
    }


    /**
     *
     * @param $array
     * @return User
     */
    private static function userFromArray($array){
        $user = new User();
        $user->id       = $array['id'];
        $user->email        = $array['email'];
        $user->confirmed    = $array['confirmed'];
        $user->blacklisted  = $array['blacklisted'];
        $user->optedin      = $array['optedin'];
        $user->bouncecount  = $array['bouncecount'];
        $user->entered      = $array['entered'];
        $user->modified     = $array['modified'];
        $user->uniqid       = $array['uniqid'];
        $user->htmlemail    = $array['htmlemail'];
        $user->subscribepage= $array['subscribepage'];
        $user->rssfrequency = $array['rssfrequency'];
        $user->password     = $array['password'];
        $user->passwordchanged = $array['passwordchanged'];
        $user->disabled     = $array['disabled'];
        $user->extradata    = $array['extradata'];
        $user->foreignkey   = $array['foreignkey'];
        return $user;
    }

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
        if($this->id != null) $this->updatePassword();
    }

    /**
     * Update password in db
     */
    private function updatePassword(){
        $query = sprintf(
            'UPDATE %s
            SET password = "%s", passwordchanged = CURRENT_TIMESTAMP
            WHERE id = %d',
            Config::getTableName('user'),
            $this->password,
            $this->id);
        phpList::DB()->Sql_Query($query);
    }

    public function getAttribute($attribute){
        if(empty($this->attributes)){
            $this->getAttributes();
        }

        if(!isset($this->attributes[$attribute])){
            return null;
        }else{
            return $this->attributes[$attribute];
        }
    }

    public function getAttributes(){
        $result = phpList::DB()->Sql_Query(sprintf(
            'SELECT a.id, a.name, ua.value
            FROM %s AS a
                INNER JOIN %s AS ua
                ON a.id = ua.attributeid
            WHERE ua.userid = %d
            ORDER BY listorder',
            Config::getTableName('attribute', true), Config::getTableName('user_attribute', true), $this->id));
        while($row = phpList::DB()->Sql_Fetch_Assoc($result)){
            $this->attributes[$row['id']] = $row['value'];
            $this->attributes[$row['name']] = $row['name'];
        }
    }

    public function addAttribute($id, $value){
        phpList::DB()->Sql_Query(sprintf(
            'REPLACE INTO %s
            (userid,attributeid,value)
            VALUES(%d,%d,"%s")',
            Config::getTableName('user_attribute'), $this->id, $id, phpList::DB()->Sql_Escape($value)));
    }

    function addHistory($msg,$detail) {
        if (empty($detail)) { ## ok duplicated, but looks better :-)
            $detail = $msg;
        }

        $sysinfo = "";
        $sysarrays = array_merge($_ENV,$_SERVER);
        //TODO: change this
        if ( isset($GLOBALS["userhistory_systeminfo"]) && is_array($GLOBALS["userhistory_systeminfo"]) ) {
            foreach ($GLOBALS["userhistory_systeminfo"] as $key) {
                if (isset($sysarrays[$key])) {
                    $sysinfo .= "\n$key = $sysarrays[$key]";
                }
            }
        } elseif ( is_array(Config::$USERHISTORY_SYSTEMINFO)) {
            foreach (Config::$USERHISTORY_SYSTEMINFO as $key) {
                if ($sysarrays[$key]) {
                    $sysinfo .= "\n$key = $sysarrays[$key]";
                }
            }
        } else {
            $default = array('HTTP_USER_AGENT','HTTP_REFERER','REMOTE_ADDR','REQUEST_URI');
            foreach ($sysarrays as $key => $val) {
                if (in_array($key,$default))
                    $sysinfo .= "\n".strip_tags($key) . ' = '.htmlspecialchars($val);
            }
        }
        if (isset($_SERVER["REMOTE_ADDR"])) {
            $ip = $_SERVER["REMOTE_ADDR"];
        } else {
            $ip = '';
        }
        phpList::DB()->Sql_Query(sprintf(
            'INSERT INTO %s
            (ip,userid,date,summary,detail,systeminfo)
            VALUES("%s",%d,now(),"%s","%s","%s")',
            Config::getTableName('user_history', true),$ip,$this->id,addslashes($msg),addslashes(htmlspecialchars($detail)),$sysinfo));
    }

} 