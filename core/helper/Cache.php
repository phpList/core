<?php
/**
 * User: SaWey
 * Date: 18/12/13
 */

namespace phpList\helper;


class Cache {
    /**
     * @var Cache $_instance
     */
    private static $_instance;
    public $page_cache = array();
    public $url_cache = array();
    private $message_cache = array();
    public $linktrack_sent_cache = array();
    public $linktrack_cache = array();

    private function __construct(){}

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

    public static function linktrackSentCache()
    {
        return Cache::instance()->linktrack_sent_cache;
    }

    public static function linktrackCache()
    {
        return Cache::instance()->linktrack_cache;
    }

    /**
     * Get a message from cache, will set it when not available yet
     * @param Campaign $message
     * @return Campaign
     */
    public static function &getCachedCampaign($message)
    {
        if(!isset(Cache::$_instance->message_cache[$message->id])){
            Cache::$_instance->message_cache[$message->id] = $message;
        }
        return Cache::$_instance->message_cache[$message->id];
    }

    /**
     * Check if a message has been cached already
     * @param Campaign $message
     * @return bool
     */
    public static function isCampaignCached($message){
        return isset(Cache::$_instance->message_cache[$message->id]);
    }

    /**
     * Put a message in the cache
     * @param Campaign $message
     */
    private static function setCachedCampaign($message)
    {
        Cache::$_instance->message_cache[$message->id] = $message;
    }



    public static function getPageCache($url, $lastmodified = 0)
    {
        $req = phpList::DB()->fetchRowQuery(sprintf(
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
        $req = phpList::DB()->fetchRowQuery(sprintf(
                'SELECT lastmodified FROM %s
                WHERE url = "%s"',
                Config::getTableName('urlcache'),
                $url
            ));
        return $req[0];
    }

    public static function setPageCache($url, $lastmodified, $content)
    {
        #  if (Config::DEBUG) return;
        phpList::DB()->query(sprintf(
                'DELETE FROM %s
                WHERE url = "%s"',
                Config::getTableName('urlcache'),
                $url
            ));
        phpList::DB()->query(sprintf(
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
        phpList::DB()->query(sprintf(
            'DELETE FROM %s',
            Config::getTableName('urlcache')
            ));
    }

    public static function flushClickTrackCache() {
        if (count(Cache::$_instance->linktrack_sent_cache) == 0) return;
        foreach (Cache::$_instance->linktrack_sent_cache as $mid => $numsent) {
            foreach ($numsent as $fwdid => $fwdtotal) {
                //TODO: change output function
                if (Config::VERBOSE){
                    Output::output("Flushing clicktrack stats for $mid: $fwdid => $fwdtotal");
                }
                phpList::DB()->query(sprintf(
                        'UPDATE %s SET total = %d
                        WHERE messageid = %d
                        AND forwardid = %d',
                        Config::getTableName('linktrack_ml'),
                        $fwdtotal,
                        $mid,
                        $fwdid
                    ));
            }
        }
    }
} 