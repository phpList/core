<?php
namespace phpList;

use phpList\Entity\MailingListEntity;

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
