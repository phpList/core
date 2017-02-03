<?php
namespace phpList\Model;

use phpList\Entity\SubscriberEntity;
use phpList\helper\StringClass;

class ListModel
{

    protected $db;

    public function __construct(\phplist\Config $config, \phplist\helper\Database $db)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
    * Add subscriber to given list
    * @param $subscriber_id
    * @param $list_id
    */
    public function addSubscriber($subscriberId, $listId)
    {
        $lists = $this->getListsForSubscriber((int) $subscriberId);
        foreach ($lists as $list) {
            if ((int) $list["id"] === (int) $listId) {
                throw new \Exception("Subscriber is already subscribed on that list");
            }
        }

        $result = $this->db->query(
            sprintf(
                'INSERT INTO
                    %s
                        (userid, listid)
                VALUES
                    (%d, %d)',
                $this->config->getTableName('listuser'),
                $subscriberId,
                $listId
            )
        );

        return $result;
    }

    /**
    * Subscribe an array of subscriber ids to given list
    * @param $subscriber_ids
    * @param $list_id
    */
    public function addSubscribers($subscriberIdArray, $listId)
    {
        if (!empty($subscriberIdArray)) {
            $query = sprintf('
                INSERT INTO
                    %s
                        (userid, listid, entered, modified)
                VALUES', $this->config->getTableName('listuser'));

            foreach ($subscriberIdArray as $uid) {
                $query .= sprintf('
                    (%d, %d, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),', $uid, $listId);
            }
            $query = rtrim($query, ',') . ';';
            $this->db->query($query);
        }
    }

    /**
    * Unsubscribe given subscriber from given list
    * @param int $scrId The ID of the subscriber to remove from list
    * @param int $listId The ID of the list to remove them from
    */
    public function removeSubscriber($listId, $scrId)
    {
        $result = $this->db->query(
            sprintf(
                'DELETE FROM
                    %s
                WHERE
                    userid = %d
                AND
                    listid = %d',
                $this->config->getTableName('listuser'),
                $scrId,
                $listId
            )
        );

        return $result;
    }

    /**
    * Unsubscribe an array of subscriber ids from given list
    * @param $subscriber_ids
    * @param $list_id
    */
    public function removeSubscribers($subscriber_ids, $list_id)
    {
        if (!empty($subscriber_ids)) {
            $this->db->query(
                sprintf(
                    'DELETE FROM
                    %s
                WHERE
                    userid IN (%s)
                AND
                    listid = %d',
                    $this->config->getTableName('listuser'),
                    implode(',', $subscriber_ids),
                    $list_id
                )
            );
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
            sprintf('
            SELECT
                *
            FROM
                %s %s', $this->config->getTableName('list'), $this->config->get('subselect', ''))
        );
        return $this->makeLists($result);
    }

    /**
    * Get all lists of a given owner, can also be used to check if the user is the owner of given list
    * @param $owner_id
    * @param int $id
    * @return array
    */
    public function getListsByOwner($owner_id, $id = 0)
    {
        $result = $this->db->query(
            sprintf('
                SELECT
                    *
                FROM
                    %s
                WHERE
                    owner = %d %s', $this->config->getTableName('list'), $owner_id, (($id == 0) ? '' : " AND id = $id"))
        );
        return $this->makeLists($result);
    }

    /**
    * Get list with given id
    * @param $id
    * @return ListEntity
    */
    public function getListById($id)
    {
        $result = $this->db->query(
            sprintf('
                SELECT
                    *
                FROM
                    %s
                WHERE
                    id = %d', $this->config->getTableName('list'), $id)
        );
        return $this->listFromArray($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
    * Get lists a given user is subscribed to
    * @param $subscriber_id
    * @return array
    */
    public function getListsForSubscriber($subscriber_id)
    {
        $result = $this->db->query(
            sprintf('
                SELECT
                    l.*
                FROM
                    %s AS lu
                INNER JOIN
                    %s AS l
                ON
                    lu.listid = l.id
                WHERE
                    lu.userid = %d', $this->config->getTableName('listuser'), $this->config->getTableName('list'), $subscriber_id)
        );
        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
    * Get subscribers subscribed to a given list
    * @param $list
    * @return array
    */
    public function getListSubscribers($list)
    {
        if (is_array($list)) {
            $where = ' WHERE listid IN (' . join(',', $list) .')';
        } else {
            $where = sprintf(' WHERE listid = %d', $list);
        }
        $result = $this->db->query(
            sprintf('
                SELECT
                    userid
                FROM
                    %s %s', $this->config->getTableName('listuser'), $where)
        );

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }
}
