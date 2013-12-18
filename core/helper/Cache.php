<?php
/**
 * User: SaWey
 * Date: 18/12/13
 */

namespace phpList;


class Cache {
    /**
     * @var Cache $_instance
     */
    private static $_instance;
    public $page_cache = array();
    public $url_cache = array();
    private $message_cache = array();

    private function __construct()
    {
        $this->connect();
    }

    public static function instance()
    {
        if (!Cache::$_instance instanceof self) {
            Cache::$_instance = new self();
        }
        return Cache::$_instance;
    }

    public static function urlCache()
    {
        return Cache::instance()->url_cache;
    }

    /**
     * @param Message $message
     * @return Message
     */
    public static function getMessageFromCache($message)
    {
        if(!isset(Cache::$_instance->message_cache[$message->id])){
            Cache::$_instance->message_cache[$message->id] = $message;
        }
        return Cache::$_instance->message_cache[$message->id];
    }

    /**
     * Put a message in the cache
     * @param Message $message
     */
    public static function setMessageCache($message)
    {
        Cache::$_instance->message_cache[$message->id] = $message;
    }

    public static function getPageCache($url, $lastmodified = 0)
    {
        $req = phpList::DB()->Sql_Fetch_Row_Query(sprintf(
                'SELECT content FROM %s
                WHERE url = "%s"
                AND lastmodified >= %d',
                Config::getTableName('urlcache'),
                $url,
                $lastmodified
            ));
        return $req[0];
    }

    public static function getPageCacheLastModified($url)
    {
        $req = phpList::DB()->Sql_Fetch_Row_Query(sprintf(
                'SELECT lastmodified FROM %s
                WHERE url = "%s"',
                Config::getTableName('urlcache'),
                $url
            ));
        return $req[0];
    }

    public static function setPageCache($url, $lastmodified, $content)
    {
        #  if (isset($GLOBALS['developer_email'])) return;
        phpList::DB()->Sql_Query(sprintf(
                'DELETE FROM %s
                WHERE url = "%s"',
                Config::getTableName('urlcache'),
                $url
            ));
        phpList::DB()->Sql_Query(sprintf(
                'INSERT INTO %s (url,lastmodified,added,content)
                VALUES("%s",%d,CURRENT_TIMESTAMP,"%s")',
                Config::getTableName('urlcache'),
                $url,
                $lastmodified,
                addslashes($content)
            ));
    }

    public static function clearPageCache()
    {
        phpList::DB()->Sql_Query(sprintf(
            'DELETE FROM %s',
            Config::getTableName('urlcache')
            ));
    }
} 