<?php
/**
 * User: SaWey
 * Date: 17/12/13
 */

namespace phpList;


class Util {
    public static function flushBrowser() {
        ## push some more output to the browser, so it displays things sooner
        for ($i=0;$i<10000; $i++) {
            print ' '."\n";
        }
        flush();
    }

    public static function flushClickTrackCache() {
        //TODO: remove globals and output
        if (!isset($GLOBALS['cached']['linktracksent'])) return;
        foreach ($GLOBALS['cached']['linktracksent'] as $mid => $numsent) {
            foreach ($numsent as $fwdid => $fwdtotal) {
                if (Config::VERBOSE)
                    output("Flushing clicktrack stats for $mid: $fwdid => $fwdtotal");
                phpList::DB()->Sql_Query(sprintf(
                    'UPDATE %s SET total = %d
                    WHERE messageid = %d
                    AND forwardid = %d',
                    Config::getTableName('linktrack_ml'), $fwdtotal, $mid, $fwdid));
            }
        }
    }

    public static function cl_output($message) {
        if (Config::get('commandline')) {
            @ob_end_clean();
            print Config::get('installation_name').' - '.strip_tags($message) . "\n";
            @ob_start();
        }
    }
} 