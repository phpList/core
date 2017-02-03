<?php
namespace phpList\helper;

use phpList\Config;
use phpList\phpList;

class Process
{
    public static function getPageLock($page, $force = false)
    {
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
            phpList::DB()->query(
                sprintf(
                    'DELETE FROM %s
                    WHERE page = "%s"',
                    Config::getTableName('sendprocess'),
                    $page
                )
            );
        }

        $running_processes = self::getRunningProcesses($page);

        phpList::log()->info($running_processes['count'] . ' out of ' . $max . ' active processes', ['page' => 'process']);
        $waited = 0;
        while ($running_processes['count'] >= $max) { # don't check age, as it may be 0
            if ($running_processes['result']['age'] > 600) { # some sql queries can take quite a while
                # process has been inactive for too long, kill it
                phpList::DB()->query(
                    sprintf(
                        'UPDATE %s SET alive = 0
                        WHERE id = %d',
                        Config::getTableName('sendprocess'),
                        $running_processes['result']['id']
                    )
                );
            } elseif ((int)$running_processes['count'] >= (int)$max) {
                phpList::log()->log(
                    sprintf(
                        s('A process for this page is already running and it was still alive %s seconds ago'),
                        $running_processes['result']['age']
                    ),
                    ['page' => 'process']
                );

                sleep(1); # to log the messages in the correct order
                if ($commandline) {
                    phpList::log()->info(s('Running commandline, quitting. We\'ll find out what to do in the next run.'), ['page' => 'process']);
                    exit;
                }
                phpList::log()->info(s('Sleeping for 20 seconds, aborting will quit'), ['page' => 'process']);
                flush();
                ignore_user_abort(0);
                sleep(20);
            }
            $waited++;
            if ($waited > 10) {
                # we have waited 10 cycles, abort and quit script
                phpList::log()->info(s('We have been waiting too long, I guess the other process is still going ok'), ['page' => 'process']);
                return false;
            }
        }

        $processIdentifier = $_SERVER['REMOTE_ADDR'];
        if ($commandline) {
            $processIdentifier = Config::get('SENDPROCESS_SERVERNAME') . ':' . getmypid();
        }
        phpList::DB()->query(
            sprintf(
                'INSERT INTO %s (started, page, alive, ipaddress)
                VALUES(CURRENT_TIMESTAMP, "%s", 1, "%s")',
                Config::getTableName('sendprocess'),
                $page,
                $processIdentifier
            )
        );

        $send_process_id = phpList::DB()->insertedId();
        ignore_user_abort(1);
        return $send_process_id;
    }

    private static function getRunningProcesses($page)
    {
        $running_processes = array();
        $result = phpList::DB()->query(
            sprintf(
                ' SELECT CURRENT_TIMESTAMP - MODIFIED AS age, id FROM %s
                WHERE page = "%s"
                AND alive > 0
                ORDER BY age DESC',
                Config::getTableName('sendprocess'),
                $page
            )
        );
        $running_processes['result'] = $result->fetch(\PDO::FETCH_ASSOC);
        $running_processes['count'] = $result->rowCount();
        return $running_processes;
    }

    public static function keepLock($processid)
    {
        phpList::DB()->query(
            sprintf(
                'UPDATE %S SET alive = alive + 1
                WHERE id = %d',
                Config::getTableName('sendprocess'),
                $processid
            )
        );
    }

    public static function checkLock($processid)
    {
        $row = phpList::DB()->query(
            sprintf(
                'SELECT alive FROM %s WHERE id = %d',
                Config::getTableName('sendprocess'),
                $processid
            )
        );
        return $row->fetchColumn(0);
    }

    public static function releaseLock($processid)
    {
        if (!$processid) {
            return;
        }
        phpList::DB()->query(
            'DELETE FROM %s
            WHERE id = %d',
            Config::getTableName('sendprocess'),
            $processid
        );
    }
}
