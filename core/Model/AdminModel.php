<?php
namespace phpList\Model;

use phpList\Entity\SubscriberEntity;
use phpList\helper\String;

class AdminModel {

    protected $db;

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

    public function __construct( \phplist\Config $config, \phplist\helper\Database $db )
    {
        $this->config = $config;
        $this->db = $db;
    }

    public function getAdminByUsername( $username )
    {
        $result = $this->db->query(
            sprintf(
                'SELECT
                    *
                FROM
                    %s
                WHERE
                    loginname = "%s"'
                , $this->config->getTableName( 'admin' )
                // FIXME: string->sqlEscape removed from here
                , $username
            )
        );
        
        return $result->fetch( \PDO::FETCH_ASSOC );
    }
}
