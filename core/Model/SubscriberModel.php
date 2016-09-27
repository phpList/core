<?php
namespace phpList\Model;

use phpList\Entity\SubscriberEntity;
use phpList\helper\StringClass;

class SubscriberModel {

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

    /**
    * @brief Save a subscriber to the database
    * @throws \Exception
    */
    public function save( $emailAddress, $encPass )
    {
        $query = sprintf(
            'INSERT INTO %s (
                email
                , entered
                , modified
                , password
                , passwordchanged
                , disabled
                , htmlemail
            )
            VALUES (
                "%s"
                , CURRENT_TIMESTAMP
                , CURRENT_TIMESTAMP
                , "%s"
                , CURRENT_TIMESTAMP
                , 0
                , 1
            )'
            , $this->config->getTableName( 'user', true )
            , $emailAddress
            , $encPass
        );

        // If query is successful, retrieve IDs
        if ( $this->db->query( $query ) ) {
            $id = $this->db->insertedId();
            $uniqeId = $this->giveUniqueId( $id );
            return $id;
        } else {
            // Query unsuccessful, throw error
            throw new \Exception( 'There was an error inserting the subscriber: ' . $this->db->getErrorMessage() );
        }
    }

    /**
    * Update a subscriber's details
    * @Note To update just the user password, use @updatePass
    */
    public function update( $blacklisted, $confirmed, $emailAddress, $extradata, $htmlemail, $id, $optedin )
    {
        $query = sprintf(
            'UPDATE
                %s
            SET
                email = "%s",
                confirmed = "%s",
                blacklisted = "%s",
                optedin = "%s",
                modified = CURRENT_TIMESTAMP,
                htmlemail = "%s",
                extradata = "%s"
            WHERE
                id = %d'
            , $this->config->getTableName( 'user', true )
            , $emailAddress
            , $confirmed
            , $blacklisted
            , $optedin
            , $htmlemail
            , $extradata
            , $id
        );

        if ( $this->db->query( $query ) ) {
            return true;
        } else {
            throw new \Exception( 'Updating subscriber failed: ' . $this->db->getErrorMessage() );
        }
    }

    public function getSubscriberById( $id )
    {
        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                $this->config->getTableName( 'user', true ),
                $id
            )
        );
        return $result->fetch( \PDO::FETCH_ASSOC );
    }

    public function getSubscriberByEmail( $emailAddress )
    {
        $result = $this->db->query(
            sprintf(
                "SELECT * FROM %s
                WHERE email = '%s'",
                $this->config->getTableName( 'user', true ),
                $emailAddress
            )
        );
        return $result->fetch( \PDO::FETCH_ASSOC );
    }

    /**
     * Cleanly delete all records of a subscriber from DB
     * @param  int $id ID of subscriber to delete
     * @return mixed Result of db->deleteFromArray()
     */
    public function delete( $id )
    {
        // Get the correct table mappings from Config{} class
        $tables = array(
            $this->config->getTableName('listuser') => 'userid',
            $this->config->getTableName('user_attribute', true) => 'userid',
            $this->config->getTableName('usermessage') => 'userid',
            $this->config->getTableName('user_history', true) => 'userid',
            $this->config->getTableName('user_message_bounce', true) => 'user',
            $this->config->getTableName('user', true) => 'id'
        );

        // If user_group table exists, tag it for deletion
        // NOTE: Why is this check necessary? backwards compatibility?
        if ( $this->db->tableExists( $this->config->getTableName( 'user_group') ) ) {
            $tables[$this->config->getTableName('user_group')] = 'userid';
        }

        // Delete all entries from DB & return result
        return $this->db->deleteFromArray( $tables, $id );
    }

    /**
    * Assign a unique id to a Subscriber
    * @param int $subscriber_id
    * @return string unique id
    */
    private function giveUniqueId( $subscriberId )
    {
        //TODO: make uniqueId a unique field in database
        do {
            $uniqueId = md5(uniqid(mt_rand()));
        } while (
            !$this->db->query(
                sprintf(
                    'UPDATE %s SET uniqid = "%s"
                    WHERE id = %d',
                    $this->config->getTableName('user', true),
                    $uniqueId,
                    $subscriberId
                )
            )
        );
        return $uniqueId;
    }

    public function updatePass( $id, $encPass )
    {
        $query = sprintf(
            'UPDATE
                %s
            SET
                password = "%s", passwordchanged = CURRENT_TIMESTAMP
            WHERE
                id = %d'
            , $this->config->getTableName( 'user', true )
            , $encPass
            , $id
        );
        $this->db->query( $query );
    }
}
