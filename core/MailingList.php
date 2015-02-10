<?php
namespace phpList;

use phpList\entities\MailingListEntity;

/**
 * Class MailingList
 * @package PHPList\Core
 */
class MailingList
{
    protected $config;
    protected $db;

    /**
     * Default constructor
     */
    public function __construct(Config $config, helper\Database $db)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
     * Create a MailingList object from database values
     * @param $array
     * @return MailingListEntity
     */
    private function listFromArray($array)
    {
        $list = new MailingListEntity($array['name'], $array['description'], $array['listorder'], $array['owner'], $array['active'], $array['category'], $array['prefix']);
        $list->id = $array['id'];
        $list->entered = $array['entered'];
        $list->rssfeed = $array['rssfeed'];
        $list->modified = $array['modified'];
        return $list;
    }

    /**
     * Get an array of all lists in the database
     * @return array
     */
    public function getAllLists()
    {
        //TODO: probably best to replace the subselect with a function parameter
        $result = $this->db->query(
            sprintf('SELECT * FROM %s %s', $this->config->getTableName('list'), $this->config->get('subselect', ''))
        );
        return $this->makeLists($result->fetch(\PDO::FETCH_ASSOC));
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
            sprintf(
                'SELECT * FROM %s
                WHERE owner = %d %s',
                $this->config->getTableName('list'),
                $owner_id,
                (($id == 0) ? '' : " AND id = $id")
            )
        );
        return $this->makeLists($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Get list with given id
     * @param $id
     * @return MailingListEntity
     */
    public function getListById($id)
    {
        $result = $this->db->query(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                $this->config->getTableName('list'),
                $id
            )
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
            sprintf(
                'SELECT l.*
                FROM %s AS lu
                    INNER JOIN %s AS l
                    ON lu.listid = l.id
                WHERE lu.userid = %d',
                $this->config->getTableName('listuser'),
                $this->config->getTableName('list'),
                $subscriber_id
            )
        );
        return $this->makeLists($result->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Get subscribers subscribed to a given list
     * @param $list
     * @return array
     */
    public function getListSubscribers($list)
    {
        if(is_array($list)){
            $where = ' WHERE listid IN (' . join(',', $list) .')';
        }else{
            $where = sprintf(' WHERE listid = %d', $list);
        }
        $result = $this->db->query(
            sprintf(
                'SELECT userid FROM %s %s',
                $this->config->getTableName('listuser'),
                $where
            )
        );

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Make an array of MailingList objects from a db result
     * @param null|\PDOStatement $db_result
     * @return array
     */
    private function makeLists($db_result)
    {
        $list = array();
        if (!empty($db_result)) {
            while ($row = $db_result->fetch(\PDO::FETCH_ASSOC)) {
                $list[] = $this->listFromArray($row);
            }
        }
        return $list;
    }

    /**
     * Save a list to the database, will update if it already exists
     */
    public function save(MailingListEntity $mailing_list)
    {
        if ($mailing_list->id != 0) {
            $this->update($mailing_list);
        } else {
            $query = sprintf(
                'INSERT INTO %s
                (name, description, entered, listorder, owner, prefix, active, category)
                VALUES("%s", "%s", CURRENT_TIMESTAMP, %d, %d, "%s", %d, "%s")',
                $this->config->getTableName('list'),
                $mailing_list->name,
                $mailing_list->description,
                $mailing_list->listorder,
                $mailing_list->owner,
                $mailing_list->prefix,
                $mailing_list->active,
                $mailing_list->category
            );

            $this->db->query($query);
            $mailing_list->id = $this->db->insertedId();
        }
        return $mailing_list;
    }

    /**
     * Update this lists details in the database
     */
    public function update(MailingListEntity $mailing_list)
    {
        $query
            = 'UPDATE %s SET name = "%s", description = "%s", active = %d, listorder = %d, prefix = "%s", owner = %d
            WHERE id = %d';

        $query = sprintf(
            $query,
            $this->config->getTableName('list'),
            $mailing_list->name,
            $mailing_list->description,
            $mailing_list->active,
            $mailing_list->listorder,
            $mailing_list->prefix,
            $mailing_list->owner,
            $mailing_list->id
        );
        $this->db->query($query);
    }

    /**
     * Add subscriber to given list
     * @param $subscriber_id
     * @param $list_id
     */
    public function addSubscriber($subscriber_id, $list_id)
    {
        $this->db->query(
            sprintf(
                'INSERT INTO %s
                (userid, listid)
                VALUES(%d, %d)',
                $this->config->getTableName('listuser'),
                $subscriber_id,
                $list_id
            )
        );
    }

    /**
     * Subscribe an array of subscriber ids to given list
     * @param $subscriber_ids
     * @param $list_id
     */
    public function addSubscribers($subscriber_ids, $list_id)
    {
        if (!empty($subscriber_ids)) {
            $query = sprintf(
                'INSERT INTO %s
                (userid, listid, entered, modified)
                VALUES',
                $this->config->getTableName('listuser')
            );

            foreach ($subscriber_ids as $uid) {
                $query .= sprintf('(%d, %d, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),', $uid, $list_id);
            }
            $query = rtrim($query, ',') . ';';
            $this->db->query($query);
        }
    }

    /**
     * Unsubscribe given subscriber from given list
     * @param $subscriber_id
     * @param $list_id
     */
    public function removeSubscriber($subscriber_id, $list_id)
    {
        $this->db->query(
            sprintf(
                'DELETE FROM %s
                WHERE userid = %d
                AND listid = %d',
                $this->config->getTableName('listuser'),
                $subscriber_id,
                $list_id
            )
        );
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
                    'DELETE FROM %s
                    WHERE userid IN (%s)
                    AND listid = %d',
                    $this->config->getTableName('listuser'),
                    implode(',', $subscriber_ids),
                    $list_id
                )
            );
        }
    }

    /**
     * Get available list categories
     * @return array
     */
    public function getAllCategories()
    {
        $listCategories = $this->config->get('list_categories');
        $categories = explode(',', $listCategories);
        if (!$categories) {
            $categories = array();
            ## try to fetch them from existing lists
            $result = $this->db->query(
                sprintf(
                    'SELECT DISTINCT category FROM %s
                    WHERE category != "%s" ',
                    $this->config->getTableName('list')
                )
            );
            while ($row = $result->fetch()) {
                $categories[] = $row[0];
            }
            if (!empty($categories)) {
                $this->config->setDBConfig($this->db, 'list_categories', join(',', $categories));
            }
        } else {
            foreach ($categories as $key => $val) {
                $categories[$key] = trim($val);
            }
        }

        return $categories;
    }
}