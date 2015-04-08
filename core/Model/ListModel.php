<?php
namespace phpList\Model;

use phpList\Entity\SubscriberEntity;
use phpList\helper\String;

class ListModel {

    protected $db;

    public function __construct( \phplist\Config $config, \phplist\helper\Database $db )
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
    * Add subscriber to given list
    * @param $subscriber_id
    * @param $list_id
    */
    public function addSubscriber( $subscriberId, $listId )
    {
        $this->db->query(
            sprintf(
                'INSERT INTO
                    %s
                        (userid, listid)
                VALUES
                    (%d, %d)'
                , $this->config->getTableName( 'listuser' )
                , $subscriberId
                , $listId
            )
        );
    }

    /**
    * Subscribe an array of subscriber ids to given list
    * @param $subscriber_ids
    * @param $list_id
    */
    public function addSubscribers( $subscriberIdArray, $listId )
    {
        if ( !empty( $subscriberIdArray ) ) {
            $query = sprintf( '
                INSERT INTO
                    %s
                        (userid, listid, entered, modified)
                VALUES'
                , $this->config->getTableName( 'listuser' )
            );

            foreach ( $subscriberIdArray as $uid ) {
                $query .= sprintf( '
                    (%d, %d, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),'
                    , $uid
                    , $listId
                );
            }
            $query = rtrim( $query, ',' ) . ';';
            $this->db->query( $query );
        }
    }

    /**
    * Get an array of all lists in the database
    * @return array
    */
    public function getAllLists()
    {
        //TODO: probably best to replace the subselect with a function parameter
        $result = $this->db->query(
        sprintf( '
            SELECT
                *
            FROM
                %s %s'
            , $this->config->getTableName( 'list' )
            , $this->config->get( 'subselect', '' ) )
        );
        return $this->makeLists( $result );
    }

    /**
    * Get all lists of a given owner, can also be used to check if the user is the owner of given list
    * @param $owner_id
    * @param int $id
    * @return array
    */
    public function getListsByOwner( $owner_id, $id = 0 )
    {
        $result = $this->db->query(
            sprintf( '
                SELECT
                    *
                FROM
                    %s
                WHERE
                    owner = %d %s'
                , $this->config->getTableName( 'list' )
                , $owner_id
                , ( ($id == 0) ? '' : " AND id = $id" )
            )
        );
        return $this->makeLists($result);
    }

    /**
    * Get list with given id
    * @param $id
    * @return MailingListEntity
    */
    public function getListById( $id )
    {
        $result = $this->db->query(
            sprintf( '
                SELECT
                    *
                FROM
                    %s
                WHERE
                    id = %d'
                , $this->config->getTableName( 'list' )
                , $id
            )
        );
        return $this->listFromArray($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
    * Get lists a given user is subscribed to
    * @param $subscriber_id
    * @return array
    */
    public function getListsForSubscriber( $subscriber_id )
    {
        $result = $this->db->query(
            sprintf( '
                SELECT
                    l.*
                FROM
                    %s AS lu
                INNER JOIN
                    %s AS l
                ON
                    lu.listid = l.id
                WHERE
                    lu.userid = %d'
                , $this->config->getTableName( 'listuser' )
                , $this->config->getTableName( 'list' )
                , $subscriber_id
            )
        );
        return $this->makeLists( $result );
    }

    /**
    * Get subscribers subscribed to a given list
    * @param $list
    * @return array
    */
    public function getListSubscribers( $list )
    {
        if( is_array( $list ) ) {
            $where = ' WHERE listid IN (' . join( ',', $list ) .')';
        } else {
            $where = sprintf( ' WHERE listid = %d', $list );
        }
        $result = $this->db->query(
            sprintf( '
                SELECT
                    userid
                FROM
                    %s %s'
                , $this->config->getTableName( 'listuser' )
                , $where
            )
        );

        return $result->fetchAll( \PDO::FETCH_ASSOC );
    }
}
