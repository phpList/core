<?php
namespace phpList\helper;

use phpList\Campaign;
use phpList\Config;
use phpList\phpList;

class Cache
{
    /**
     * @var Cache $_instance
     */
    private static $_instance;
    public $page_cache = array();
    public $url_cache = array();
    private $campaign_cache = array();
    public $linktrack_sent_cache = array();
    public $linktrack_cache = array();

    private function __construct()
    {
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

    public static function linktrackSentCache()
    {
        return Cache::instance()->linktrack_sent_cache;
    }

    public static function linktrackCache()
    {
        return Cache::instance()->linktrack_cache;
    }

    /**
     * Get a campaign from cache, will set it when not available yet
     * @param Campaign $campaign
     * @return Campaign
     */
    public static function &getCachedCampaign($campaign)
    {
        if (!isset(Cache::$_instance->campaign_cache[$campaign->id])) {
            Cache::$_instance->campaign_cache[$campaign->id] = $campaign;
        }
        return Cache::$_instance->campaign_cache[$campaign->id];
    }

    /**
     * Check if a campaign has been cached already
     * @param Campaign $campaign
     * @return bool
     */
    public static function isCampaignCached($campaign)
    {
        return isset(Cache::$_instance->campaign_cache[$campaign->id]);
    }

    /**
     * Put a campaign in the cache
     * @param Campaign $campaign
     */
    private static function setCachedCampaign($campaign)
    {
        Cache::$_instance->campaign_cache[$campaign->id] = $campaign;
    }



    public static function getPageCache($url, $lastmodified = 0)
    {
        $result = phpList::DB()->query(sprintf(
            'SELECT content FROM %s
                WHERE url = "%s"
                AND lastmodified >= %d',
            Config::getTableName('urlcache'),
            $url,
            $lastmodified
        ));
        return $result->fetchColumn(0);
    }

    public static function getPageCacheLastModified($url)
    {
        $result = phpList::DB()->query(sprintf(
            'SELECT lastmodified FROM %s
                WHERE url = "%s"',
            Config::getTableName('urlcache'),
            $url
        ));
        return $result->fetchColumn(0);
    }

    public static function setPageCache($url, $lastmodified, $content)
    {
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

    public static function flushClickTrackCache()
    {
        if (count(Cache::$_instance->linktrack_sent_cache) == 0) {
            return;
        }
        foreach (Cache::$_instance->linktrack_sent_cache as $mid => $numsent) {
            foreach ($numsent as $fwdid => $fwdtotal) {
                if (Config::VERBOSE) {
                    phpList::log()->debug("Flushing clicktrack stats for $mid: $fwdid => $fwdtotal", ['page' => 'cache']);
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
