<?php
/**
 * User: SaWey
 * Date: 17/12/13
 */

namespace phpList\helper;


class Logger
{
    private static $_instance;
    private $report;

    private function __construct()
    {
    }

    public static function Instance()
    {
        if (!Logger::$_instance instanceof self) {
            Logger::$_instance = new self();
        }
        return Logger::$_instance;
    }

    public static function logEvent($campaign, $page = 'unknown page')
    {
        /*
         * TODO: enable plugins
        $logged = false;
        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $logged = $logged || $plugin->logEvent($campaign);
        }
        if ($logged) return;
        */
        //Sander: Don't think below check is needed
        /*if (!phpList::DB()->Sql_Table_Exists(Config::getTableName('eventlog'))) {
            return;
        }*/

        @phpList::DB()->query(
            sprintf(
                'INSERT INTO %s (entered,page,entry)
                VALUES(CURRENT_TIMESTAMP, "%s", "%s")',
                Config::getTableName('eventlog', $page, $campaign)
            ),
            1
        );
    }

    public static function addToReport($text)
    {
        Logger::$_instance->report .= "\n$text";
    }

    public static function getReport()
    {
        return Logger::$_instance->report;
    }


} 