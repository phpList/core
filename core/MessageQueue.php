<?php
/**
 * User: SaWey
 * Date: 17/12/13
 */

namespace phpList;


class MessageQueue
{
    private $status = 'OK';
    /**
     * @var Timer
     */
    private $timer;
    private $domainthrottle = array();
    public $messageid;

    public $script_stage = 0;
    public $reload = false;
    public $report;
    public $send_process_id;
    public $nothingtodo;
    public $invalid;
    public $processed;
    public $failed_sent;
    public $notsent;
    public $sent;
    public $unconfirmed;
    public $num_per_batch;
    public $batch_period;
    public $counters;
    public $original_num_per_batch;

    function __construct()
    {
    }

    //TODO: should we need an option to not output text and send it somewhere else (e.g. a file)
    public function process($message_id, $force = false, $reload = false, $cmd_max = 0)
    {
        $this->timer = new timer();
        $this->messageid = $message_id;

        $commandline = Config::get('commandline', false);
        if ($commandline && $force) {
            # force set, so kill other processes
            Util::cl_output('Force set, killing other send processes');
        }
        $send_process_id = Process::getPageLock('processqueue', $force);

        if (empty($send_process_id)) {
            return false;
        }
        #Util::cl_output('page locked on '.$send_process_id);
        $this->reload = $reload;

        //TODO: enable plugins
        /*foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $plugin->processQueueStart();
        }*/

        ## let's make sure all subscribers have a uniqid
        ## only when on CL
        if ($commandline) {
            $num = User::checkUniqueIds();
            if ($num) {
                Util::cl_output('Given a Unique ID to ' . $num . ' subscribers, this might have taken a while');
            }
        }

        $this->num_per_batch = 0;
        $this->batch_period = 0;
        $someusers = /*$skipped =*/ 0;

        $maxbatch = -1;
        $minbatchperiod = -1;
        # check for batch limits
        $ISPrestrictions = '';
        $ISPlockfile = '';
        $user_attribute_query = '';
        $lastsent = !empty($_GET['lastsent']) ? sprintf('%d', $_GET['lastsent']) : 0;
        $lastskipped = !empty($_GET['lastskipped']) ? sprintf('%d', $_GET['lastskipped']) : 0;

        if ($fp = @fopen('/etc/phplist.conf', 'r')) {
            $contents = fread($fp, filesize('/etc/phplist.conf'));
            fclose($fp);
            $lines = explode("\n", $contents);
            $ISPrestrictions = s('The following restrictions have been set by your ISP:') . "\n";
            foreach ($lines as $line) {
                list($key, $val) = explode("=", $line);

                switch ($key) {
                    case "maxbatch":
                        $maxbatch = sprintf('%d', $val);
                        $ISPrestrictions .= "$key = $val\n";
                        break;
                    case "minbatchperiod":
                        $minbatchperiod = sprintf('%d', $val);
                        $ISPrestrictions .= "$key = $val\n";
                        break;
                    case "lockfile":
                        $ISPlockfile = $val;
                }
            }
        }

        if (Config::MAILQUEUE_BATCH_SIZE) {
            if ($maxbatch > 0) {
                $this->num_per_batch = min(Config::MAILQUEUE_BATCH_SIZE, $maxbatch);
            } else {
                $this->num_per_batch = sprintf('%d', Config::MAILQUEUE_BATCH_SIZE);
            }
        } else {
            if ($maxbatch > 0) {
                $this->num_per_batch = $maxbatch;
            }
        }

        if (Config::MAILQUEUE_BATCH_PERIOD) {
            if ($minbatchperiod > 0) {
                $batch_period = max(Config::MAILQUEUE_BATCH_PERIOD, $minbatchperiod);
            } else {
                $batch_period = Config::MAILQUEUE_BATCH_PERIOD;
            }
        }

        ## force batch processing in small batches when called from the web interface
        /*
         * bad idea, we shouldn't touch the batch settings, in case they are very specific for
         * ISP restrictions, instead limit webpage processing by time (below)
         *
        if (empty($GLOBALS['commandline'])) {
          $this->num_per_batch = min($this->num_per_batch,100);
          $batch_period = max($batch_period,1);
        } elseif (isset($cline['m'])) {
          $cl_num_per_batch = sprintf('%d',$cline['m']);
          ## don't block when the param is not a number
          if (!empty($cl_num_per_batch)) {
            $this->num_per_batch = $cl_num_per_batch;
          }
          Util::cl_output("Batch set with commandline to $this->num_per_batch");
        }
        */
        $maxProcessQueueTime = 0;
        if (Config::MAX_PROCESSQUEUE_TIME > 0) {
            $maxProcessQueueTime = (int)Config::MAX_PROCESSQUEUE_TIME;
        }
        # in-page processing force to a minute max, and make sure there's a batch size
        if (!$commandline) {
            $maxProcessQueueTime = min($maxProcessQueueTime, 60);
            if ($this->num_per_batch <= 0) {
                $this->num_per_batch = 10000;
            }
        }

        if (Config::VERBOSE && $maxProcessQueueTime) {
            $this->output(s('Maximum time for queue processing') . ': ' . $maxProcessQueueTime, 'progress');
        }

        if ($cmd_max > 0) {
            Util::cl_output('Max to send is ' . $cmd_max . ' num per batch is ' . $this->num_per_batch);
            $clinemax = (int)$cmd_max;
            ## slow down just before max
            if ($clinemax < 20) {
                $this->num_per_batch = min(2, $clinemax, $this->num_per_batch);
            } elseif ($clinemax < 200) {
                $this->num_per_batch = min(20, $clinemax, $this->num_per_batch);
            } else {
                $this->num_per_batch = min($clinemax, $this->num_per_batch);
            }
            Util::cl_output('Max to send is ' . $cmd_max . ' setting num per batch to ' . $this->num_per_batch);
        }

        $safemode = false;
        if (ini_get('safe_mode')) {
            # keep an eye on timeouts
            $safemode = true;
            $this->num_per_batch = min(100, $this->num_per_batch);
            //TODO: remove print below
            print s('Running in safe mode') . '<br/>';
        }

        $this->original_num_per_batch = $this->num_per_batch;
        if ($this->num_per_batch && $batch_period) {
            # check how many were sent in the last batch period and subtract that
            # amount from this batch
            /*
              $this->output(sprintf('select count(*) from %s where entered > date_sub(current_timestamp,interval %d second) and status = "sent"',
                $tables["usermessage"],$batch_period));
            */
            $recently_sent = phpList::DB()->fetchRowQuery(
                sprintf(
                    'SELECT COUNT(*) FROM %s
                    WHERE entered > date_sub(CURRENT_TIMESTAMP,INTERVAL %d second)
                    AND status = "sent"',
                    Config::getTableName('usermessage'),
                    $batch_period
                )
            );

            Util::cl_output('Recently sent : ' . $recently_sent[0]);
            $this->num_per_batch -= $recently_sent[0];

            # if this ends up being 0 or less, don't send anything at all
            if ($this->num_per_batch == 0) {
                $this->num_per_batch = -1;
            }
        }
        # output some stuff to make sure it's not buffered in the browser
        //TODO: remove print below
        for ($i = 0; $i < 10000; $i++) {
            print '  ';
            if ($i % 100 == 0) {
                print "\n";
            }
        }
        print '<style type="text/css" src="css/app.css"></style>';
        print '<style type="text/css" src="ui/' . Config::get('ui') . '/css/style.css"></style>';
        print '<script type="text/javascript" src="js/' . Config::get('jQuery') . '"></script>';
        ## not sure this works, but would be nice
        print '<script type="text/javascript">$("#favicon").attr("href","images/busy.gif");</script>';

        flush();
        # report keeps track of what is going on
        $this->nothingtodo = false;

        register_shutdown_function(array(&$this, 'shutdown'));

        # we don not want to timeout or abort
        ignore_user_abort(1);
        set_time_limit(600);
        flush();

        if (!$this->reload) { ## only show on first load
            $this->output(s('Started'), 0);
            if (Config::get('SYSTEM_TIMEZONE') != '') {
                $this->output(s('Time now ') . date('Y-m-d H:i'));
            }
        }

        #output('Will process for a maximum of '.$maxProcessQueueTime.' seconds '.MAX_PROCESSQUEUE_TIME);

        # check for other processes running
        if (empty($this->send_process_id)) {
            $this->send_process_id = Process::getPageLock('processqueue');
        }

        if (!$this->send_process_id) {
            $this->output(s('Unable get lock for processing'));
            $this->status = s('Error processing');
            return false;
        }
        if (empty($this->reload)) { ## only show on first load
            if (!empty($ISPrestrictions)) {
                $this->output($ISPrestrictions);
            }
            if (is_file($ISPlockfile)) {
                $this->processError(s('Processing has been suspended by your ISP, please try again later'), 1);
            }
        }

        if ($this->num_per_batch > 0) {
            if ($safemode) {
                $this->output(s('In safe mode, batches are set to a maximum of 100'));
            }
            if ($this->original_num_per_batch != $this->num_per_batch) {
                if (empty($reload)) {
                    $this->output(s('Sending in batches of %d messages', $this->original_num_per_batch), 0);
                }
                $diff = $this->original_num_per_batch - $this->num_per_batch;
                if ($diff < 0) $diff = 0;
                $this->output(
                    s(
                        'This batch will be %d emails, because in the last %d seconds %d emails were sent',
                        $this->num_per_batch,
                        $this->batch_period,
                        $diff
                    ),
                    0,
                    'progress'
                );
            } else {
                $this->output(s('Sending in batches of %d emails', $this->num_per_batch), 0, 'progress');
            }
        } elseif ($this->num_per_batch < 0) {
            $this->output(
                s(
                    'In the last %d seconds more emails were sent (%d) than is currently allowed per batch (%d)',
                    $this->batch_period,
                    $recently_sent[0],
                    $this->original_num_per_batch
                ),
                0,
                'progress'
            );
            $this->processed = 0;
            $this->script_stage = 5;
            Config::setRunningConfig('wait', $this->batch_period);
            return;
        }
        $$this->counters['batch_total'] = $this->num_per_batch;

        if (0 && $reload) {
            $this->output(s('Sent in last run') . ": $lastsent", 0, 'progress');
            $this->output(s('Skipped in last run') . ": $lastskipped", 0, 'progress');
        }

        $this->script_stage = 1; # we are active
        $notsent = $sent = $invalid = $unconfirmed = $cannotsend = 0;

        ## check for messages that need requeuing
        Message::checkMessagesToRequeue();

        if (Config::VERBOSE) {
            $this->output(phpList::DB()->getLastQuery());
        }

        $messages = Message::getMessagesToQueue();
        $num_messages = count($messages);

        if ($num_messages) {
            if (empty($this->reload)) {
                $this->output(s('Processing has started,') . ' ' . $num_messages . ' ' . s('message(s) to process.'));
            }
            clearPageCache();
            if (!Config::get('commandline', false) && empty($this->reload)) {
                if (!$safemode) {
                    $this->output(
                        s(
                            'Please leave this window open. You have batch processing enabled, so it will reload several times to send the messages. Reports will be sent by email to'
                        ) . ' ' . getConfig("report_address")
                    );
                } else {
                    $this->output(
                        s(
                            'Your webserver is running in safe_mode. Please keep this window open. It may reload several times to make sure all messages are sent.'
                        ) . ' ' . s('Reports will be sent by email to') . ' ' . getConfig("report_address")
                    );
                }
            }
        }

        $this->script_stage = 2; # we know the messages to process
        #include_once "footer.inc";
        if (!isset($this->num_per_batch)) {
            $this->num_per_batch = 1000000;
        }
        /**
         * @var $message Message
         */
        foreach ($messages as $message) {
            $this->counters['campaign']++;
            $failed_sent = 0;
            $throttlecount = 0;

            $this->counters['total_users_for_message ' . $message->id] = 0;
            $this->counters['processed_users_for_message ' . $message->id] = 0;

            if (!empty($getspeedstats)) $this->output('start send ' . $message->id);

            /*
             * TODO: enable plugins
            foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                $plugin->campaignStarted($msgdata);
            }*/

            if ($message->resetstats == 1) {
                $message->resetMessageStatistics();
                ## make sure to reset the resetstats flag, so it doesn't clear it every run
                $message->setDataItem('resetstats', 0);
            }

            ## check the end date of the campaign
            $stopSending = false;
            //if (!empty($message->'finishsending')) {
            $finishSendingBefore = $message->finishsending->getTimestamp();
            $secondsTogo = $finishSendingBefore - time();
            $stopSending = $secondsTogo < 0;
            if (empty($this->reload)) {
                ### Hmm, this is probably incredibly confusing. It won't finish then
                if (Config::VERBOSE) {
                    $this->output(
                        sprintf(
                            s('sending of this campaign will stop, if it is still going in %s'),
                            Util::secs2time($secondsTogo)
                        )
                    );
                }
            }
            //}

            $userselection = $message->userselection; ## @@ needs more work
            ## load message in cache
            if (!PrepareMessage::precacheMessage($message)) {
                ## precache may fail on eg invalid remote URL
                ## any reporting needed here?

                # mark the message as suspended
                phpList::DB()->Sql_Query(sprintf(
                        'UPDATE %s SET status = "suspended"
                        WHERE id = %d',
                        Config::getTableName('message'),
                        $message->id
                    )
                );
                $this->output(s('Error loading message, please check the eventlog for details'));
                if (Config::MANUALLY_PROCESS_QUEUE) {
                    # wait a little, otherwise the message won't show
                    sleep(10);
                }
                continue;
            }

            if (!empty($getspeedstats)) $this->output('message data loaded ');
            //if (Config::VERBOSE) {
                //   $this->output($msgdata);
            //}
            if (!empty($message->notify_start) && !isset($message->start_notified)) {
                $notifications = explode(',', $message->notify_start);
                foreach ($notifications as $notification) {
                    phpListMailer::sendMail(
                        $notification,
                        s('Campaign started'),
                        sprintf(
                            s('phplist has started sending the campaign with subject %s'),
                            $message->subject . "\n\n" .
                            sprintf(
                                s('to view the progress of this campaign, go to http://%s'),
                                Config::get('website') . Config::get('adminpages') . '/?page=messages&amp;tab=active'
                            )
                        )
                    );
                }
                //TODO: should probably move to Message class
                phpList::DB()->Sql_Query(sprintf(
                        'INSERT IGNORE INTO %s (name,id,data)
                        VALUES("start_notified",%d,CURRENT_TIMESTAMP)',
                        Config::getTableName('messagedata'),
                        $message->id
                    )
                );
            }

            if (empty($this->reload)) {
                $this->output(s('Processing message') . ' ' . $message->id);
            }

            flush();
            Process::keepLock($this->send_process_id);
            $message->setStatus('inprocess');

            if (empty($this->reload)) {
                $this->output(s('Looking for users'));
            }
            if (phpList::DB()->hasError()) {
                $this->processError(phpList::DB()->error());
            }

            # make selection on attribute, users who at least apply to the attributes
            # lots of ppl seem to use it as a normal mailinglist system, and do not use attributes.
            # Check this and take anyone in that case.

            ## keep an eye on how long it takes to find users, and warn if it's a long time
            $findUserStart = $this->timer->elapsed(true);

            $numattr = phpList::DB()->fetchRowQuery(sprintf(
                    'SELECT COUNT(*) FROM %s',
                    Config::getTableName('attribute')
                ));

            $user_attribute_query = ''; #16552
            if ($userselection && $numattr[0]) {
                $res = phpList::DB()->query($userselection);
                $this->counters['total_users_for_message'] = phpList::DB()->numRows($res);
                if (empty($this->reload)) {
                    $this->output(
                        $this->counters['total_users_for_message'] . ' ' . s(
                            'users apply for attributes, now checking lists'
                        ),
                        0,
                        'progress'
                    );
                }
                $user_list = '';
                while ($row = phpList::DB()->fetchRow($res)) {
                    $user_list .= $row[0] . ",";
                }
                $user_list = substr($user_list, 0, -1);
                if ($user_list)
                    $user_attribute_query = " AND listuser.userid IN ($user_list)";
                else {
                    if (empty($this->reload)) {
                        $this->output(s('No users apply for attributes'));
                    }
                    $message->setStatus('sent');
                    //finish("info", "Message $messageid: \nNo users apply for attributes, ie nothing to do");
                    $subject = s("Maillist Processing info");
                    if (!$this->nothingtodo) {
                        $this->output(s('Finished this run'), 1, 'progress');
                        printf(
                            '<script type="text/javascript">
                                var parentJQuery = window.parent.jQuery;
                                parentJQuery("#progressmeter").updateSendProgress("%s,%s");
                             </script>',
                            $this->sent,
                            $this->counters['total_users_for_message ' . $this->messageid]
                        );
                    }
                    //TODO:enable plugins
                    /*
                    if (!Config::TEST && !$this->nothingtodo && Config::get(('END_QUEUE_PROCESSING_REPORT'))) {
                        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                            $plugin->sendReport($subject,"Message $message->id: \nNo users apply for attributes, ie nothing to do");
                        }
                    }
                    */
                    $this->script_stage = 6;
                    # we should actually continue with the next message
                    return;
                }
            }
            if ($this->script_stage < 3)
                $this->script_stage = 3; # we know the users by attribute

            # when using commandline we need to exclude users who have already received
            # the email
            # we don't do this otherwise because it slows down the process, possibly
            # causing us to not find anything at all
            $exclusion = '';
            $doneusers = array();
            $skipusers = array();

            # 8478, avoid building large array in memory, when sending large amounts of users.

            /*
              $req = Sql_Query("select userid from {$tables["usermessage"]} where messageid = $messageid");
              $skipped = Sql_Affected_Rows();
              if ($skipped < 10000) {
                while ($row = Sql_Fetch_Row($req)) {
                  $alive = checkLock($send_process_id);
                  if ($alive)
                    keepLock($send_process_id);
                  else
                    ProcessError(s('Process Killed by other process'));
                  array_push($doneusers,$row[0]);
                }
              } else {
                $this->output(s('Warning, disabling exclusion of done users, too many found'));
                logEvent(s('Warning, disabling exclusion of done users, too many found'));
              }

              # also exclude unconfirmed users, otherwise they'll block the process
              # will give quite different statistics than when used web based
            #  $req = Sql_Query("select id from {$tables["user"]} where !confirmed");
            #  while ($row = Sql_Fetch_Row($req)) {
            #    array_push($doneusers,$row[0]);
            #  }
              if (sizeof($doneusers))
                $exclusion = " and listuser.userid not in (".join(",",$doneusers).")";
            */

            if (Config::USE_LIST_EXCLUDE) {
                if (Config::VERBOSE) {
                    $this->output(s('looking for users who can be excluded from this mailing'));
                }
                if (!empty($message->excludelist)) {
                    $message->excludeUsersOnList($message->excludelist);
                    /*if (Config::VERBOSE) {
                        $this->output('Exclude query ' . phpList::DB()->getLastQuery());
                    }*/

                }
            }

            /*
              ## 8478
              $query = sprintf('select distinct user.id from
                %s as listuser,
                %s as user,
                %s as listmessage
                where
                listmessage.messageid = %d and
                listmessage.listid = listuser.listid and
                user.id = listuser.userid %s %s %s',
                $tables['listuser'],$tables["user"],$tables['listmessage'],
                $messageid,
                $userconfirmed,
                $exclusion,
                $user_attribute_query);*/
            $queued = 0;
            if (Config::MESSAGEQUEUE_PREPARE) {
                /*## we duplicate messageid to match the query_params or the main query
                TODO: @michiel: I don't get the point of having messageid twice?
                $query = sprintf(
                    'select userid from ' . $tables['usermessage'] . ' where messageid = ? and messageid = ? and status = "todo"'
                );
                #   cl_output($query.' '.$messageid);
                $queued_count = Sql_Query_Params($query, array($messageid, $messageid));
                $queued = Sql_Affected_Rows();
                # if (VERBOSE) {
                cl_output('found pre-queued subscribers ' . $queued, 0, 'progress');
                #  }*/
                phpList::DB()->query(sprintf(
                        'SELECT userid FROM %s
                        WHERE messageid = %d
                        AND status = "todo"',
                        Config::getTableName('usermessage'),
                        $message->id
                    ));
                $queued = phpList::DB()->affectedRows();
                # if (Config::VERBOSE) {
                cl_output('found pre-queued subscribers ' . $queued, 0, 'progress');
            }

            ## if the above didn't find any, run the normal search (again)
            if (empty($queued)) {
                ## remove pre-queued messages, otherwise they wouldn't go out
                phpList::DB()->query(sprintf(
                        'DELETE FROM %s
                        WHERE messageid = %d
                        AND status = "todo"',
                        Config::getTableName('usermessage'),
                        $message->id
                    ));

                $removed = phpList::DB()->affectedRows();
                if ($removed) {
                    cl_output('removed pre-queued subscribers ' . $removed, 0, 'progress');
                }

                $query = sprintf(
                    'SELECT DISTINCT u.id FROM %s AS listuser
                            CROSS JOIN %s AS u
                            CROSS JOIN %s AS listmessage
                            LEFT JOIN %s AS um
                              ON (um.messageid = %d AND um.userid = listuser.userid)
                        WHERE true
                          AND listmessage.messageid = %d
                          AND listmessage.listid = listuser.listid
                          AND u.id = listuser.userid
                          AND um.userid IS NULL
                          AND u.confirmed and !u.blacklisted and !u.disabled
                        %s %s',
                    Config::getTableName('listuser'),
                    Config::getTableName('user'),
                    Config::getTableName('listmessage'),
                    Config::getTableName('usermessage'),
                    $exclusion,
                    $user_attribute_query
                );
            }

            if (Config::VERBOSE) {
                $this->output('User select query ' . $query);
            }

            $userids = phpList::DB()->query($query);
            if (phpList::DB()->hasError()) {
                $this->processError(phpList::DB()->error());
            }

            # now we have all our users to send the message to
            $this->counters['total_users_for_message ' . $message->id] = phpList::DB()->numRows($userids);
            /*if ($skipped >= 10000) {
                $this->counters['total_users_for_message ' . $message->id] -= $skipped;
            }*/

            $findUserEnd = $this->timer->elapsed(true);

            if ($findUserEnd - $findUserStart > 300 && Config::get('commandline', false)) {
                $this->output(
                    s(
                        'Warning, finding the subscribers to send out to takes a long time, consider changing to commandline sending'
                    )
                );
            }

            if (empty($this->reload)) {
                $this->output(
                    s('Found them') . ': ' . $this->counters['total_users_for_message ' . $message->id] . ' ' . s(
                        'to process'
                    )
                );
            }
            $message->setDataItem('to process', $this->counters['total_users_for_message ' . $message->id]);

            if (defined('MESSAGEQUEUE_PREPARE') && MESSAGEQUEUE_PREPARE && empty($queued)) {
                ## experimental MESSAGEQUEUE_PREPARE will first mark all messages as todo and then work it's way through the todo's
                ## that should save time when running the queue multiple times, which avoids the user search after the first time
                ## only do this first time, ie empty($queued);
                ## the last run will pick up changes
                while ($userdata = Sql_Fetch_Row($userids)) {
                    ## mark message/user combination as "todo"
                    $userid = $userdata[0]; # id of the user
                    Sql_Replace(
                        $tables['usermessage'],
                        array(
                            'entered' => 'current_timestamp',
                            'userid' => $userid,
                            'messageid' => $message->id,
                            'status' => "todo"
                        ),
                        array(
                            'userid',
                            'messageid'
                        ),
                        false
                    );
                }
                ## rerun the initial query, in order to continue as normal
                $query = sprintf(
                    'select userid from ' . $tables['usermessage'] . ' where messageid = ? and messageid = ? and status = "todo"'
                );
                $userids = Sql_Query_Params($query, array($message->id, $message->id));
                $this->counters['total_users_for_message ' . $message->id] = Sql_Num_Rows($userids);
            }

            if (MAILQUEUE_BATCH_SIZE) {
                ## in case of sending multiple campaigns, reduce batch with "sent"
                $this->num_per_batch -= $sent;

                # send in batches of $this->num_per_batch users
                $batch_total = $this->counters['total_users_for_message ' . $message->id];
                if ($this->num_per_batch > 0) {
                    $query .= sprintf(' limit 0,%d', $this->num_per_batch);
                    if (VERBOSE) {
                        $this->output($this->num_per_batch . '  query -> ' . $query);
                    }
                    $userids = Sql_Query_Params($query, array($message->id, $message->id));
                    if (Sql_Has_Error($database_connection)) {
                        ProcessError(Sql_Error($database_connection));
                    }
                } else {
                    $this->output(s('No users to process for this batch'), 0, 'progress');
                    $userids = Sql_Query("select * from ${tables['user']} where id = 0");
                }
                $affrows = Sql_Num_Rows($userids);
                $this->output(s('Processing batch of ') . ': ' . $affrows, 0, 'progress');
            }

            while ($userdata = Sql_Fetch_Row($userids)) {
                $this->counters['processed_users_for_message ' . $message->id]++;
                $failure_reason = '';
                if ($this->num_per_batch && $sent >= $this->num_per_batch) {
                    $this->output(s('batch limit reached') . ": $sent ($this->num_per_batch)", 1, 'progress');
                    $GLOBALS["wait"] = $batch_period;
                    return;
                }

                $userid = $userdata[0]; # id of the user
                if (!empty($getspeedstats)) $this->output(
                    '-----------------------------------' . "\n" . 'start process user ' . $userid
                );
                $some = 1;
                set_time_limit(120);

                $secondsTogo = $finishSendingBefore - time();
                $stopSending = $secondsTogo < 0;

                # check if we have been "killed"
                #   $this->output('Process ID '.$send_process_id);
                $alive = checkLock($send_process_id);

                ## check for max-process-queue-time
                $elapsed = $GLOBALS['processqueue_timer']->elapsed(1);
                if ($maxProcessQueueTime && $elapsed > $maxProcessQueueTime && $sent > 0) {
                    cl_output(s('queue processing time has exceeded max processing time ') . $maxProcessQueueTime);
                    break;
                } elseif ($alive && !$stopSending) {
                    keepLock($send_process_id);
                } elseif ($stopSending) {
                    $this->output(s('Campaign sending timed out, is past date to process until'));
                    break;
                } else {
                    ProcessError(s('Process Killed by other process'));
                }

                # check if the message we are working on is still there and in process
                $status = Sql_Fetch_Array_query("select id,status from {$tables['message']} where id = $message->id");
                if (!$status['id']) {
                    ProcessError(s('Message I was working on has disappeared'));
                } elseif ($status['status'] != 'inprocess') {
                    ProcessError(s('Sending of this message has been suspended'));
                }
                flush();

                ##
                #Sql_Query_Params(sprintf('delete from %s where userid = ? and messageid = ? and status = "active"',$tables['usermessage']), array($userid,$message->id));

                # check whether the user has already received the message
                if (!empty($getspeedstats)) $this->output('verify message can go out to ' . $userid);

                $um = Sql_query(
                    "select entered from {$tables['usermessage']} where userid = $userid and messageid = $message->id and status != 'todo'"
                );
                if (!Sql_Num_Rows($um)) {
                    ## mark this message that we're working on it, so that no other process will take it
                    ## between two lines ago and here, should hopefully be quick enough
                    $userlock = Sql_Replace(
                        $tables['usermessage'],
                        array(
                            'entered' => 'current_timestamp',
                            'userid' => $userid,
                            'messageid' => $message->id,
                            'status' => "active"
                        ),
                        array(
                            'userid',
                            'messageid'
                        ),
                        false
                    );

                    if ($this->script_stage < 4)
                        $this->script_stage = 4; # we know a subscriber to send to
                    $someusers = 1;
                    $users = Sql_query(
                        "select id,email,uniqid,htmlemail,confirmed,blacklisted,disabled from {$tables['user']} where id = $userid"
                    );

                    # pick the first one (rather historical from before email was unique)
                    $user = Sql_fetch_Assoc($users);
                    if ($user['confirmed'] && is_email($user['email'])) {
                        $userid = $user['id']; # id of the subscriber
                        $useremail = $user['email']; # email of the subscriber
                        $userhash = $user['uniqid']; # unique string of the user
                        $htmlpref = $user['htmlemail']; # preference for HTML emails
                        $confirmed = $user['confirmed'] && !$user['disabled']; ## 7 = disabled flag
                        $blacklisted = $user['blacklisted'];

                        $cansend = !$blacklisted && $confirmed;
                        /*
                        ## Ask plugins if they are ok with sending this message to this user
                        */
                        if (!empty($getspeedstats)) $this->output('start check plugins ');

                        reset($GLOBALS['plugins']);
                        while ($cansend && $plugin = current($GLOBALS['plugins'])) {
                            if (VERBOSE) {
                                cl_output('Checking plugin ' . $plugin->name());
                            }
                            $cansend = $plugin->canSend($msgdata, $user);
                            if (!$cansend) {
                                $failure_reason .= 'Sending blocked by plugin ' . $plugin->name;
                                $this->counters['send blocked by ' . $plugin->name]++;
                                if (VERBOSE) {
                                    cl_output('Sending blocked by plugin ' . $plugin->name);
                                }
                            }

                            next($GLOBALS['plugins']);
                        }
                        if (!empty($getspeedstats)) $this->output('end check plugins ');

####################################
# Throttling

                        $throttled = 0;
                        if ($cansend && USE_DOMAIN_THROTTLE) {
                            list($mailbox, $domainname) = explode('@', $useremail);
                            $now = time();
                            $interval = $now - ($now % DOMAIN_BATCH_PERIOD);
                            if (!isset($domainthrottle[$domainname]) || !is_array($domainthrottle[$domainname])) {
                                $domainthrottle[$domainname] = array(
                                    'interval' => '',
                                    'sent' => 0,
                                    'attempted' => 0,
                                );
                            } elseif (isset($domainthrottle[$domainname]['interval']) && $domainthrottle[$domainname]['interval'] == $interval) {
                                $throttled = $domainthrottle[$domainname]['sent'] >= DOMAIN_BATCH_SIZE;
                                if ($throttled) {
                                    $this->counters['send blocked by domain throttle']++;
                                    $domainthrottle[$domainname]['attempted']++;
                                    if (DOMAIN_AUTO_THROTTLE
                                        && $domainthrottle[$domainname]['attempted'] > 25 # skip a few before auto throttling
                                        && $num_messages <= 1 # only do this when there's only one message to process otherwise the other ones don't get a chance
                                        && $this->counters['total_users_for_message ' . $message->id] < 1000 # and also when there's not too many left, because then it's likely they're all being throttled
                                    ) {
                                        $domainthrottle[$domainname]['attempted'] = 0;
                                        logEvent(
                                            sprintf(
                                                s(
                                                    'There have been more than 10 attempts to send to %s that have been blocked for domain throttling.'
                                                ),
                                                $domainname
                                            )
                                        );
                                        logEvent(s('Introducing extra delay to decrease throttle failures'));
                                        if (VERBOSE) {
                                            $this->output(s('Introducing extra delay to decrease throttle failures'));
                                        }
                                        if (!isset($running_throttle_delay)) {
                                            $running_throttle_delay = (int)(MAILQUEUE_THROTTLE + (DOMAIN_BATCH_PERIOD / (DOMAIN_BATCH_SIZE * 4)));
                                        } else {
                                            $running_throttle_delay += (int)(DOMAIN_BATCH_PERIOD / (DOMAIN_BATCH_SIZE * 4));
                                        }
                                        #output("Running throttle delay: ".$running_throttle_delay);
                                    } elseif (VERBOSE) {
                                        $this->output(
                                            sprintf(
                                                s(
                                                    '%s is currently over throttle limit of %d per %d seconds'
                                                ) . ' (' . $domainthrottle[$domainname]['sent'] . ')',
                                                $domainname,
                                                DOMAIN_BATCH_SIZE,
                                                DOMAIN_BATCH_PERIOD
                                            )
                                        );
                                    }
                                }
                            }
                        }

                        if ($cansend) {
                            $success = 0;
                            if (!TEST) {
                                reset($GLOBALS['plugins']);
                                while (!$throttled && $plugin = current($GLOBALS['plugins'])) {
                                    $throttled = $plugin->throttleSend($msgdata, $user);
                                    if ($throttled) {
                                        if (!isset($this->counters['send throttled by plugin ' . $plugin->name])) {
                                            $this->counters['send throttled by plugin ' . $plugin->name] = 0;
                                        }
                                        $this->counters['send throttled by plugin ' . $plugin->name]++;
                                        $failure_reason .= 'Sending throttled by plugin ' . $plugin->name;
                                    }
                                    next($GLOBALS['plugins']);
                                }
                                if (!$throttled) {
                                    if (VERBOSE)
                                        $this->output(
                                            s('Sending') . ' ' . $message->id . ' ' . s('to') . ' ' . $useremail
                                        );
                                    $emailSentTimer = new timer();
                                    $this->counters['batch_count']++;
                                    $success = sendEmail(
                                        $message->id,
                                        $useremail,
                                        $userhash,
                                        $htmlpref
                                    ); // $rssitems Obsolete by rssmanager plugin
                                    if (!$success) {
                                        $this->counters['sendemail returned false']++;
                                    }
                                    if (VERBOSE) {
                                        $this->output(
                                            s('It took') . ' ' . $emailSentTimer->elapsed(1) . ' ' . s(
                                                'seconds to send'
                                            )
                                        );
                                    }
                                } else {
                                    $throttlecount++;
                                }
                            } else {
                                $success = sendEmailTest($message->id, $useremail);
                            }

                            #############################
                            # tried to send email , process succes / failure
                            if ($success) {
                                if (USE_DOMAIN_THROTTLE) {
                                    list($mailbox, $domainname) = explode('@', $useremail);
                                    if ($domainthrottle[$domainname]['interval'] != $interval) {
                                        $domainthrottle[$domainname]['interval'] = $interval;
                                        $domainthrottle[$domainname]['sent'] = 0;
                                    } else {
                                        $domainthrottle[$domainname]['sent']++;
                                    }
                                }
                                $sent++;
                                $um = Sql_Replace(
                                    $tables['usermessage'],
                                    array(
                                        'entered' => 'current_timestamp',
                                        'userid' => $userid,
                                        'messageid' => $message->id,
                                        'status' => "sent"
                                    ),
                                    array(
                                        'userid',
                                        'messageid'
                                    ),
                                    false
                                );

//obsolete, moved to rssmanager plugin
//            if (ENABLE_RSS && $pxrocessrss) {
//              foreach ($rssitems as $rssitemid) {
//                $status = Sql_query("update {$tables['rssitem']} set processed = processed +1 where id = $rssitemid");
//                $um = Sql_query("replace into {$tables['rssitem_user']} (userid,itemid) values($userid,$rssitemid)");
//              }
//              Sql_Query("replace into {$tables["user_rss"]} (userid,last) values($userid,date_sub(current_timestamp,interval 15 minute))");
//
//              }
                            } else {
                                $failed_sent++;
                                ## need to check this, the entry shouldn't be there in the first place, so no need to delete it
                                ## might be a cause for duplicated emails
                                if (defined('MESSAGEQUEUE_PREPARE') && MESSAGEQUEUE_PREPARE) {
                                    Sql_Query_Params(
                                        sprintf(
                                            'update %s set status = "todo" where userid = ? and messageid = ? and status = "active"',
                                            $tables['usermessage']
                                        ),
                                        array($userid, $message->id)
                                    );
                                } else {
                                    Sql_Query_Params(
                                        sprintf(
                                            'delete from %s where userid = ? and messageid = ? and status = "active"',
                                            $tables['usermessage']
                                        ),
                                        array($userid, $message->id)
                                    );
                                }
                                if (VERBOSE) {
                                    $this->output(s('Failed sending to') . ' ' . $useremail);
                                    logEvent("Failed sending message $message->id to $useremail");
                                }
                                # make sure it's not because it's an underdeliverable email
                                # unconfirm this user, so they're not included next time
                                if (!$throttled && !validateEmail($useremail)) {
                                    $unconfirmed++;
                                    $this->counters['email address invalidated']++;
                                    logEvent("invalid email address $useremail user marked unconfirmed");
                                    Sql_Query(
                                        sprintf(
                                            'update %s set confirmed = 0 where email = "%s"',
                                            $GLOBALS['tables']['user'],
                                            $useremail
                                        )
                                    );
                                }
                            }

                            if ($this->script_stage < 5) {
                                $this->script_stage = 5; # we have actually sent one user
                            }
                            if (isset($running_throttle_delay)) {
                                sleep($running_throttle_delay);
                                if ($sent % 5 == 0) {
                                    # retry running faster after some more messages, to see if that helps
                                    unset($running_throttle_delay);
                                }
                            } elseif (MAILQUEUE_THROTTLE) {
                                usleep(MAILQUEUE_THROTTLE * 1000000);
                            } elseif (MAILQUEUE_BATCH_SIZE && MAILQUEUE_AUTOTHROTTLE) {
                                $totaltime = $GLOBALS['processqueue_timer']->elapsed(1);
                                $msgperhour = (3600 / $totaltime) * $sent;
                                $msgpersec = $msgperhour / 3600;

                                ##11336 - this may cause "division by 0", but 'secpermsg' isn't used at all
                                #  $secpermsg = $totaltime / $sent;
                                $target = (MAILQUEUE_BATCH_PERIOD / MAILQUEUE_BATCH_SIZE) * $sent;
                                $delay = $target - $totaltime;
#             $this->output("Sent: $sent mph $msgperhour mps $msgpersec secpm $secpermsg target $target actual $actual d $delay");

                                if ($delay > 0) {
                                    if (VERBOSE) {
                                        /* $this->output(s('waiting for').' '.$delay.' '.s('seconds').' '.
                                                           s('to make sure we don\'t exceed our limit of ').MAILQUEUE_BATCH_SIZE.' '.
                                                           s('messages in ').' '.MAILQUEUE_BATCH_PERIOD.s('seconds')); */
                                        $this->output(
                                            sprintf(
                                                s('waiting for %.1f seconds to meet target of %s seconds per message'),
                                                $delay,
                                                (MAILQUEUE_BATCH_PERIOD / MAILQUEUE_BATCH_SIZE)
                                            )
                                        );
                                    }
                                    usleep($delay * 1000000);
                                }
                            }
                        } else {
                            $cannotsend++;
                            # mark it as sent anyway, because otherwise the process will never finish
                            if (VERBOSE) {
                                $this->output(s('not sending to ') . $useremail);
                            }
                            $um = Sql_query(
                                "replace into {$tables['usermessage']} (entered,userid,messageid,status) values(current_timestamp,$userid,$message->id,\"not sent\")"
                            );
                        }

                        # update possible other users matching this email as well,
                        # to avoid duplicate sending when people have subscribed multiple times
                        # bit of legacy code after making email unique in the database
#        $emails = Sql_query("select * from {$tables['user']} where email =\"$useremail\"");
#        while ($email = Sql_fetch_row($emails))
#          Sql_query("replace into {$tables['usermessage']} (userid,messageid) values($email[0],$message->id)");
                    } else {
                        # some "invalid emails" are entirely empty, ah, that is because they are unconfirmed

                        ## this is quite old as well, with the preselection that avoids unconfirmed users
                        # it is unlikely this is every processed.

                        if (!$user['confirmed'] || $user['disabled']) {
                            if (VERBOSE)
                                $this->output(
                                    s('Unconfirmed user') . ': ' . $userid . ' ' . $user['email'] . ' ' . $user['id']
                                );
                            $unconfirmed++;
                            # when running from commandline we mark it as sent, otherwise we might get
                            # stuck when using batch processing
                            # if ($GLOBALS["commandline"]) {
                            $um = Sql_query(
                                "replace into {$tables['usermessage']} (entered,userid,messageid,status) values(current_timestamp,$userid,$message->id,\"unconfirmed user\")"
                            );
                            # }
                        } elseif ($user['email'] || $user['id']) {
                            if (VERBOSE) {
                                $this->output(s('Invalid email address') . ': ' . $user['email'] . ' ' . $user['id']);
                            }
                            logEvent(
                                s('Invalid email address') . ': userid  ' . $user['id'] . '  email ' . $user['email']
                            );
                            # mark it as sent anyway
                            if ($user['id']) {
                                $um = Sql_query(
                                    sprintf(
                                        'replace into %s (entered,userid,messageid,status) values(current_timestamp,%d,%d,"invalid email address")',
                                        $tables['usermessage'],
                                        $userid,
                                        $message->id
                                    )
                                );
                                Sql_Query(
                                    sprintf(
                                        'update %s set confirmed = 0 where id = %d',
                                        $GLOBALS['tables']['user'],
                                        $user['id']
                                    )
                                );
                                addUserHistory(
                                    $user['email'],
                                    s(
                                        'Subscriber marked unconfirmed for invalid email address',
                                        s('Marked unconfirmed while sending campaign %d', $message->id)
                                    )
                                );
                            }
                            $invalid++;
                        }
                    }
                } else {

                    ## and this is quite historical, and also unlikely to be every called
                    # because we now exclude users who have received the message from the
                    # query to find users to send to

                    ## when trying to send the message, it was already marked for this user
                    ## June 2010, with the multiple send process extension, that's quite possible to happen again

                    $um = Sql_Fetch_Row($um);
                    $notsent++;
                    if (VERBOSE) {
                        $this->output(
                            s('Not sending to') . ' ' . $userdata[0] . ', ' . s('already sent') . ' ' . $um[0]
                        );
                    }
                }
                $status = Sql_query("update {$tables['message']} set processed = processed + 1 where id = $message->id");
                $processed = $notsent + $sent + $invalid + $unconfirmed + $cannotsend + $failed_sent;
                #if ($processed % 10 == 0) {
                if (0) {
                    $this->output(
                        'AR' . $affrows . ' N ' . $this->counters['total_users_for_message ' . $message->id] . ' P' . $processed . ' S' . $sent . ' N' . $notsent . ' I' . $invalid . ' U' . $unconfirmed . ' C' . $cannotsend . ' F' . $failed_sent
                    );
                    $rn = $reload * $this->num_per_batch;
                    $this->output(
                        'P ' . $processed . ' N' . $this->counters['total_users_for_message ' . $message->id] . ' NB' . $this->num_per_batch . ' BT' . $batch_total . ' R' . $reload . ' RN' . $rn
                    );
                }
                /*
                 * don't calculate this here, but in the "msgstatus" instead, so that
                 * the total speed can be calculated, eg when there are multiple send processes
                 *
                 * re-added for commandline outputting
                 */


                $totaltime = $GLOBALS['processqueue_timer']->elapsed(1);
                if ($sent > 0) {
                    $msgperhour = (3600 / $totaltime) * $sent;
                    $secpermsg = $totaltime / $sent;
                    $timeleft = ($this->counters['total_users_for_message ' . $message->id] - $sent) * $secpermsg;
                    $eta = date('D j M H:i', time() + $timeleft);
                } else {
                    $msgperhour = 0;
                    $secpermsg = 0;
                    $timeleft = 0;
                    $eta = s('unknown');
                }
                setMessageData($message->id, 'ETA', $eta);
                setMessageData($message->id, 'msg/hr', $msgperhour);

                cl_progress('sent ' . $sent . ' ETA ' . $eta . ' sending ' . sprintf('%d', $msgperhour) . ' msg/hr');

                setMessageData(
                    $message->id,
                    'to process',
                    $this->counters['total_users_for_message ' . $message->id] - $sent
                );
                setMessageData($message->id, 'last msg sent', time());
                #  setMessageData($message->id,'totaltime',$GLOBALS['processqueue_timer']->elapsed(1));
                if (!empty($getspeedstats)) $this->output(
                    'end process user ' . "\n" . '-----------------------------------' . "\n" . $userid
                );
            }
            $processed = $notsent + $sent + $invalid + $unconfirmed + $cannotsend + $failed_sent;
            $this->output(
                s(
                    'Processed %d out of %d subscribers',
                    $this->counters['processed_users_for_message ' . $message->id],
                    $this->counters['total_users_for_message ' . $message->id]
                ),
                1,
                'progress'
            );

            if ($this->counters['total_users_for_message ' . $message->id] - $sent <= 0 || $stopSending) {
                # this message is done
                if (!$someusers)
                    $this->output(s('Hmmm, No users found to send to'), 1, 'progress');
                if (!$failed_sent) {
                    repeatMessage($message->id);
                    $status = Sql_query(
                        sprintf(
                            'update %s set status = "sent",sent = current_timestamp where id = %d',
                            $GLOBALS['tables']['message'],
                            $message->id
                        )
                    );

                    if (!empty($msgdata['notify_end']) && !isset($msgdata['end_notified'])) {
                        $notifications = explode(',', $msgdata['notify_end']);
                        foreach ($notifications as $notification) {
                            sendMail(
                                $notification,
                                s('Message campaign finished'),
                                sprintf(
                                    s('phpList has finished sending the campaign with subject %s'),
                                    $msgdata['subject']
                                ) . "\n\n" .
                                sprintf(
                                    s('to view the results of this campaign, go to http://%s'),
                                    getConfig(
                                        'website'
                                    ) . $GLOBALS['adminpages'] . '/?page=statsoverview&id=' . $message->id
                                )
                            );
                        }
                        Sql_Query(
                            sprintf(
                                'insert ignore into %s (name,id,data) values("end_notified",%d,current_timestamp)',
                                $GLOBALS['tables']['messagedata'],
                                $message->id
                            )
                        );
                    }
                    $query
                        = " select sent, sendstart"
                        . " from ${tables['message']}"
                        . " where id = ?";
                    $rs = Sql_Query_Params($query, array($message->id));
                    $timetaken = Sql_Fetch_Row($rs);
                    $this->output(
                        s('It took') . ' ' . timeDiff($timetaken[0], $timetaken[1]) . ' ' . s('to send this message')
                    );
                    sendMessageStats($message->id);
                }
                ## flush cached message track stats to the DB
                if (isset($GLOBALS['cached']['linktracksent'])) {
                    flushClicktrackCache();
                    # we're done with $message->id, so get rid of the cache
                    unset($GLOBALS['cached']['linktracksent'][$message->id]);
                }

            } else {
                if ($this->script_stage < 5)
                    $this->script_stage = 5;
            }
        }

        if (!$num_messages)
            $this->script_stage = 6; # we are done
        # shutdown will take care of reporting
    }

    private function processError($message)
    {
        Logger::addToReport($message);
        $this->output("Error: $message");
        exit;
    }

    public function shutdown()
    {
        #  $this->output( "Script status: ".connection_status(),0); # with PHP 4.2.1 buggy. http://bugs.php.net/bug.php?id=17774
        $this->output(s('Script stage') . ': ' . $this->script_stage, 0, 'progress');

        $some = $this->processed; #$sent;# || $invalid || $notsent;
        if (!$some) {
            $this->output(s('Finished, Nothing to do'), 0, 'progress');
            $nothingtodo = 1;
        }

        $totaltime = $GLOBALS['processqueue_timer']->elapsed(1);
        if ($totaltime > 0) {
            $msgperhour = (3600 / $totaltime) * $this->sent;
        } else {
            $msgperhour = s('Calculating');
        }
        if ($this->sent)
            $this->output(
                sprintf(
                    '%d %s %01.2f %s (%d %s)',
                    $this->sent,
                    s('messages sent in'),
                    $totaltime,
                    s('seconds'),
                    $msgperhour,
                    s('msgs/hr')
                ),
                $this->sent,
                'progress'
            );
        if ($this->invalid) {
            $this->output(s('%d invalid email addresses', $this->invalid), 1, 'progress');
        }
        if ($this->failed_sent) {
            $this->output(s('%d failed (will retry later)', $this->failed_sent), 1, 'progress');
            foreach ($this->counters as $label => $value) {
                #  $this->output(sprintf('%d %s',$value,s($label)),1,'progress');
                Util::cl_output(sprintf('%d %s', $value, s($label)));
            }
        }
        if ($this->unconfirmed) {
            $this->output(sprintf(s('%d emails unconfirmed (not sent)'), $this->unconfirmed), 1, 'progress');
        }

        /*
         * TODO: enable plugins
        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $plugin->processSendStats($this->sent,$this->invalid,$this->failed_sent,$this->unconfirmed,$this->counters);
        }
        */

        Util::flushClickTrackCache();
        Process::releaseLock($this->send_process_id);

        //TODO: replace print function
        //finish("info",$report,$this->script_stage);
        //function finish ($flag,$message,$this->script_stage) {
        $subject = s("Maillist Processing info");
        if (!$this->nothingtodo) {
            $this->output(s('Finished this run'), 1, 'progress');
            printf(
                '<script type="text/javascript">
                                        var parentJQuery = window.parent.jQuery;
                                        parentJQuery("#progressmeter").updateSendProgress("%s,%s");
                                     </script>',
                $this->sent,
                $this->counters['total_users_for_message ' . $this->messageid]
            );
        }
        //TODO:enable plugins
        /*
        if (!Config::TEST && !$this->nothingtodo && Config::get(('END_QUEUE_PROCESSING_REPORT'))) {
            foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                $plugin->sendReport($subject,$message);
            }
        }
        */

        if ($this->script_stage < 5 && !$this->nothingtodo) {
            $this->output(
                s('Warning: script never reached stage 5') . "\n" . s(
                    'This may be caused by a too slow or too busy server'
                ) . " \n"
            );
            //TODO: remove globals
        } elseif ($this->script_stage == 5 && (!$this->nothingtodo || isset($GLOBALS["wait"]))) {
            # if the script timed out in stage 5, reload the page to continue with the rest
            $this->reload++;
            if (!Config::get('commandline') && $this->num_per_batch && $this->batch_period) {
                if ($this->sent + 10 < $this->original_num_per_batch) {
                    $this->output(s('Less than batch size were sent, so reloading imminently'), 1, 'progress');
                    $delaytime = 10;
                } else {
                    // TODO: we should actually want batch period minus time already spent.
                    // might be nice to calculate that at some point
                    $this->output(
                        sprintf(s('Waiting for %d seconds before reloading'), $this->batch_period),
                        1,
                        'progress'
                    );
                    $delaytime = $this->batch_period;
                }
                sleep($delaytime);
                printf(
                    '<script type="text/javascript">
                                               document.location = "./?page=pageaction&action=processqueue&ajaxed=true&reload=%d&lastsent=%d&lastskipped=%d";
                                            </script>',
                    $this->reload,
                    $this->sent,
                    $this->notsent
                );
            } else {
                printf(
                    '<script type="text/javascript">
                                               document.location = "./?page=pageaction&action=processqueue&ajaxed=true&reload=%d&lastsent=%d&lastskipped=%d";
                                            </script>',
                    $this->reload,
                    $this->sent,
                    $this->notsent
                );
            }
        } elseif ($this->script_stage == 6 || $this->nothingtodo) {
            /*
             * TODO: enable plugins and remove print
            foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                $plugin->messageQueueFinished();
            }*/
            $this->output(s('Finished, All done'), 0);
            printf(
                '<script type="text/javascript">
                                        var parentJQuery = window.parent.jQuery;
                                        window.parent.allDone("%s");
                                     </script>',
                s('All done')
            );

        } else {
            $this->output(s('Script finished, but not all messages have been sent yet.'));
        }
        if (!Config::get('commandline') && empty($_GET['ajaxed'])) {
            include_once "footer.inc";
        } elseif (Config::get('commandline')) {
            @ob_end_clean();
        }
        exit;
    }

    private $shadecount = 0;

    private function output($message, $logit = 1, $target = 'summary')
    {
        /*
         * Sander: Not used afaik
         * if (isset($this->counters['total_users_for_message '.$this->messageid])) {
            $total = $this->counters['total_users_for_message '.$this->messageid];
        } else {
            $total = 0;
        }*/

        if (is_array($message)) {
            $tmp = '';
            foreach ($message as $key => $val) {
                $tmp .= $key . '=' . $val . '; ';
            }
            $message = $tmp;
        }
        if (Config::get('commandline')) {
            Util::cl_output(
                strip_tags($message) . ' [' . $this->timer->interval(1) . '] (' . Config::get(
                    'pagestats'
                )["number_of_queries"] . ')'
            );
            $infostring = '[' . date('D j M Y H:i', time()) . '] [CL]';
        } else {
            $infostring = '[' . date('D j M Y H:i', time()) . '] [' . $_SERVER['REMOTE_ADDR'] . "]";
            #print "$infostring $message<br/>\n";
            $lines = explode("\n", $message);
            foreach ($lines as $line) {
                $line = preg_replace('/"/', '\"', $line);

                ## contribution in forums, http://forums.phplist.com/viewtopic.php?p=14648
                //Replace the "&rsquo;" which is not replaced by html_decode
                $line = preg_replace("/&rsquo;/", "'", $line);
                //Decode HTML chars
                $line = html_entity_decode($line, ENT_QUOTES, 'UTF-8');

                print "\n" . '<div class="output shade' . $this->shadecount . '">' . $line . '</div>';
                $line = str_replace("'", "\'", $line); // #16880 - avoid JS error
                printf(
                    '<script type="text/javascript">
                                                var parentJQuery = window.parent.jQuery;
                                                parentJQuery("#processqueue%s").append(\'<div class="output shade%s">%s</div>\');
                                                parentJQuery("#processqueue%s").animate({scrollTop:100000}, "slow");
                                              </script>',
                    $target,
                    ($this->shadecount) ? 1 : 0,
                    $line,
                    $target
                );

                $this->shadecount = !$this->shadecount;

                for ($i = 0; $i < 10000; $i++) {
                    print '  ';
                    if ($i % 100 == 0) print "\n";
                }
                @ob_flush();
                flush();
            }
            flush();
        }

        Logger::addToReport($infostring . ' ' . $message);
        if ($logit) {
            Logger::logEvent($message, 'processqueue');
        }

        flush();
    }


} 