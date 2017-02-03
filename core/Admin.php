<?php
namespace phpList;

use phpList\helper\StringClass;
use phpList\helper\Util;
use phpList\Entity\AdminEntity;

class Admin
{
    public $id = 0;
    private $loginname;

    public function __construct($adminModel, $pass)
    {
        $this->adminModel = $adminModel;
        $this->pass = $pass;
    }

    /**
     * Set the login name and return false if it alreay is in use
     * @param string $loginname
     * @return bool
     */
    public function setLoginname($loginname)
    {
        $this->loginname = strtolower(StringClass::normalize($loginname));
        return $this->isLoginUnique();
    }

    private $namelc;

    /**
     * @param string $namelc
     */
    public function setNamelc($namelc)
    {
        $this->namelc = strtolower(StringClass::normalize($namelc));
    }

    public $email;
    public $created;
    public $modified;
    public $modifiedby;
    private $password;

    /**
     * @param string $password
     * Will encrypt and set the password
     * and update  db when changing the password of
     * an existing admin
     */
    public function setPassword($password)
    {
        $this->password = Util::encryptPass($password);
        if ($this->id != 0) {
            phpList::DB()->query(
                sprintf(
                    'UPDATE %s
                    SET password = "%s", passwordchanged = CURRENT_TIMESTAMP
                    WHERE id = %s',
                    Config::getTableName('admin'),
                    $this->password,
                    $this->id
                )
            );
        }
    }

    public $passwordchanged;
    public $superuser;
    public $disabled;

    /**
     * @var array ('subscribers' => true/false, 'campaigns' => true/false,'statistics' => true/false, 'settings' => true/false);
     */
    public $privileges;

    /**
     * Get an admin by id
     * @param int $id
     * @return Admin
     */
    public static function getAdmin($id)
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('admin'),
                $id
            )
        );

        return Admin::adminFromArray($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Get all admins from the database
     * If a string is provided, it will try to search for the admins matching the string
     * @param string $search
     * @return array Admin
     */
    public static function getAdmins($search = '')
    {
        $admins = array();
        $condition = '';
        if ($search != '') {
            $search = StringClass::sqlEscape($search);
            $condition = sprintf(' WHERE loginname LIKE "%%%s%%" OR email LIKE "%%%s%%"', $search, $search);
        }

        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                %s ORDER BY loginname',
                Config::getTableName('admin'),
                $condition
            )
        );


        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $admins[] = Admin::adminFromArray($row);
        }
        return $admins;
    }

    /**
     * Save the admin in the database
     */
    public function save()
    {
        if ($this->id != 0) {
            //TODO: maybe not send empty modifiedby param?
            $this->update('call to save function');
        } else {
            if (!$this->isLoginUnique()) {
                throw new \Exception('Login name already in use');
            } elseif (empty($this->namelc)) {
                //TODO: why is this used?
                $this->namelc = $this->loginname;
            }
            phpList::DB()->query(
                sprintf(
                    'INSERT INTO %s (loginname, namelc, created)
                    VALUES("%s", "%s", CURRENT_TIMESTAMP)',
                    Config::getTableName('admin'),
                    $this->loginname,
                    $this->namelc
                )
            );
        }
    }

    /**
     * Update back to db
     * $modifiedby can be any string to see who has changed the record
     * @param string $modifiedby
     */
    public function update($modifiedby)
    {
        $privileges = StringClass::sqlEscape(serialize($this->privileges));
        phpList::DB()->query(
            sprintf(
                'UPDATE %s SET
                loginname = "%s", namelc = "%s", email = "%s", modified = CURRENT_TIMESTAMP, modifiedby = "%s", superuser = %d, disabled = %d, privileges = "%s"',
                Config::getTableName('admin'),
                $this->loginname,
                $this->namelc,
                $this->email,
                $modifiedby,
                $this->superuser,
                $this->disabled,
                $privileges
            )
        );
    }

    /**
     * Remove an admin from the database
     * @param int $id
     */
    //TODO: not sure if this should be static
    public static function delete($id)
    {
        $tables = array(
            Config::getTableName('admin') => 'id',
            Config::getTableName('admin_attribute') => 'adminid',
            Config::getTableName('admin_task') => 'adminid'
        );
        phpList::DB()->deleteFromArray($tables, $id);
    }

    /**
     * Check if the login name is unique
     */
    private function isLoginUnique()
    {
        $condition = '';
        if ($this->id != 0) {
            $condition = ' AND NOT id = ' . $this->id;
        }
        $result = phpList::DB()->query(
            sprintf(
                'SELECT COUNT(id) FROM %s
                WHERE loginname = "%s" %s',
                Config::getTableName('admin'),
                $this->loginname,
                $condition
            )
        );
        return ($result->fetchColumn(0) == 0) ? true : false;
    }

    /**
     * Add admin attributes to the database
     * @param array $attributes
     */
    public function addAttributes($attributes)
    {
        while (list($key, $val) = each($attributes)) {
            phpList::DB()->query(
                sprintf(
                    'REPLACE INTO %s
                    (adminid,adminattributeid,value)
                    VALUES(%d,%d,"%s")',
                    Config::getTableName('admin_attribute'),
                    $this->id,
                    $key,
                    addslashes($val)
                )
            );
        }
    }

    //TODO: Should we still use admin attributes?
    public function getAttributes()
    {
        $attributes = array();
        $result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s AS adm_att
                INNER JOIN %s AS adm
                ON adm_att.adminid = adm.id
                WHERE adm.id = %d',
                Config::getTableName('admin_attribute'),
                Config::getTableName('admin'),
                $this->id
            )
        );

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $attributes[] = $row;
        }
        return $attributes;
    }

    /**
     * Will check login credentials and return result array
     * @param $login string
     * @param $password string
     * @return array $result Keys: admin [object], error [string], result [bool]
     */
    public function validateLogin($plainPass, $username)
    {
        $return = array(
            'result' => false,
            // FIXME: Re-add translation / formatting logic to message
            'error' => 'Login failed',
            'admin' => null
        );

        // Hash the supplied password for comparison
        $encPass = $this->pass->encrypt($plainPass);

        $result = $this->adminModel->getAdminByUsername($username);

        // If an admin was found with that username
        if ($result) {
            $adminEntity = $this->adminEntityFromArray($result);
        }

        if (! $result) {
            $return['error'] = 'Admin not found'; // If admin not found
        } elseif ($adminEntity->disabled) {
            // FIXME: translation / formatting via s() removed
            $return['error'] = 'Your account has been disabled';
        } elseif ($encPass == $adminEntity->encPass) {
            $return['result'] = true;
            $return['error'] = '';
            $return['admin'] = $adminEntity;
        } elseif ($encPass != $adminEntity->encPass) {
            // FIXME: translation / formatting via s() removed
            $return['error'] = 'Incorrect password';
        } else {
            // FIXME: translation / formatting via s() removed
            $return['error'] = 'Unknown error';
        }
        return $return;
    }

    private static function adminEntityFromArray(array $array)
    {
        // FIXME: Move this object instantiation to DI.
        $adminEntity = new AdminEntity($array['loginname']);

        $adminEntity->id = $array['id'];
        $adminEntity->namelc = $array['namelc'];
        $adminEntity->email = $array['email'];
        $adminEntity->created = $array['created'];
        $adminEntity->modified = $array['modified'];
        $adminEntity->modifiedby = $array['modifiedby'];
        $adminEntity->encPass = $array['password'];
        $adminEntity->passwordchanged = $array['passwordchanged'];
        $adminEntity->superuser = $array['superuser'];
        $adminEntity->disabled = $array['disabled'];
        $adminEntity->privileges = unserialize($array['privileges']);

        return $adminEntity;
    }

    /**
     * Send email with a random encrypted token.
     * @return bool
     */
    public function sendPasswordToken()
    {
        #Check if the token is not present in the database yet.
        //TODO: make key_value a unique field in the database
        do {
            $unique_key = md5(uniqid(mt_rand()));
        } while (!phpList::DB()->query(
            sprintf(
                'INSERT INTO %s (date, admin, key_value)
                VALUES (CURRENT_TIMESTAMP, %d, "%s")',
                Config::getTableName('admin_password_request'),
                $this->id,
                $unique_key
            )
        ));

        $urlroot = Config::get('website') . Config::get('adminpages');
        #Build the email body to be sent, and finally send it.
        $emailBody = s('Hello') . ' ' . $this->loginname . "\n\n";
        $emailBody .= s('You have requested a new password for phpList.') . "\n\n";
        $emailBody .= s('To enter a new one, please visit the following link:') . "\n\n";
        $emailBody .= sprintf('http://%s/?page=login&token=%s', $urlroot, $unique_key) . "\n\n";
        $emailBody .= s('You have 24 hours left to change your password. After that, your token won\'t be valid.');
        //TODO: convert to new mail class
        return phpListMailer::sendMail($this->email, s('New password'), "\n\n" . $emailBody, '', '', true);
    }

    /**
     * Delete expired tokens from the database
     */
    public static function deleteOldTokens()
    {
        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE date_add( date, INTERVAL %s) < CURRENT_TIMESTAMP',
                Config::getTableName('admin_password_request'),
                Config::get('password_change_timeframe')
            ),
            1
        );

        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE expires < CURRENT_TIMESTAMP',
                Config::getTableName('admintoken')
            ),
            1
        );
    }

    /**
     * Check if a form token is valid
     * @param string $token
     * @return bool
     */
    public function verifyToken($token)
    {
        if (empty($token)) {
            return false;
        }

        ## @@@TODO for now ignore the error. This will cause a block on editing admins if the table doesn't exist.
        $result = phpList::DB()->prepare(
            sprintf(
                'SELECT id FROM %s
                WHERE adminid = %d
                AND value = :token
                AND expires > CURRENT_TIMESTAMP',
                Config::getTableName('admintoken'),
                $this->id
            )
        );
        $result->execute(array(':token' => $token));
        return ($result->rowCount() > 0);
    }

    public function checkIfTheTokenIsValid($token)
    {
        return $this->adminModel->checkIfTheTokenIsValid($token);
    }

    public function setLoginToken($id)
    {
        $this->adminModel->setLoginToken($id);
    }

    public function getLoginToken($id)
    {
        return $this->adminModel->getLoginToken($id);
    }
}
