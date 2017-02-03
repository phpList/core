<?php
namespace phpList;

use phpList\Entity\ListEntity;

/**
 * Class MailingList
 */
class ListManager
{
    protected $config;
    protected $db;

    /**
     * Default constructor
     */
    public function __construct(Config $config, helper\Database $db, \phpList\Model\ListModel $listModel)
    {
        $this->config = $config;
        $this->db = $db;
        $this->listModel = $listModel;
    }

    /**
     * Create a MailingList object from database values
     *
     * @param $array
     *
     * @return ListEntity
     */
    private function listFromArray($array)
    {
        $list = new ListEntity($array['name'], $array['description'], $array['listorder'], $array['owner'], $array['active'], $array['category'], $array['prefix']);
        $list->id = $array['id'];
        $list->entered = $array['entered'];
        $list->rssfeed = $array['rssfeed'];
        $list->modified = $array['modified'];
        return $list;
    }

    /**
     * Make an array of MailingList objects from a db result
     *
     * @param null|\PDOStatement $db_result
     *
     * @return array
     */
    private function makeLists($db_result)
    {
        $list = [];
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
    public function save(ListEntity $mailing_list)
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
    public function update(ListEntity $mailing_list)
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
     * Add a subscriber to a list
     *
     * @param SubscriberEntity $scrEntity
     * @param int $listId ID of the list to add the subscriber to
     */
    public function addSubscriber(\phpList\Entity\SubscriberEntity $scrEntity, $listId)
    {
        // Add the subscriber to the list
        return $this->listModel->addSubscriber($scrEntity->id, $listId);
    }

    /**
     * Add a subscriber to a list
     *
     * @param phpListEntityListEntity $listEntity
     * @param int $listId ID of the list to add the subscriber to
     */
    public function addSubscribers(array $scrEntities, $listId)
    {
        // Initialise array for collecting outcomes
        $results = [];

        // Loop through each subecriber entity object
        foreach ($scrEntities as $scrEntity) {
            // Add the subscriber to the list
            $results = $this->listModel->addSubscriber($scrEntity->id, $listId);
        }

        // Return true unless one or more queries failed
        if (false === array_search(false, $results)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Add a subscriber to a list
     *
     * @param SubscriberEntity $scrEntity
     * @param int $listId ID of the list to add the subscriber to
     */
    public function removeSubscriber($listId, \phpList\Entity\SubscriberEntity $scrEntity)
    {
        // Add the subscriber to the list
        return $this->listModel->removeSubscriber($listId, $scrEntity->id);
    }

    /**
     * Add a subscriber to a list
     *
     * @param phpListEntityListEntity $listEntity
     * @param int $listId ID of the list to add the subscriber to
     */
    public function removeSubscribers(array $scrEntities, $listId)
    {
        // Initialise array for collecting outcomes
        $results = [];

        // Loop through each subecriber entity object
        foreach ($scrEntities as $scrEntity) {
            // Add the subscriber to the list
            $results = $this->listModel->removeSubscriber($scrEntity->id, $listId);
        }

        // Return true unless one or more queries failed
        if (false === array_search(false, $results)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get available list categories
     *
     * @return array
     */
    public function getAllCategories()
    {
        $listCategories = $this->config->get('list_categories');
        $categories = explode(',', $listCategories);
        if (!$categories) {
            $categories = [];
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
