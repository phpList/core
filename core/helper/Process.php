<?php
/**
 * User: SaWey
 * Date: 17/12/13
 */

namespace phpList;


class Process {
    public static function getPageLock($page, $force = false) {
        $commandline = Config::get('commandline', false);
        if ($commandline && $page == 'processqueue') {
            //TODO: implement memcached_enabled config
            if (Config::get('memcached_enabled', false)) {
                ## multi-send requires a valid memcached setup
                $max = Config::get('MAX_SENDPROCESSES');
            } else {
                $max = 1;
            }
        } else {
            $max = 1;
        }

        ## allow killing other processes
        if ($force) {
            phpList::DB()->Sql_Query(sprintf(
                'DELETE FROM %s
                WHERE page = "%s"',
                Config::getTableName('sendprocess'), $page));
        }

        $running_processes = self::getRunningProcesses($page);

        if (Config::VERBOSE) {
            cl_output($running_processes['count']. ' out of '.$max.' active processes');
        }
        $waited = 0;
        # while ($running_res['age'] && $count >= $max) { # a process is already running
        while ($running_processes['count'] >= $max) { # don't check age, as it may be 0
            #   cl_output('running process: '.$running_res['age'].' '.$max);
            if ($running_processes['result']['age'] > 600) {# some sql queries can take quite a while
                #cl_output($running_res['id'].' is old '.$running_res['age']);
                # process has been inactive for too long, kill it
                phpList::DB()->Sql_query(sprintf(
                    'UPDATE %s SET alive = 0
                    WHERE id = %d',
                    Config::getTableName('sendprocess'), $running_processes['result']['id']));
            } elseif ((int)$running_processes['count'] >= (int)$max) {
                #   cl_output (sprintf($GLOBALS['I18N']->get('A process for this page is already running and it was still alive %s seconds ago'),$running_res['age']));
                //TODO:change this output function call to something usefull in this rewrite
                output (sprintf(s('A process for this page is already running and it was still alive %s seconds ago'),$running_processes['result']['age']),0);
                sleep(1); # to log the messages in the correct order
                if ($commandline) {
                    cl_output(s('Running commandline, quitting. We\'ll find out what to do in the next run.'));
                    exit;
                }
                output (s('Sleeping for 20 seconds, aborting will quit'),0);
                flush();
                ignore_user_abort(0);
                sleep(20);
            }
            $waited++;
            if ($waited > 10) {
                # we have waited 10 cycles, abort and quit script
                output(s('We have been waiting too long, I guess the other process is still going ok'),0);
                return false;
            }
        }

        $processIdentifier = $_SERVER['REMOTE_ADDR'];
        if ($commandline) {
            $processIdentifier = Config::get('SENDPROCESS_SERVERNAME').':'.getmypid();
        }
        phpList::DB()->Sql_Query(sprintf(
            'INSERT INTO %s (started, page, alive, ipaddress)
            VALUES(CURRENT_TIMESTAMP, "%s", 1, "%s")',
            Config::getTableName('sendprocess'), $page, $processIdentifier));

        $send_process_id = phpList::DB()->Sql_Insert_Id();
        ignore_user_abort(1);
        #  cl_output('Got pagelock '.$send_process_id );
        return $send_process_id;
    }

    private static function getRunningProcesses($page){
        $result = array();
        $running_req = phpList::DB()->Sql_Query(sprintf(
            ' SELECT CURRENT_TIMESTAMP - MODIFIED AS age, id FROM %s
            WHERE page = "%s"
            AND alive > 0
            ORDER BY age DESC',
            Config::getTableName('sendprocess'), $page));
        $result['result'] = phpList::DB()->Sql_Fetch_Assoc($running_req);
        $result['count'] = phpList::DB()->Sql_Num_Rows($running_req);
        return $result;
    }

    public static function keepLock($processid) {
        phpList::DB()->Sql_Query(sprintf(
            'UPDATE %S SET alive = alive + 1
            WHERE id = %d',
            Config::getTableName('sendprocess'), $processid));
    }

    public static function checkLock($processid) {
        $row = phpList::DB()->Sql_Fetch_Row_Query(sprintf(
            'SELECT alive FROM %s WHERE id = %d', Config::getTableName('sendprocess'), $processid));
        return $row[0];
    }

    public static function releaseLock($processid) {
        if (!$processid) return;
        phpList::DB()->Sql_query(
            'DELETE FROM %s
            WHERE id = %d',
            Config::getTableName('sendprocess'), $processid);
    }

} 