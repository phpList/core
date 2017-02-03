<?php
namespace phpList\Model;

use phpList\Admin;

class AdminModel
{
    protected $db;

    /**
     * Used for looping over all usable subscriber attributes
     * for replacing in messages, urls etc. (@see fetchUrl)
     *
     * @var array
     */
    public static $DB_ATTRIBUTES = [
        'id', 'email', 'confirmed', 'blacklisted', 'optedin', 'bouncecount',
        'entered', 'modified', 'uniqid', 'htmlemail', 'subscribepage', 'rssfrequency',
        'extradata', 'foreignkey',
    ];

    public function __construct(\phplist\Config $config, \phplist\helper\Database $db)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
     * Fetch all details of an Admin with a given username
     *
     * @param strong $username username of admin to fetch
     */
    public function getAdminByUsername($username)
    {
        $result = $this->db->query(
            sprintf(
                "SELECT
                    *
                FROM
                    %s
                WHERE
                    loginname = '%s'", $this->config->getTableName('admin')
                // FIXME: string->sqlEscape removed from here
, $username
            )
        );

        return $result->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if the given token is valid
     *
     * @param $token
     *
     * @return bool
     */
    public function checkIfTheTokenIsValid($token)
    {
        if (empty($token)) {
            return false;
        }

        ## @@@TODO for now ignore the error. This will cause a block on editing admins if the table doesn't exist.
        $result = $this->db->query(
            sprintf(
                "SELECT id FROM %s
                WHERE value = '%s'
                AND expires > CURRENT_TIMESTAMP",
                $this->config->getTableName('admintoken'),
                $token
            )
        );

        if ($result->fetch(\PDO::FETCH_ASSOC) !== false) {
            return true;
        }

        return false;
    }

    public function setLoginToken($id)
    {
        $this->db->query(sprintf("delete from %s WHERE adminid = '%s'", $this->config->getTableName('admintoken'), $id));

        $key = md5(time() . mt_rand(0, 10000));
        $tokenResult = $this->db->query(
            sprintf('insert into %s (adminid,value,entered,expires) values(%d,"%s",%d,date_add(now(),interval 1 hour))',
                $this->config->getTableName('admintoken'), $id, $key, time()), 1);

        ## keep the token table empty
        $result = $this->db->query(sprintf('delete from %s where expires < now()', $this->config->getTableName('admintoken')));

        if (count($result->fetch(\PDO::FETCH_ASSOC)) > 0) {
            return true;
        }

        return false;
    }

    public function getLoginToken($id)
    {
        $result = $this->db->query(sprintf("select * from %s WHERE adminid = '%s'", $this->config->getTableName('admintoken'), $id));
        $result = $result->fetch();
        if (count($result) > 0) {
            return $result['value'];
        }
    }

    public function validateLogin($plainPass, $username)
    {
        $admin = new Admin($this, $plainPass);
        return $admin->validateLogin($plainPass, $username);
    }
}
