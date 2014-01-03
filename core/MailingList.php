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

    public function __construct()
    {
    }

    private static function listFromArray($array)
    {
        $list = new MailingList();
        $list->id = $array['id'];
        $list->name = $array['name'];
        $list->description = $array['description'];
        $list->entered = $array['entered'];
        $list->listorder = $array['listorder'];
        $list->prefix = $array['prefix'];
        $list->rssfeed = $array['rssfeed'];
        $list->modified = $array['modified'];
        $list->active = $array['active'];
        $list->owner = $array['owner'];
        $list->category = $array['category'];
        return $list;
    }

    public static function getAllLists()
    {
        $db_result = phpList::DB()->query(
            sprintf('SELECT * FROM %s %s', Config::getTableName('list'), Config::get('subselect', ''))
        );
        return MailingList::makeLists($db_result);
    }

    public static function getListsFromOwner($owner_id, $id = 0)
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

    public static function getList($id)
    {
        $result = phpList::DB()->fetchAssocQuery(
            sprintf(
                'SELECT id FROM %s
                WHERE id = %d',
                Config::getTableName('list'),
                $id
            )
        );
        return MailingList::listFromArray($result);
    }

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

    public function save()
    {
        if ($this->id != 0) {
            $this->update();
        } else {
            $query = sprintf(
                'INSERT INTO %s
                (name, description, entered, listorder, owner, prefix, active)
                VALUES("%s", "%s", CURRENT_TIMESTAMP, %d, %d, "%s", %d)',
                Config::getTableName('list'),
                $this->name,
                $this->description,
                $this->listorder,
                $this->owner,
                $this->prefix,
                $this->active
            );

            phpList::DB()->query($query);
            $this->id = phpList::DB()->insertedId();
        }
    }

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

    public static function addSubscribers($users, $list_id)
    {
        if (!empty($users)) {
            $query = sprintf(
                'INSERT INTO %s
                (userid, listid, entered, modified)
                VALUES',
                Config::getTableName('listuser')
            );

            foreach ($users as $uid) {
                $query .= sprintf('(%d, %d, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),', $uid, $list_id);
            }
            $query = rtrim($query, ',') . ';';
            phpList::DB()->query($query);
        }
    }

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

    public static function removeSubscribers($users, $list_id)
    {
        if (!empty($users)) {
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE userid IN (%s)
                    AND listid = %d',
                    Config::getTableName('listuser'),
                    implode(',', $users),
                    $list_id
                )
            );
        }
    }

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