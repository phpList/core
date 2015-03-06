<?php
namespace phpList\Model;

use phpList\Entity\SubscriberEntity;
use phpList\helper\String;

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
    * @Note To update just the user password, use @updatePassword
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

    /**
    * Assign a unique id to a Subscriber
    * @param int $subscriber_id
    * @return string unique id
    */
    private function giveUniqueId( $subscriber_id )
    {
        //TODO: make uniqueid a unique field in database
        do {
            $unique_id = md5( uniqid( mt_rand() ) );
        } while (
            !$this->db->query(
                sprintf(
                'UPDATE %s SET uniqid = "%s"
                WHERE id = %d',
                $this->config->getTableName('user', true),
                $unique_id,
                $subscriber_id
                )
            )
        );
        return $unique_id;
    }
}
