<?php
/**
 * User: SaWey
 * Date: 5/12/13
 */

namespace phpList;

/**
 * Class MailingList
 * @package PHPList\Core
 */
class MailingList
{
    public $id = 0;
    public $name;
    public $description;
    public $entered;
    public $listorder;
    public $prefix;
    public $rssfeed;
    public $modified;
    public $active;
    public $owner;
    public $category;

    /**
     * @param string $name
     * @param string $description
     * @param int $listorder
     * @param int $owner
     * @param bool $active
     * @param int $category
     */
    public function __construct($name, $description, $listorder, $owner, $active, $category, $prefix)
    {
        $this->name = $name;
        $this->description = $description;
        $this->listorder = $listorder;
        $this->owner = $owner;
        $this->active = $active;
        $this->category = $category;
    }

    /**
     * Create a MailingList object from database values
     * @param $array
     * @return MailingList
     */
    private static function listFromArray($array)
    {
        $list = new MailingList($array['name'], $array['description'], $array['listorder'], $array['owner'], $array['active'], $array['category'], $array['prefix']);
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
    public static function getAllLists()
    {
        //TODO: probably best to replace the subselect with a function parameter
        $db_result = phpList::DB()->query(
            sprintf('SELECT * FROM %s %s', Config::getTableName('list'), Config::get('subselect', ''))
        );
        return MailingList::makeLists($db_result);
    }

    /**
     * Get all lists of a given owner, can also be used to check if the user is the owner of given list
     * @param $owner_id
     * @param int $id
     * @return array
     */
    public static function getListsByOwner($owner_id, $id = 0)
    {
        $db_result = phpList::DB()->query(
            sprintf(
                'SELECT * FROM %s
                WHERE owner = %d %s',
                Config::getTableName('list'),
                $owner_id,
                (($id == 0) ? '' : " AND id = $id")
            )
        );
        return MailingList::makeLists($db_result);
    }

    /**
     * Get list with given id
     * @param $id
     * @return MailingList
     */
    public static function getListById($id)
    {
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT * FROM %s
                WHERE id = %d',
                Config::getTableName('list'),
                $id
            )
        );
        return MailingList::listFromArray($result);
    }

    /**
     * Get lists a given user is subscribed to
     * @param $user_id
     * @return array
     */
    public static function getListsForUser($user_id)
    {
        $result = phpList::DB()->query(
            sprintf(
                'SELECT l.*
                FROM %s AS lu
                    INNER JOIN %s AS l
                    ON lu.listid = l.id
                WHERE lu.userid = %d',
                Config::getTableName('listuser'),
                Config::getTableName('list'),
                $user_id
            )
        );
        return MailingList::makeLists($result);
    }

    /**
     * Get users subscribed to a given list
     * @param $list
     * @return array
     */
    public static function getListUsers($list)
    {
        $user_ids = array();
        if(is_array($list)){
            $where = ' WHERE listid IN (' . join(',', $list) .')';
        }else{
            $where = sprintf(' WHERE listid = %d', $list);
        }
        $result = phpList::DB()->query(
            sprintf(
                'SELECT userid FROM %s %s',
                Config::getTableName('listuser'),
                $where
            )
        );
        while ($row = phpList::DB()->fetchRow($result)) {
            $user_ids[] = $row;
        }
        return $user_ids;
    }

    /**
     * Make an array of MailingList objects from a db result
     * @param $db_result
     * @return array
     */
    private static function makeLists($db_result)
    {
        $list = array();
        if (!empty($db_result)) {
            while ($row = phpList::DB()->fetchAssoc($db_result)) {
                $list[] = MailingList::listFromArray($row);
            }
        }
        return $list;
    }

    /**
     * Save a list to the database, will update if it already exists
     */
    public function save()
    {
        if ($this->id != 0) {
            $this->update();
        } else {
            $query = sprintf(
                'INSERT INTO %s
                (name, description, entered, listorder, owner, prefix, active, category)
                VALUES("%s", "%s", CURRENT_TIMESTAMP, %d, %d, "%s", %d, "%s")',
                Config::getTableName('list'),
                $this->name,
                $this->description,
                $this->listorder,
                $this->owner,
                $this->prefix,
                $this->active,
                $this->category
            );

            phpList::DB()->query($query);
            $this->id = phpList::DB()->insertedId();
        }
    }

    /**
     * Update this lists details in the database
     */
    public function update()
    {
        $query
            = 'UPDATE %s SET name = "%s", description = "%s", active = %d, listorder = %d, prefix = "%s", owner = %d
            WHERE id = %d';

        $query = sprintf(
            $query,
            Config::getTableName('list'),
            $this->name,
            $this->description,
            $this->active,
            $this->listorder,
            $this->prefix,
            $this->owner,
            $this->id
        );
        phpList::DB()->query($query);
    }

    /**
     * Check if this list is used in a subscribe page
     * TODO: move out of core functionality
     * @return bool
     */
    public function usedInSubscribePage()
    {
        if ($this->id == 0) {
            return false;
        }

        $req = phpList::DB()->query(
            sprintf(
                'SELECT data
                FROM %s
                WHERE name = "lists"',
                Config::getTableName('subscribepage_data')
            )
        );
        while ($row = phpList::DB()->fetchAssoc($req)) {
            $lists = explode(',', $row['data']);
        }
        return in_array($this->id, $lists);
    }

    /**
     * Subscribe a user to given list
     * @param $user_id
     * @param $list_id
     */
    public static function addSubscriber($user_id, $list_id)
    {
        phpList::DB()->query(
            sprintf(
                'INSERT INTO %s
                (userid, listid)
                VALUES(%d, %d)',
                Config::getTableName('listuser'),
                $user_id,
                $list_id
            )
        );
    }

    /**
     * Subscribe an array of user ids to given list
     * @param $user_ids
     * @param $list_id
     */
    public static function addSubscribers($user_ids, $list_id)
    {
        if (!empty($user_ids)) {
            $query = sprintf(
                'INSERT INTO %s
                (userid, listid, entered, modified)
                VALUES',
                Config::getTableName('listuser')
            );

            foreach ($user_ids as $uid) {
                $query .= sprintf('(%d, %d, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),', $uid, $list_id);
            }
            $query = rtrim($query, ',') . ';';
            phpList::DB()->query($query);
        }
    }

    /**
     * Unsubscribe given user from given list
     * @param $user_id
     * @param $list_id
     */
    public static function removeSubscriber($user_id, $list_id)
    {
        phpList::DB()->query(
            sprintf(
                'DELETE FROM %s
                WHERE userid = %d
                AND listid = %d',
                Config::getTableName('listuser'),
                $user_id,
                $list_id
            )
        );
    }

    /**
     * Unsubscribe an array of user ids from given list
     * @param $user_ids
     * @param $list_id
     */
    public static function removeSubscribers($user_ids, $list_id)
    {
        if (!empty($user_ids)) {
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE userid IN (%s)
                    AND listid = %d',
                    Config::getTableName('listuser'),
                    implode(',', $user_ids),
                    $list_id
                )
            );
        }
    }

    /**
     * Get available list categories
     * @return array
     */
    public static function getAllCategories()
    {
        $listCategories = Config::get('list_categories');
        $categories = explode(',', $listCategories);
        if (!$categories) {
            $categories = array();
            ## try to fetch them from existing lists
            $req = phpList::DB()->query(
                sprintf(
                    'SELECT DISTINCT category FROM %s
                    WHERE category != "%s" ',
                    Config::getTableName('list')
                )
            );
            while ($row = phpList::DB()->fetchRow($req)) {
                $categories[] = $row[0];
            }
            if (!empty($categories)) {
                Config::setDBConfig('list_categories', join(',', $categories));
            }
        } else {
            foreach ($categories as $key => $val) {
                $categories[$key] = trim($val);
            }
        }

        return $categories;
    }
}