<?php
/**
 * User: SaWey
 * Date: 17/12/13
 */

namespace phpList;


class MessageQueue
{
    private $status = 'OK';
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
    public $cannotsend;
    public $num_per_batch;
    public $batch_period;
    public $counters;
    public $original_num_per_batch;

    function __construct()
    {
    }

    public function process($message_id, $force = false, $reload = false, $cmd_max = 0)
    {
        //initialize the process queue timer
        Timer::start('PQC');
        $this->messageid = $message_id;

        $commandline = Config::get('commandline', false);
        if ($commandline && $force) {
            # force set, so kill other processes
            Output::cl_output('Force set, killing other send processes');
        }
        $this->send_process_id = Process::getPageLock('processqueue', $force);

        if (empty($this->send_process_id)) {
            return false;
        }
        #Output::cl_output('page locked on '.$this->send_process_id);
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
                Output::cl_output('Given a Unique ID to ' . $num . ' subscribers, this might have taken a while');
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

        //TODO: change change this to use running config instead of $_GET
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
                    case 'maxbatch':
                        $maxbatch = sprintf('%d', $val);
                        $ISPrestrictions .= "$key = $val\n";
                        break;
                    case 'minbatchperiod':
                        $minbatchperiod = sprintf('%d', $val);
                        $ISPrestrictions .= "$key = $val\n";
                        break;
                    case 'lockfile':
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
          Output::cl_output("Batch set with commandline to $this->num_per_batch");
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
            Output::output(s('Maximum time for queue processing') . ': ' . $maxProcessQueueTime, 'progress');
        }

        if ($cmd_max > 0) {
            Output::cl_output('Max to send is ' . $cmd_max . ' num per batch is ' . $this->num_per_batch);
            $clinemax = (int)$cmd_max;
            ## slow down just before max
            if ($clinemax < 20) {
                $this->num_per_batch = min(2, $clinemax, $this->num_per_batch);
            } elseif ($clinemax < 200) {
                $this->num_per_batch = min(20, $clinemax, $this->num_per_batch);
            } else {
                $this->num_per_batch = min($clinemax, $this->num_per_batch);
            }
            Output::cl_output('Max to send is ' . $cmd_max . ' setting num per batch to ' . $this->num_per_batch);
        }

        $safemode = false;
        if (ini_get('safe_mode')) {
            # keep an eye on timeouts
            $safemode = true;
            $this->num_per_batch = min(100, $this->num_per_batch);
            Output::customPrint(s('Running in safe mode') . '<br/>');
        }
        $recently_sent = 0;
        $this->original_num_per_batch = $this->num_per_batch;
        if ($this->num_per_batch && $batch_period) {
            # check how many were sent in the last batch period and subtract that
            # amount from this batch
            /*
              Output::output(sprintf('select count(*) from %s where entered > date_sub(current_timestamp,interval %d second) and status = "sent"',
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

            Output::cl_output('Recently sent : ' . $recently_sent[0]);
            $this->num_per_batch -= $recently_sent[0];

            # if this ends up being 0 or less, don't send anything at all
            if ($this->num_per_batch == 0) {
                $this->num_per_batch = -1;
            }
        }
        # output some stuff to make sure it's not buffered in the browser
        for ($i = 0; $i < 10000; $i++) {
            Output::customPrint('  ');
            if ($i % 100 == 0) {
                Output::customPrint("\n");
            }
        }
        Output::customPrint('<style type="text/css" src="css/app.css"></style>');
        Output::customPrint('<style type="text/css" src="ui/' . Config::get('ui') . '/css/style.css"></style>');
        Output::customPrint('<script type="text/javascript" src="js/' . Config::get('jQuery') . '"></script>');
        ## not sure this works, but would be nice
        Output::customPrint('<script type="text/javascript">$("#favicon").attr("href","images/busy.gif");</script>');

        flush();
        # report keeps track of what is going on
        $this->nothingtodo = false;

        register_shutdown_function(array(&$this, 'shutdown'));

        # we don not want to timeout or abort
        ignore_user_abort(1);
        set_time_limit(600);
        flush();

        if (!$this->reload) { ## only show on first load
            Output::output(s('Started'), 0);
            if (Config::get('SYSTEM_TIMEZONE') != '') {
                Output::output(s('Time now ') . date('Y-m-d H:i'));
            }
        }

        #output('Will process for a maximum of '.$maxProcessQueueTime.' seconds '.MAX_PROCESSQUEUE_TIME);

        # check for other processes running
        if (empty($this->send_process_id)) {
            $this->send_process_id = Process::getPageLock('processqueue');
        }

        if (!$this->send_process_id) {
            Output::output(s('Unable get lock for processing'));
            $this->status = s('Error processing');
            return false;
        }
        if (empty($this->reload)) { ## only show on first load
            if (!empty($ISPrestrictions)) {
                Output::output($ISPrestrictions);
            }
            if (is_file($ISPlockfile)) {
                $this->queueProcessError(s('Processing has been suspended by your ISP, please try again later'), 1);
            }
        }

        if ($this->num_per_batch > 0) {
            if ($safemode) {
                Output::output(s('In safe mode, batches are set to a maximum of 100'));
            }
            if ($this->original_num_per_batch != $this->num_per_batch) {
                if (empty($reload)) {
                    Output::output(s('Sending in batches of %d messages', $this->original_num_per_batch), 0);
                }
                $diff = $this->original_num_per_batch - $this->num_per_batch;
                if ($diff < 0) $diff = 0;
                Output::output(
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
                Output::output(s('Sending in batches of %d emails', $this->num_per_batch), 0, 'progress');
            }
        } elseif ($this->num_per_batch < 0) {
            Output::output(
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
            Output::output(s('Sent in last run') . ": $lastsent", 0, 'progress');
            Output::output(s('Skipped in last run') . ": $lastskipped", 0, 'progress');
        }

        $this->script_stage = 1; # we are active
        $this->notsent = $this->sent = $this->invalid = $this->unconfirmed = $this->cannotsend = 0;

        ## check for messages that need requeuing
        Message::checkMessagesToRequeue();

        if (Config::VERBOSE) {
            Output::output(phpList::DB()->getLastQuery());
        }

        $messages = Message::getMessagesToQueue();
        $num_messages = count($messages);

        if ($num_messages) {
            if (empty($this->reload)) {
                Output::output(s('Processing has started,') . ' ' . $num_messages . ' ' . s('message(s) to process.'));
            }
            clearPageCache();
            if (!Config::get('commandline', false) && empty($this->reload)) {
                if (!$safemode) {
                    Output::output(
                        s(
                            'Please leave this window open. You have batch processing enabled, so it will reload several times to send the messages. Reports will be sent by email to'
                        ) . ' ' . getConfig("report_address")
                    );
                } else {
                    Output::output(
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
            $this->failed_sent = 0;
            $throttlecount = 0;

            $this->counters['total_users_for_message ' . $message->id] = 0;
            $this->counters['processed_users_for_message ' . $message->id] = 0;

            if (Config::get('get_speed_stats', false) !== false){
                Output::output('start send ' . $message->id);
            }

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
            //if (!empty($message->'finishsending')) {
            $finishSendingBefore = $message->finishsending->getTimestamp();
            $secondsTogo = $finishSendingBefore - time();
            $stopSending = ($secondsTogo < 0);
            if (empty($this->reload)) {
                ### Hmm, this is probably incredibly confusing. It won't finish then
                if (Config::VERBOSE) {
                    Output::output(
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
                Output::output(s('Error loading message, please check the eventlog for details'));
                if (Config::MANUALLY_PROCESS_QUEUE) {
                    # wait a little, otherwise the message won't show
                    sleep(10);
                }
                continue;
            }

            if (Config::get('get_speed_stats', false) !== false){
                Output::output('message data loaded ');
            }
            //if (Config::VERBOSE) {
                //   Output::output($msgdata);
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
                $message->setDataItem('start_notified', 'CURRENT_TIMESTAMP');
            }

            if (empty($this->reload)) {
                Output::output(s('Processing message') . ' ' . $message->id);
            }

            flush();
            Process::keepLock($this->send_process_id);
            $message->setStatus('inprocess');

            if (empty($this->reload)) {
                Output::output(s('Looking for users'));
            }
            if (phpList::DB()->hasError()) {
                $this->queueProcessError(phpList::DB()->error());
            }

            # make selection on attribute, users who at least apply to the attributes
            # lots of ppl seem to use it as a normal mailinglist system, and do not use attributes.
            # Check this and take anyone in that case.

            ## keep an eye on how long it takes to find users, and warn if it's a long time
            $findUserStart = Timer::get('PQC')->elapsed(true);

            $numattr = phpList::DB()->fetchRowQuery(sprintf(
                    'SELECT COUNT(*) FROM %s',
                    Config::getTableName('attribute')
                ));

            $user_attribute_query = ''; #16552
            if ($userselection && $numattr[0]) {
                $res = phpList::DB()->query($userselection);
                $this->counters['total_users_for_message'] = phpList::DB()->numRows($res);
                if (empty($this->reload)) {
                    Output::output(
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
                        Output::output(s('No users apply for attributes'));
                    }
                    $message->setStatus('sent');
                    //finish("info", "Message $messageid: \nNo users apply for attributes, ie nothing to do");
                    $subject = s("Maillist Processing info");
                    if (!$this->nothingtodo) {
                        Output::output(s('Finished this run'), 1, 'progress');
                        Output::customPrintf(
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
                  $alive = checkLock($this->send_process_id);
                  if ($alive)
                    keepLock($this->send_process_id);
                  else
                    ProcessError(s('Process Killed by other process'));
                  array_push($doneusers,$row[0]);
                }
              } else {
                Output::output(s('Warning, disabling exclusion of done users, too many found'));
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
                    Output::output(s('looking for users who can be excluded from this mailing'));
                }
                if (!empty($message->excludelist)) {
                    $message->excludeUsersOnList($message->excludelist);
                    /*if (Config::VERBOSE) {
                        Output::output('Exclude query ' . phpList::DB()->getLastQuery());
                    }*/

                }
            }

            /*
              ## 8478
              $userids_query = sprintf('select distinct user.id from
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

                $userids_query = sprintf(
                    'SELECT userid FROM %s
                    WHERE messageid = %d
                    AND status = "todo"',
                    Config::getTableName('usermessage'),
                    $message->id
                );
                phpList::DB()->query($userids_query);
                $userids_result = phpList::DB()->affectedRows();
                # if (Config::VERBOSE) {
                cl_output('found pre-queued subscribers ' . $userids_result, 0, 'progress');
            }

            ## if the above didn't find any, run the normal search (again)
            if (empty($userids_result)) {
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

                $userids_query = sprintf(
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

                $userids_result = phpList::DB()->query($userids_query);
            }

            if (Config::VERBOSE) {
                Output::output('User select query ' . $userids_query);
            }

            if (phpList::DB()->hasError()) {
                $this->queueProcessError(phpList::DB()->error());
            }

            # now we have all our users to send the message to
            $this->counters['total_users_for_message ' . $message->id] = phpList::DB()->numRows($userids_result);
            /*if ($skipped >= 10000) {
                $this->counters['total_users_for_message ' . $message->id] -= $skipped;
            }*/

            $findUserEnd = Timer::get('PQC')->elapsed(true);

            if ($findUserEnd - $findUserStart > 300 && Config::get('commandline', false)) {
                Output::output(
                    s(
                        'Warning, finding the subscribers to send out to takes a long time, consider changing to commandline sending'
                    )
                );
            }

            if (empty($this->reload)) {
                Output::output(
                    s('Found them') . ': ' . $this->counters['total_users_for_message ' . $message->id] . ' ' . s(
                        'to process'
                    )
                );
            }
            $message->setDataItem('to process', $this->counters['total_users_for_message ' . $message->id]);

            if (Config::MESSAGEQUEUE_PREPARE) {
                ## experimental MESSAGEQUEUE_PREPARE will first mark all messages as todo and then work it's way through the todo's
                ## that should save time when running the queue multiple times, which avoids the user search after the first time
                ## only do this first time, ie empty($queued);
                ## the last run will pick up changes
                while ($user_ids = Sql_Fetch_Row($userids_result)) {
                    ## mark message/user combination as "todo"
                    $message->updateUserMessageStatus($user_ids[0], 'todo');
                }
                ## rerun the initial query, in order to continue as normal
                $userids_query = sprintf(
                    'SELECT userid FROM %s
                    WHERE messageid = %d
                    AND status = "todo"',
                    Config::getTableName('usermessage'),
                    $message->id
                );
                $userids_result = phpList::DB()->query($userids_query);
                $this->counters['total_users_for_message ' . $message->id] = phpList::DB()->numRows($userids_result);
            }

            if (Config::MAILQUEUE_BATCH_SIZE > 0) {
                ## in case of sending multiple campaigns, reduce batch with "sent"
                $this->num_per_batch -= $this->sent;

                # send in batches of $this->num_per_batch users
                $batch_total = $this->counters['total_users_for_message ' . $message->id];
                if ($this->num_per_batch > 0) {
                    $userids_query .= sprintf(' LIMIT 0,%d', $this->num_per_batch);
                    if (Config::VERBOSE) {
                        Output::output($this->num_per_batch . '  query -> ' . $userids_query);
                    }
                    $userids_result = phpList::DB()->query($userids_query);
                    if (phpList::DB()->hasError()) {
                        $this->queueProcessError(phpList::DB()->error());
                    }
                } else {
                    Output::output(s('No users to process for this batch'), 0, 'progress');
                    //TODO: Can we remove this pointless query (will have to change the while loop below)
                    $userids_result = phpList::DB()->query(sprintf('select * from %s where id = 0', Config::getTableName('user')));
                }
                $affrows = phpList::DB()->numRows($userids_result);
                Output::output(s('Processing batch of ') . ': ' . $affrows, 0, 'progress');
            }

            while ($userdata = phpList::DB()->fetchRow($userids_result)) {
                $this->counters['processed_users_for_message ' . $message->id]++;
                $failure_reason = '';
                if ($this->num_per_batch && $this->sent >= $this->num_per_batch) {
                    Output::output(s('batch limit reached') . ": $this->sent ($this->num_per_batch)", 1, 'progress');
                    Config::setRunningConfig('wait', $batch_period);
                    return;
                }

                $user = User::getUser($userdata[0]); # id of the user
                if (Config::get('get_speed_stats', false) !== false) Output::output(
                    '-----------------------------------' . "\n" . 'start process user ' . $user->id
                );
                $some = 1;
                set_time_limit(120);

                $secondsTogo = $finishSendingBefore - time();
                $stopSending = $secondsTogo < 0;

                # check if we have been "killed"
                #   Output::output('Process ID '.$this->send_process_id);
                $alive = Process::checkLock($this->send_process_id);

                ## check for max-process-queue-time
                $elapsed = $GLOBALS['processqueue_timer']->elapsed(true);
                if ($maxProcessQueueTime && $elapsed > $maxProcessQueueTime && $this->sent > 0) {
                    cl_output(s('queue processing time has exceeded max processing time ') . $maxProcessQueueTime);
                    break;
                } elseif ($alive && !$stopSending) {
                    Process::keepLock($this->send_process_id);
                } elseif ($stopSending) {
                    Output::output(s('Campaign sending timed out, is past date to process until'));
                    break;
                } else {
                    $this->queueProcessError(s('Process Killed by other process'));
                }

                # check if the message we are working on is still there and in process
                $message = Message::getMessage($message->id);
                if (empty($message)) {
                    ProcessError(s('Message I was working on has disappeared'));
                } elseif ($message->status != 'inprocess') {
                    ProcessError(s('Sending of this message has been suspended'));
                }
                flush();

                ##
                #Sql_Query_Params(sprintf('delete from %s where userid = ? and messageid = ? and status = "active"',$tables['usermessage']), array($userid,$message->id));

                # check whether the user has already received the message
                if (Config::get('get_speed_stats', false) !== false){
                    Output::output('verify message can go out to ' . $user->id);
                }

                $um = phpList::DB()->query(sprintf(
                        'SELECT entered FROM %s
                        WHERE userid = %d
                        AND messageid = %d
                        AND status != "todo"',
                        Config::getTableName('usermessage'),
                        $user->id,
                        $message->id
                    ));
                if (!phpList::DB()->numRows($um)) {
                    ## mark this message that we're working on it, so that no other process will take it
                    ## between two lines ago and here, should hopefully be quick enough
                    $message->updateUserMessageStatus($user->id, 'active');

                    if ($this->script_stage < 4)
                        $this->script_stage = 4; # we know a subscriber to send to
                    $someusers = 1;

                    # pick the first one (rather historical from before email was unique)
                    if ($user->confirmed && is_email($user->email)) {
                        //$userid = $user['id']; # id of the subscriber
                        //$useremail = $user['email']; # email of the subscriber
                        //$userhash = $user['uniqid']; # unique string of the user
                        //$htmlpref = $user['htmlemail']; # preference for HTML emails
                        $confirmed = $user->confirmed && !$user->disabled; ## 7 = disabled flag
                        //$blacklisted = $user['blacklisted'];

                        $cansend = !$user->blacklisted && $confirmed;
                        /*
                        ## Ask plugins if they are ok with sending this message to this user
                        */
                        if (Config::get('get_speed_stats', false) !== false){
                            Output::output('start check plugins ');
                        }

                        /*TODO: enable plugins
                        reset($GLOBALS['plugins']);
                        while ($cansend && $plugin = current($GLOBALS['plugins'])) {
                            if (Config::VERBOSE) {
                                cl_output('Checking plugin ' . $plugin->name());
                            }
                            $cansend = $plugin->canSend($message, $user);
                            if (!$cansend) {
                                $failure_reason .= 'Sending blocked by plugin ' . $plugin->name;
                                $this->counters['send blocked by ' . $plugin->name]++;
                                if (Config::VERBOSE) {
                                    cl_output('Sending blocked by plugin ' . $plugin->name);
                                }
                            }

                            next($GLOBALS['plugins']);
                        }*/
                        if (Config::get('get_speed_stats', false) !== false){
                            Output::output('end check plugins ');
                        }

                        ####################################
                        # Throttling

                        $throttled = 0;
                        if ($cansend && Config::USE_DOMAIN_THROTTLE) {
                            list($mailbox, $domainname) = explode('@', $user->email);
                            $now = time();
                            $interval = $now - ($now % Config::DOMAIN_BATCH_PERIOD);
                            if (!isset($this->domainthrottle[$domainname]) || !is_array($this->domainthrottle[$domainname])) {
                                $this->domainthrottle[$domainname] = array(
                                    'interval' => '',
                                    'sent' => 0,
                                    'attempted' => 0,
                                );
                            } elseif (isset($this->domainthrottle[$domainname]['interval']) && $this->domainthrottle[$domainname]['interval'] == $interval) {
                                $throttled = $this->domainthrottle[$domainname]['sent'] >= Config::DOMAIN_BATCH_SIZE;
                                if ($throttled) {
                                    $this->counters['send blocked by domain throttle']++;
                                    $this->domainthrottle[$domainname]['attempted']++;
                                    if (Config::DOMAIN_AUTO_THROTTLE
                                        && $this->domainthrottle[$domainname]['attempted'] > 25 # skip a few before auto throttling
                                        && $num_messages <= 1 # only do this when there's only one message to process otherwise the other ones don't get a chance
                                        && $this->counters['total_users_for_message ' . $message->id] < 1000 # and also when there's not too many left, because then it's likely they're all being throttled
                                    ) {
                                        $this->domainthrottle[$domainname]['attempted'] = 0;
                                        Logger::logEvent(
                                            sprintf(
                                                s(
                                                    'There have been more than 10 attempts to send to %s that have been blocked for domain throttling.'
                                                ),
                                                $domainname
                                            )
                                        );
                                        Logger::logEvent(s('Introducing extra delay to decrease throttle failures'));
                                        if (Config::VERBOSE) {
                                            Output::output(
                                                s('Introducing extra delay to decrease throttle failures')
                                            );
                                        }
                                        if (!isset($running_throttle_delay)) {
                                            $running_throttle_delay = (int)(Config::MAILQUEUE_THROTTLE + (Config::DOMAIN_BATCH_PERIOD / (Config::DOMAIN_BATCH_SIZE * 4)));
                                        } else {
                                            $running_throttle_delay += (int)(Config::DOMAIN_BATCH_PERIOD / (Config::DOMAIN_BATCH_SIZE * 4));
                                        }
                                        #Output::output("Running throttle delay: ".$running_throttle_delay);
                                    } elseif (Config::VERBOSE) {
                                        Output::output(
                                            sprintf(
                                                s('%s is currently over throttle limit of %d per %d seconds') .
                                                ' (' . $this->domainthrottle[$domainname]['sent'] . ')',
                                                $domainname,
                                                Config::DOMAIN_BATCH_SIZE,
                                                Config::DOMAIN_BATCH_PERIOD
                                            )
                                        );
                                    }
                                }
                            }
                        }

                        if ($cansend) {
                            $success = 0;
                            if (!Config::TEST) {
                                /*TODO: enable plugins
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
                                */
                                if (!$throttled) {
                                    if (Config::VERBOSE)
                                        Output::output(
                                            s('Sending') . ' ' . $message->id . ' ' . s('to') . ' ' . $user->email
                                        );
                                    $emailSentTimer = new Timer();
                                    $this->counters['batch_count']++;
                                    $success = PrepareMessage::sendEmail(
                                        $message->id,
                                        $user->email,
                                        $user->uniqid,
                                        $user->htmlemail
                                    ); // $rssitems Obsolete by rssmanager plugin
                                    if (!$success) {
                                        $this->counters['sendemail returned false']++;
                                    }
                                    if (Config::VERBOSE) {
                                        Output::output(
                                            s('It took') . ' ' . $emailSentTimer->elapsed(true) . ' ' .
                                            s('seconds to send')
                                        );
                                    }
                                } else {
                                    $throttlecount++;
                                }
                            } else {
                                $success = $this->sendEmailTest($message->id, $user->email);
                            }

                            #############################
                            # tried to send email , process succes / failure
                            if ($success) {
                                if (Config::USE_DOMAIN_THROTTLE) {
                                    list($mailbox, $domainname) = explode('@', $user->email);
                                    if ($this->domainthrottle[$domainname]['interval'] != $interval) {
                                        $this->domainthrottle[$domainname]['interval'] = $interval;
                                        $this->domainthrottle[$domainname]['sent'] = 0;
                                    } else {
                                        $this->domainthrottle[$domainname]['sent']++;
                                    }
                                }
                                $this->sent++;
                                $message->updateUserMessageStatus($user->id, 'sent');
                            } else {
                                $this->failed_sent++;
                                ## need to check this, the entry shouldn't be there in the first place, so no need to delete it
                                ## might be a cause for duplicated emails
                                if (Config::MESSAGEQUEUE_PREPARE) {
                                    phpList::DB()->query(sprintf(
                                            'UPDATE %s SET status = "todo"
                                            WHERE userid = %d
                                            AND messageid = %d
                                            AND status = "active"',
                                            Config::getTableName('usermessage'),
                                            $user->id,
                                            $message->id
                                        ));
                                } else {
                                    phpList::DB()->query(sprintf(
                                            'DELETE FROM %s
                                            WHERE userid = %d
                                            AND messageid = %d
                                            AND status = "active"',
                                            Config::getTableName('usermessage'),
                                            $user->id,
                                            $message->id
                                        ));
                                }
                                if (Config::VERBOSE) {
                                    Output::output(s('Failed sending to') . ' ' . $user->email);
                                    Logger::logEvent("Failed sending message $message->id to $user->email");
                                }
                                # make sure it's not because it's an underdeliverable email
                                # unconfirm this user, so they're not included next time
                                //TODO: should we not validate the email every time it is written to the database
                                //and mark it valid, so we don't need to check it every time
                                if (!$throttled && !Validation::validateEmail($user->email)) {
                                    $this->unconfirmed++;
                                    $this->counters['email address invalidated']++;
                                    logEvent("invalid email address $user->email user marked unconfirmed");
                                    $user->confirmed  = false;
                                    $user->save();
                                    /*Sql_Query(
                                        sprintf(
                                            'update %s set confirmed = 0 where email = "%s"',
                                            $GLOBALS['tables']['user'],
                                            $useremail
                                        )
                                    );*/
                                }
                            }

                            if ($this->script_stage < 5) {
                                $this->script_stage = 5; # we have actually sent one user
                            }
                            if (isset($running_throttle_delay)) {
                                sleep($running_throttle_delay);
                                if ($this->sent % 5 == 0) {
                                    # retry running faster after some more messages, to see if that helps
                                    unset($running_throttle_delay);
                                }
                            } elseif (Config::MAILQUEUE_THROTTLE) {
                                usleep(Config::MAILQUEUE_THROTTLE * 1000000);
                            } elseif (Config::MAILQUEUE_BATCH_SIZE && Config::MAILQUEUE_AUTOTHROTTLE) {
                                $totaltime = Timer::get('PQC')->elapsed(true);
                                //$msgperhour = (3600 / $totaltime) * $this->sent;
                                //$msgpersec = $msgperhour / 3600;

                                ##11336 - this may cause "division by 0", but 'secpermsg' isn't used at all
                                #  $secpermsg = $totaltime / $this->sent;
                                $target = (Config::MAILQUEUE_BATCH_PERIOD / Config::MAILQUEUE_BATCH_SIZE) * $this->sent;
                                $delay = $target - $totaltime;
                                #Output::output("Sent: $this->sent mph $msgperhour mps $msgpersec secpm $secpermsg target $target actual $actual d $delay");

                                if ($delay > 0) {
                                    if (Config::VERBOSE) {
                                        /* Output::output(s('waiting for').' '.$delay.' '.s('seconds').' '.
                                                           s('to make sure we don\'t exceed our limit of ').MAILQUEUE_BATCH_SIZE.' '.
                                                           s('messages in ').' '.MAILQUEUE_BATCH_PERIOD.s('seconds')); */
                                        Output::output(
                                            sprintf(
                                                s('waiting for %.1f seconds to meet target of %s seconds per message'),
                                                $delay,
                                                (Config::MAILQUEUE_BATCH_PERIOD / Config::MAILQUEUE_BATCH_SIZE)
                                            )
                                        );
                                    }
                                    usleep($delay * 1000000);
                                }
                            }
                        } else {
                            $this->cannotsend++;
                            # mark it as sent anyway, because otherwise the process will never finish
                            if (Config::VERBOSE) {
                                Output::output(s('not sending to ') . $user->email);
                            }
                            $message->updateUserMessageStatus($user->id, 'not sent');
                        }

                        # update possible other users matching this email as well,
                        # to avoid duplicate sending when people have subscribed multiple times
                        # bit of legacy code after making email unique in the database
                        #$emails = Sql_query("select * from {$tables['user']} where email =\"$useremail\"");
                        #while ($email = Sql_fetch_row($emails))
                        #Sql_query("replace into {$tables['usermessage']} (userid,messageid) values($email[0],$message->id)");
                    } else {
                        # some "invalid emails" are entirely empty, ah, that is because they are unconfirmed

                        ## this is quite old as well, with the preselection that avoids unconfirmed users
                        # it is unlikely this is every processed.

                        if (!$user->confirmed || $user->disabled) {
                            if (Config::VERBOSE)
                                Output::output(
                                    s('Unconfirmed user') . ': ' . $user->id . ' ' . $user->email . ' ' . $user->id
                                );
                            $this->unconfirmed++;
                            # when running from commandline we mark it as sent, otherwise we might get
                            # stuck when using batch processing
                            # if ($GLOBALS["commandline"]) {
                            $message->updateUserMessageStatus($user->id, 'unconfirmed user');
                            # }
                        } elseif ($user->email || $user->id) {
                            if (Config::VERBOSE) {
                                Output::output(s('Invalid email address') . ': ' . $user->email . ' ' . $user->id);
                            }
                            Logger::logEvent(
                                s('Invalid email address') . ': userid  ' . $user->id . '  email ' . $user->email
                            );
                            # mark it as sent anyway
                            if ($user->id > 0) {
                                $message->updateUserMessageStatus($user->id, 'invalid email address');
                                $user->confirmed = 0;
                                $user->save();
                                $user->addHistory(
                                    s('Subscriber marked unconfirmed for invalid email address'),
                                    s('Marked unconfirmed while sending campaign %d', $message->id),
                                    $message->id
                                );
                            }
                            $this->invalid++;
                        }
                    }
                } else {
                    ## and this is quite historical, and also unlikely to be every called
                    # because we now exclude users who have received the message from the
                    # query to find users to send to

                    ## when trying to send the message, it was already marked for this user
                    ## June 2010, with the multiple send process extension, that's quite possible to happen again

                    $um = phpList::DB()->fetchRow($um);
                    $this->notsent++;
                    if (Config::VERBOSE) {
                        Output::output(
                            s('Not sending to').' '.$user->id.', '.s('already sent').' '.$um[0]
                        );
                    }
                }
                $message->incrementProcessedAmount();
                $this->processed = $this->notsent + $this->sent + $this->invalid + $this->unconfirmed + $this->cannotsend + $this->failed_sent;
                #if ($this->processed % 10 == 0) {
                if (0) {
                    Output::output(
                        'AR' . $affrows . ' N ' . $this->counters['total_users_for_message ' . $message->id] . ' P' . $this->processed . ' S' . $this->sent . ' N' . $this->notsent . ' I' . $this->invalid . ' U' . $this->unconfirmed . ' C' . $this->cannotsend . ' F' . $this->failed_sent
                    );
                    $rn = $reload * $this->num_per_batch;
                    Output::output(
                        'P ' . $this->processed . ' N' . $this->counters['total_users_for_message ' . $message->id] . ' NB' . $this->num_per_batch . ' BT' . $batch_total . ' R' . $reload . ' RN' . $rn
                    );
                }
                /*
                 * don't calculate this here, but in the "msgstatus" instead, so that
                 * the total speed can be calculated, eg when there are multiple send processes
                 *
                 * re-added for commandline outputting
                 */


                $totaltime = Timer::get('PQC')->elapsed(true);
                if ($this->sent > 0) {
                    $msgperhour = (3600 / $totaltime) * $this->sent;
                    $secpermsg = $totaltime / $this->sent;
                    $timeleft = ($this->counters['total_users_for_message ' . $message->id] - $this->sent) * $secpermsg;
                    $eta = date('D j M H:i', time() + $timeleft);
                } else {
                    $msgperhour = 0;
                    $secpermsg = 0;
                    $timeleft = 0;
                    $eta = s('unknown');
                }
                $message->setDataItem('ETA', $eta);
                $message->setDataItem('msg/hr', $msgperhour);

                cl_progress('sent ' . $this->sent . ' ETA ' . $eta . ' sending ' . sprintf('%d', $msgperhour) . ' msg/hr');

                $message->setDataItem(
                    'to process',
                    $this->counters['total_users_for_message ' . $message->id] - $this->sent
                );
                $message->setDataItem('last msg sent', time());
                #$message->setDataItem('totaltime', $this->timer->elapsed(true));
                if (Config::get('get_speed_stats', false) !== false) Output::output(
                    'end process user ' . "\n" . '-----------------------------------' . "\n" . $user->id
                );
            }
            $this->processed = $this->notsent + $this->sent + $this->invalid + $this->unconfirmed + $this->cannotsend + $this->failed_sent;
            Output::output(
                s(
                    'Processed %d out of %d subscribers',
                    $this->counters['processed_users_for_message ' . $message->id],
                    $this->counters['total_users_for_message ' . $message->id]
                ),
                1,
                'progress'
            );

            if (($this->counters['total_users_for_message ' . $message->id] - $this->sent) <= 0 || $stopSending) {
                # this message is done
                if (!$someusers)
                    Output::output(s('Hmmm, No users found to send to'), 1, 'progress');
                if (!$this->failed_sent) {
                    $message->repeatMessage();
                    $message->setStatus('sent');

                    if (!empty($message->notify_end) && !isset($message->end_notified)) {
                        $notifications = explode(',', $message->notify_end);
                        foreach ($notifications as $notification) {
                            phpListMailer::sendMail(
                                $notification,
                                s('Message campaign finished'),
                                sprintf(
                                    s('phpList has finished sending the campaign with subject %s'),
                                    $message->subject
                                ) . "\n\n" .
                                sprintf(
                                    s('to view the results of this campaign, go to http://%s'),
                                    Config::get('website') . Config::get('adminpages') .
                                    '/?page=statsoverview&id=' . $message->id
                                )
                            );
                        }
                        $message->setDataItem('end_notified', 'CURRENT_TIMESTAMP');
                    }
                    /*TODO: Do we need to refetch these values from db?
                     * $query
                        = " select sent, sendstart"
                        . " from ${tables['message']}"
                        . " where id = ?";
                    $rs = Sql_Query_Params($query, array($message->id));
                    $timetaken = Sql_Fetch_Row($rs);*/
                    Output::output(
                        s('It took') . ' ' . Util::timeDiff($message->sent, $message->sendstart) . ' ' . s('to send this message')
                    );
                    $this->sendMessageStats($message->id);
                }
                ## flush cached message track stats to the DB
                if (isset(Cache::linktrackSentCache()[$message->id])) {
                    Cache::flushClicktrackCache();
                    # we're done with $message->id, so get rid of the cache
                    unset(Cache::linktrackSentCache()[$message->id]);
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

    /**
     * Fake sending a message for testing purposes
     * @param int $message_id
     * @param string $email
     * @return bool
     */
    private function sendEmailTest ($message_id, $email) {
        $message = s('(test)') . ' ' . s('Would have sent') . ' ' . $message_id . s('to') . ' ' . $email;
        if (Config::VERBOSE){
            Output::output($message);
        }else{
            Logger::addToReport($message);
        }
        // fake a bit of a delay,
        usleep(0.75 * 1000000);
        // and say it was fine.
        return true;
    }

    /**
     * Send statistics to phplist server
     * @param Message $message
     */
    private function sendMessageStats($message) {
        $msg = '';
        if (Config::NOSTATSCOLLECTION) {
            return;
        }

        $msg .= "phpList version ".Config::get('VERSION') . "\n";
        $diff = Util::timeDiff($message->sendstart, $message->sent);

        if ($message->processed > 10 && $diff != 'very little time') {
            $msg .= "\n".'Time taken: '.$diff;
            foreach (array (
                         'entered',
                         'processed',
                         'sendstart',
                         'sent',
                         'htmlformatted',
                         'sendformat',
                         'template',
                         'astext',
                         'ashtml',
                         'astextandhtml',
                         'aspdf',
                         'astextandpdf'
                     ) as $item) {
                $msg .= "\n".$item.' => '.$message->$item;
            }
            $mailto = Config::get('stats_collection_address', 'phplist-stats@phplist.com');
            mail($mailto,'PHPlist stats',$msg);
        }
    }

    private function queueProcessError($message)
    {
        Logger::addToReport($message);
        Output::output("Error: $message");
        exit;
    }

    public function shutdown()
    {
        #  Output::output( "Script status: ".connection_status(),0); # with PHP 4.2.1 buggy. http://bugs.php.net/bug.php?id=17774
        Output::output(s('Script stage') . ': ' . $this->script_stage, 0, 'progress');

        $some = $this->processed; #$this->sent;# || $this->invalid || $this->notsent;
        if (!$some) {
            Output::output(s('Finished, Nothing to do'), 0, 'progress');
            $nothingtodo = 1;
        }

        $totaltime = Timer::get('PQC')->elapsed(true);
        if ($totaltime > 0) {
            $msgperhour = (3600 / $totaltime) * $this->sent;
        } else {
            $msgperhour = s('Calculating');
        }
        if ($this->sent)
            Output::output(
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
            Output::output(s('%d invalid email addresses', $this->invalid), 1, 'progress');
        }
        if ($this->failed_sent) {
            Output::output(s('%d failed (will retry later)', $this->failed_sent), 1, 'progress');
            foreach ($this->counters as $label => $value) {
                #  Output::output(sprintf('%d %s',$value,s($label)),1,'progress');
                Output::cl_output(sprintf('%d %s', $value, s($label)));
            }
        }
        if ($this->unconfirmed) {
            Output::output(sprintf(s('%d emails unconfirmed (not sent)'), $this->unconfirmed), 1, 'progress');
        }

        /*
         * TODO: enable plugins
        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $plugin->processSendStats($this->sent,$this->invalid,$this->failed_sent,$this->unconfirmed,$this->counters);
        }
        */

        Cache::flushClickTrackCache();
        Process::releaseLock($this->send_process_id);

        //finish("info",$report,$this->script_stage);
        //function finish ($flag,$message,$this->script_stage) {
        $subject = s("Maillist Processing info");
        if (!$this->nothingtodo) {
            Output::output(s('Finished this run'), 1, 'progress');
            Output::customPrintf(
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
            Output::output(
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
                    Output::output(s('Less than batch size were sent, so reloading imminently'), 1, 'progress');
                    $delaytime = 10;
                } else {
                    // TODO: we should actually want batch period minus time already spent.
                    // might be nice to calculate that at some point
                    Output::output(
                        sprintf(s('Waiting for %d seconds before reloading'), $this->batch_period),
                        1,
                        'progress'
                    );
                    $delaytime = $this->batch_period;
                }
                sleep($delaytime);
                Output::customPrintf(
                    '<script type="text/javascript">
                                               document.location = "./?page=pageaction&action=processqueue&ajaxed=true&reload=%d&lastsent=%d&lastskipped=%d";
                                            </script>',
                    $this->reload,
                    $this->sent,
                    $this->notsent
                );
            } else {
                Output::customPrintf(
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
             * TODO: enable plugins
            foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                $plugin->messageQueueFinished();
            }*/
            Output::output(s('Finished, All done'), 0);
            Output::customPrintf(
                '<script type="text/javascript">
                                        var parentJQuery = window.parent.jQuery;
                                        window.parent.allDone("%s");
                                     </script>',
                s('All done')
            );

        } else {
            Output::output(s('Script finished, but not all messages have been sent yet.'));
        }
        if (!Config::get('commandline') && empty($_GET['ajaxed'])) {
            include_once "footer.inc";
        } elseif (Config::get('commandline')) {
            @ob_end_clean();
        }
        exit;
    }

} 