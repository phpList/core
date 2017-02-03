<?php
namespace phpList;

use phpList\helper\Cache;
use phpList\helper\PrepareCampaign;
use phpList\helper\Process;
use phpList\helper\Timer;
use phpList\helper\Util;
use phpList\helper\Validation;

class QueueProcessor
{
    private $status = 'OK';
    private $domainthrottle = array();
    /**
     * @var Campaign
     */
    private $current_campaign;
    private $safemode = false;
    private $script_stage = 0;
    private $reload = false;
    private $report;
    private $send_process_id;
    private $nothingtodo;
    private $invalid;
    private $processed;
    private $failed_sent;
    private $notsent;
    private $sent;
    private $unconfirmed;
    private $cannotsend;
    private $num_per_batch;
    private $batch_period;
    private $counters = array();
    private $original_num_per_batch;

    public function __construct()
    {
    }

    /**
     * Process campaign queue
     * @param bool $force set true if this one has to cancel running send processes
     * @param bool $reload
     * @param int $cmd_max
     * @return bool
     */
    public function startProcessing($force = false, $reload = false, $cmd_max = 0)
    {
        //initialize the process queue timer
        Timer::start('process_queue');

        $commandline = Config::get('commandline', false);
        if ($commandline && $force) {
            # force set, so kill other processes
            phpList::log()->info('Force set, killing other send processes', ['page' => 'processqueue']);
        }
        $this->send_process_id = Process::getPageLock('processqueue', $force);

        if (empty($this->send_process_id)) {
            phpList::log()->debug(s('Unable get lock for processing'), ['page' => 'porcessqueue']);
            $this->status = s('Error processing');
            return false;
        }

        $this->reload = $reload;

        ## let's make sure all subscribers have a uniqid
        ## only when on CL
        if ($commandline) {
            $num = Subscriber::checkUniqueIds();
            if ($num) {
                phpList::log()->info('Given a Unique ID to ' . $num . ' subscribers, this might have taken a while', ['page' => 'processqueue']);
            }
        }

        $this->num_per_batch = 0;
        $this->batch_period = 0;
        $somesubscribers = /*$skipped =*/ 0;

        $restrictions = $this->checkRestrictions($cmd_max);

        flush();
        # report keeps track of what is going on
        $this->nothingtodo = false;

        register_shutdown_function(array(&$this, 'shutdown'));

        # we do not want to timeout or abort
        ignore_user_abort(1);
        set_time_limit(600);
        flush();

        if (!$this->reload) { ## only show on first load
            phpList::log()->debug(s('Started'), 0, ['page' => 'porcessqueue']);
            if (Config::get('SYSTEM_TIMEZONE') != '') {
                phpList::log()->debug(s('Time now ') . date('Y-m-d H:i'), ['page' => 'porcessqueue']);
            }
        }

        if (!$this->reload) { ## only show on first load
            if (!empty($restrictions['rules'])) {
                phpList::log()->debug($restrictions['rules'], ['page' => 'porcessqueue']);
            }
            if ($restrictions['locked']) {
                $this->queueProcessError(s('Processing has been suspended by your ISP, please try again later'), 1);
            }
        }

        if ($this->num_per_batch > 0) {
            if ($this->original_num_per_batch != $this->num_per_batch) {
                if (empty($reload)) {
                    phpList::log()->debug(s('Sending in batches of %d campaigns', $this->original_num_per_batch), 0, ['page' => 'porcessqueue']);
                }
                $diff = $this->original_num_per_batch - $this->num_per_batch;
                if ($diff < 0) {
                    $diff = 0;
                }
                phpList::log()->info(
                    s(
                        'This batch will be %d emails, because in the last %d seconds %d emails were sent',
                        $this->num_per_batch,
                        $this->batch_period,
                        $diff
                    ),
                    ['page' => 'processqueue']
                );
            } else {
                phpList::log()->debug(s('Sending in batches of %d emails', $this->num_per_batch), ['page' => 'porcessqueue']);
            }
        } elseif ($this->num_per_batch < 0) {
            phpList::log()->info(
                s(
                    'In the last %d seconds more emails were sent (%d) than is currently allowed per batch (%d)',
                    $this->batch_period,
                    $restrictions['recently_sent'],
                    $this->original_num_per_batch
                ),
                ['page' => 'processqueue']
            );
            $this->processed = 0;
            $this->script_stage = 5;
            Config::setRunningConfig('wait', $this->batch_period);
            return false;
        }
        $$this->counters['batch_total'] = $this->num_per_batch;

        if (0 && $reload) {
            //TODO: change change this to use running config instead of $_GET
            $lastsent = !empty($_GET['lastsent']) ? sprintf('%d', $_GET['lastsent']) : 0;
            $lastskipped = !empty($_GET['lastskipped']) ? sprintf('%d', $_GET['lastskipped']) : 0;
            phpList::log()->debug(s('Sent in last run') . ": $lastsent", ['page' => 'porcessqueue']);
            phpList::log()->debug(s('Skipped in last run') . ": $lastskipped", ['page' => 'porcessqueue']);
        }

        $this->script_stage = 1; # we are active
        $this->notsent = $this->sent = $this->invalid = $this->unconfirmed = $this->cannotsend = 0;

        ## check for campaigns that need requeuing
        Campaign::checkCampaignsToRequeue();

        if (Config::VERBOSE) {
            phpList::log()->debug(phpList::DB()->getLastQuery(), ['page' => 'porcessqueue']);
        }

        $campaigns = Campaign::getCampaignsToQueue();
        $num_campaigns = count($campaigns);

        if ($num_campaigns) {
            if (!$this->reload) {
                phpList::log()->debug(s('Processing has started,') . ' ' . $num_campaigns . ' ' . s('campaign(s) to process.'), ['page' => 'porcessqueue']);
            }
            Cache::clearPageCache();
            if (!$commandline && !$this->reload) {
                if (!$this->safemode) {
                    phpList::log()->info(
                        s(
                            'Please leave this window open. You have batch processing enabled, so it will reload several times to send the campaigns. Reports will be sent by email to'
                        ) . ' ' . Config::get('report_address')
                    );
                } else {
                    phpList::log()->info(
                        s(
                            'Your webserver is running in safe_mode. Please keep this window open. It may reload several times to make sure all campaigns are sent.'
                        ) . ' ' . s('Reports will be sent by email to') . ' ' . Config::get('report_address')
                    );
                }
            }
        }

        $this->script_stage = 2; # we know the campaigns to process
        if (!isset($this->num_per_batch)) {
            $this->num_per_batch = 1000000;
        }

        $output_speed_stats = Config::get('get_speed_stats', false) !== false;
        /**
         * @var $campaign Campaign
         */
        foreach ($campaigns as $campaign) {
            $this->current_campaign = $campaign;
            $this->counters['campaign']++;
            $this->failed_sent = 0;
            $throttlecount = 0;

            $this->counters['total_subscribers_for_campaign ' . $campaign->id] = 0;
            $this->counters['processed_subscribers_for_campaign ' . $campaign->id] = 0;


            if ($output_speed_stats) {
                phpList::log()->debug('start send ' . $campaign->id, ['page' => 'porcessqueue']);
            }

            if ($campaign->resetstats == 1) {
                $campaign->resetCampaignStatistics();
                ## make sure to reset the resetstats flag, so it doesn't clear it every run
                $campaign->setDataItem('resetstats', 0);
            }

            ## check the end date of the campaign
            $finish_sending_before = $campaign->finishsending->getTimestamp();
            $seconds_to_go = $finish_sending_before - time();
            $stop_sending = ($seconds_to_go < 0);
            if (!$this->reload) {
                ### Hmm, this is probably incredibly confusing. It won't finish then
                if (Config::VERBOSE) {
                    phpList::log()->info(
                        sprintf(
                            s('sending of this campaign will stop, if it is still going in %s'),
                            Util::secs2time($seconds_to_go)
                        )
                    );
                }
            }

            $subscriberselection = $campaign->subscriberselection; ## @@ needs more work
            ## load campaign in cache
            if (!PrepareCampaign::precacheCampaign($campaign)) {
                ## precache may fail on eg invalid remote URL
                ## any reporting needed here?

                # mark the campaign as suspended
                phpList::DB()->query(sprintf(
                    'UPDATE %s SET status = "suspended"
                        WHERE id = %d',
                    Config::getTableName('message'),
                    $campaign->id
                ));
                phpList::log()->debug(s('Error loading campaign, please check the eventlog for details'), ['page' => 'porcessqueue']);
                if (Config::MANUALLY_PROCESS_QUEUE) {
                    # wait a little, otherwise the campaign won't show
                    sleep(10);
                }
                continue;
            }

            if ($output_speed_stats) {
                phpList::log()->debug('campaign data loaded ', ['page' => 'porcessqueue']);
            }
            if (!empty($campaign->notify_start) && !isset($campaign->start_notified)) {
                $notifications = explode(',', $campaign->notify_start);
                foreach ($notifications as $notification) {
                    phpListMailer::sendMail(
                        $notification,
                        s('Campaign started'),
                        sprintf(
                            s('phplist has started sending the campaign with subject %s'),
                            $campaign->subject . "\n\n" .
                            sprintf(
                                s('to view the progress of this campaign, go to http://%s'),
                                Config::get('website') . Config::get('adminpages') . '/?page=campaigns&amp;tab=active'
                            )
                        )
                    );
                }
                $campaign->setDataItem('start_notified', 'CURRENT_TIMESTAMP');
            }

            if (!$this->reload) {
                phpList::log()->debug(s('Processing campaign') . ' ' . $campaign->id, ['page' => 'porcessqueue']);
            }

            flush();
            Process::keepLock($this->send_process_id);
            $campaign->setStatus('inprocess');

            if (!$this->reload) {
                phpList::log()->debug(s('Looking for subscribers'), ['page' => 'porcessqueue']);
            }
            if (phpList::DB()->hasError()) {
                $this->queueProcessError(phpList::DB()->error());
            }

            # make selection on attribute, subscribers who at least apply to the attributes
            # lots of ppl seem to use it as a normal mailinglist system, and do not use attributes.
            # Check this and take anyone in that case.

            ## keep an eye on how long it takes to find subscribers, and warn if it's a long time
            $find_subscriber_start = Timer::get('process_queue')->elapsed(true);

            $attribute_count = phpList::DB()->query(sprintf(
                'SELECT COUNT(*) FROM %s',
                Config::getTableName('attribute')
            ))->fetchColumn(0);

            $subscriber_attribute_query = ''; #16552
            if ($subscriberselection && $attribute_count) {
                $result = phpList::DB()->query($subscriberselection);
                $this->counters['total_subscribers_for_campaign'] = $result->rowCount();
                if (!$this->reload) {
                    phpList::log()->info(
                        $this->counters['total_subscribers_for_campaign'] . ' ' . s(
                            'subscribers apply for attributes, now checking lists'
                        ),
                        ['page' => 'processqueue']
                    );
                }
                $subscriber_list = '';
                while ($fetched_subscriber = $result->fetchColumn(0)) {
                    $subscriber_list .= $fetched_subscriber . ",";
                }
                $subscriber_list = substr($subscriber_list, 0, -1);
                if ($subscriber_list) {
                    $subscriber_attribute_query = " AND listuser.userid IN ($subscriber_list)";
                } else {
                    if (!$this->reload) {
                        phpList::log()->debug(s('No subscribers apply for attributes'), ['page' => 'porcessqueue']);
                    }
                    $campaign->setStatus('sent');
                    $subject = s("Maillist Processing info");
                    if (!$this->nothingtodo) {
                        phpList::log()->debug(s('Finished this run'), ['page' => 'porcessqueue']);
                        phpList::log()->info(
                            s(
                                '%s of %s done',
                                $this->sent,
                                $this->counters['total_subscribers_for_campaign ' . $this->current_campaign->id]
                            ),
                            ['page' => 'progress']
                        );
                    }
                    $this->script_stage = 6;
                    # we should actually continue with the next campaign
                    return true;
                }
            }
            if ($this->script_stage < 3) {
                $this->script_stage = 3; # we know the subscribers by attribute
            }

            # when using commandline we need to exclude subscribers who have already received
            # the email
            # we don't do this otherwise because it slows down the process, possibly
            # causing us to not find anything at all
            $exclusion = '';

            if (Config::USE_LIST_EXCLUDE) {
                if (Config::VERBOSE) {
                    phpList::log()->debug(s('looking for subscribers who can be excluded from this mailing'), ['page' => 'porcessqueue']);
                }
                //TODO: change this so it happens automatically when set in the campaign object
                if (!empty($campaign->excludelist)) {
                    $campaign->excludeSubscribersOnList($campaign->excludelist);
                    /*if (Config::VERBOSE) {
                        phpList::log()->debug('Exclude query ' . phpList::DB()->getLastQuery(), ['page' => 'porcessqueue']);
                    }*/
                }
            }

            /*
              ## 8478
              $subscriberids_query = sprintf('select distinct user.id from
                %s as listuser,
                %s as user,
                %s as listmessage
                where
                listmessage.messageid = %d and
                listmessage.listid = listuser.listid and
                user.id = listuser.userid %s %s %s',
                $tables['listuser'],$tables['user'],$tables['listmessage'],
                $campaignid,
                $subscriberconfirmed,
                $exclusion,
                $subscriber_attribute_query);*/
                $queued = 0;
            if (Config::MESSAGEQUEUE_PREPARE) {
                $subscriberids_query = sprintf(
                    'SELECT userid FROM %s
                    WHERE messageid = %d
                    AND status = "todo"',
                    Config::getTableName('usermessage'),
                    $campaign->id
                );

                $subscriberids_result = phpList::DB()->query($subscriberids_query)->rowCount();
                # if (Config::VERBOSE) {
                phpList::log()->info('found pre-queued subscribers ' . $subscriberids_result, ['page' => 'processqueue']);
            }

            ## if the above didn't find any, run the normal search (again)
            if ($subscriberids_result <= 0) {
                ## remove pre-queued campaigns, otherwise they wouldn't go out
                $removed = phpList::DB()->query(sprintf(
                    'DELETE FROM %s
                        WHERE messageid = %d
                        AND status = "todo"',
                    Config::getTableName('usermessage'),
                    $campaign->id
                ))->rowCount();

                if ($removed > 0) {
                    phpList::log()->info('removed pre-queued subscribers ' . $removed, ['page' => 'processqueue']);
                }

                $subscriberids_query = sprintf(
                    'SELECT DISTINCT user.id FROM %s AS listuser
                            CROSS JOIN %s AS user
                            CROSS JOIN %s AS listmessage
                            LEFT JOIN %s AS user_message
                              ON (user_message.messageid = %d AND user_message.userid = listuser.userid)
                        WHERE true
                          AND listmessage.messageid = %d
                          AND listmessage.listid = listuser.listid
                          AND user.id = listuser.userid
                          AND user_message.userid IS NULL
                          AND user.confirmed
                          AND !user.blacklisted
                          AND !user.disabled
                        %s %s',
                    Config::getTableName('listuser'),
                    Config::getTableName('user', true),
                    Config::getTableName('listmessage'),
                    Config::getTableName('usermessage'),
                    $exclusion,
                    $subscriber_attribute_query
                );

                $subscriberids_result = phpList::DB()->query($subscriberids_query);
            }

            if (Config::VERBOSE) {
                phpList::log()->debug('Subscriber select query ' . $subscriberids_query, ['page' => 'porcessqueue']);
            }

            if (phpList::DB()->hasError()) {
                $this->queueProcessError(phpList::DB()->error());
            }

            # now we have all our subscribers to send the campaign to
            $this->counters['total_subscribers_for_campaign ' . $campaign->id] = $subscriberids_result->rowCount();

            $find_subscriber_end = Timer::get('process_queue')->elapsed(true);

            if ($find_subscriber_end - $find_subscriber_start > 300 && $commandline) {
                phpList::log()->info(
                    s(
                        'Warning, finding the subscribers to send out to takes a long time, consider changing to commandline sending'
                    )
                );
            }

            if (!$this->reload) {
                phpList::log()->info(
                    s('Found them') . ': ' . $this->counters['total_subscribers_for_campaign ' . $campaign->id] . ' ' .
                    s('to process')
                );
            }
            $campaign->setDataItem('to process', $this->counters['total_subscribers_for_campaign ' . $campaign->id]);

            if (Config::MESSAGEQUEUE_PREPARE) {
                ## experimental MESSAGEQUEUE_PREPARE will first mark all campaigns as todo and then work it's way through the todo's
                ## that should save time when running the queue multiple times, which avoids the subscriber search after the first time
                ## only do this first time, ie empty($queued);
                ## the last run will pick up changes
                while ($fetched_subscriber_id = $subscriberids_result->fetchColumn(0)) {
                    ## mark campaign/subscriber combination as "todo"
                    $campaign->updateSubscriberCampaignStatus($fetched_subscriber_id, 'todo');
                }
                ## rerun the initial query, in order to continue as normal
                $subscriberids_query = sprintf(
                    'SELECT userid FROM %s
                    WHERE messageid = %d
                    AND status = "todo"',
                    Config::getTableName('usermessage'),
                    $campaign->id
                );
                $subscriberids_result = phpList::DB()->query($subscriberids_query);
                $this->counters['total_subscribers_for_campaign ' . $campaign->id] = $subscriberids_result->rowCount();
            }

            if (Config::MAILQUEUE_BATCH_SIZE > 0) {
                ## in case of sending multiple campaigns, reduce batch with "sent"
                $this->num_per_batch -= $this->sent;

                # send in batches of $this->num_per_batch subscribers
                $batch_total = $this->counters['total_subscribers_for_campaign ' . $campaign->id];
                if ($this->num_per_batch > 0) {
                    $subscriberids_query .= sprintf(' LIMIT 0,%d', $this->num_per_batch);
                    if (Config::VERBOSE) {
                        phpList::log()->debug($this->num_per_batch . '  query -> ' . $subscriberids_query, ['page' => 'porcessqueue']);
                    }
                    try {
                        $subscriberids_result = phpList::DB()->query($subscriberids_query);
                    } catch (\PDOException $e) {
                        $this->queueProcessError($e->getMessage());
                    }
                } else {
                    phpList::log()->debug(s('No subscribers to process for this batch'), ['page' => 'porcessqueue']);
                    //TODO: Can we remove this pointless query (will have to change the while loop below)
                    $subscriberids_result = phpList::DB()->query(sprintf('SELECT * FROM %s WHERE id = 0', Config::getTableName('user')));
                }
                $affrows = $subscriberids_result->rowCount();
                phpList::log()->debug(s('Processing batch of ') . ': ' . $affrows, ['page' => 'porcessqueue']);
            }

            while ($subscriberdata = $subscriberids_result->fetch()) {
                $this->counters['processed_subscribers_for_campaign ' . $campaign->id]++;
                $failure_reason = '';
                if ($this->num_per_batch && $this->sent >= $this->num_per_batch) {
                    phpList::log()->debug(s('batch limit reached') . ": $this->sent ($this->num_per_batch)", ['page' => 'porcessqueue']);
                    Config::setRunningConfig('wait', $this->batch_period);
                    return false;
                }

                $subscriber = Subscriber::getSubscriber($subscriberdata[0]); # id of the subscriber
                if ($output_speed_stats) {
                    phpList::log()->info(
                        '-----------------------------------' . "\n" . 'start process subscriber ' . $subscriber->id
                    );
                }
                $some = 1;
                set_time_limit(120);

                $seconds_to_go = $finish_sending_before - time();
                $stop_sending = $seconds_to_go < 0;

                # check if we have been "killed"
                $alive = Process::checkLock($this->send_process_id);

                ## check for max-process-queue-time
                $elapsed = Timer::get('process_queue')->elapsed(true);
                if ($restrictions['max_process_queue_time'] && $elapsed > $restrictions['max_process_queue_time'] && $this->sent > 0) {
                    phpList::log()->info(s('queue processing time has exceeded max processing time ') . $restrictions['max_process_queue_time'], ['page' => 'processqueue']);
                    break;
                } elseif ($alive && !$stop_sending) {
                    Process::keepLock($this->send_process_id);
                } elseif ($stop_sending) {
                    phpList::log()->debug(s('Campaign sending timed out, is past date to process until'), ['page' => 'porcessqueue']);
                    break;
                } else {
                    $this->queueProcessError(s('Process Killed by other process'));
                }

                # check if the campaign we are working on is still there and in process
                $campaign = Campaign::getCampaign($campaign->id);
                if (empty($campaign)) {
                    $this->queueProcessError(s('Campaign I was working on has disappeared'));
                } elseif ($campaign->status != 'inprocess') {
                    $this->queueProcessError(s('Sending of this campaign has been suspended'));
                }
                flush();

                # check whether the subscriber has already received the campaign
                if ($output_speed_stats) {
                    phpList::log()->debug('verify campaign can go out to ' . $subscriber->id, ['page' => 'porcessqueue']);
                }

                $um = phpList::DB()->query(sprintf(
                    'SELECT entered FROM %s
                        WHERE userid = %d
                        AND messageid = %d
                        AND status != "todo"',
                    Config::getTableName('usermessage'),
                    $subscriber->id,
                    $campaign->id
                ));
                if ($um->rowCount() <= 0) {
                    ## mark this campaign that we're working on it, so that no other process will take it
                    ## between two lines ago and here, should hopefully be quick enough
                    $campaign->updateSubscriberCampaignStatus($subscriber->id, 'active');

                    if ($this->script_stage < 4) {
                        $this->script_stage = 4; # we know a subscriber to send to
                    }
                    $somesubscribers = 1;

                    # pick the first one (rather historical from before email was unique)
                    //TODO: since we don't allow invalid email addresses to be set, can we omit this check
                    // or is there a reason to keep it here?
                    if ($subscriber->confirmed && Validation::isEmail($subscriber->getEmailAddress())) {
                        ####################################
                        # Throttling

                        $throttled = 0;
                        if ($subscriber->allowsReceivingMails() && Config::USE_DOMAIN_THROTTLE) {
                            //TODO: what if we were to put the domain name of the email address in a separate database field
                            //we could even query for the domain then
                            list($mailbox, $domainname) = explode('@', $subscriber->getEmailAddress());
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
                                        && $num_campaigns <= 1 # only do this when there's only one campaign to process otherwise the other ones don't get a chance
                                        && $this->counters['total_subscribers_for_campaign ' . $campaign->id] < 1000 # and also when there's not too many left, because then it's likely they're all being throttled
                                    ) {
                                        $this->domainthrottle[$domainname]['attempted'] = 0;
                                        phpList::log()->notice(
                                            sprintf(
                                                s('There have been more than 10 attempts to send to %s that have been blocked for domain throttling.'),
                                                $domainname
                                            )
                                        );
                                        phpList::log()->notice(s('Introducing extra delay to decrease throttle failures'));
                                        if (Config::VERBOSE) {
                                            phpList::log()->info(
                                                s('Introducing extra delay to decrease throttle failures')
                                            );
                                        }
                                        if (!isset($running_throttle_delay)) {
                                            $running_throttle_delay = (int)(Config::MAILQUEUE_THROTTLE + (Config::DOMAIN_BATCH_PERIOD / (Config::DOMAIN_BATCH_SIZE * 4)));
                                        } else {
                                            $running_throttle_delay += (int)(Config::DOMAIN_BATCH_PERIOD / (Config::DOMAIN_BATCH_SIZE * 4));
                                        }
                                    } elseif (Config::VERBOSE) {
                                        phpList::log()->info(
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

                        if ($subscriber->allowsReceivingMails()) {
                            $success = false;
                            if (Config::TEST) {
                                $success = $this->sendEmailTest($campaign->id, $subscriber->getEmailAddress());
                            } else {
                                if (!$throttled) {
                                    if (Config::VERBOSE) {
                                        phpList::log()->info(
                                            s('Sending') . ' ' . $campaign->id . ' ' . s('to') . ' ' . $subscriber->getEmailAddress()
                                        );
                                    }
                                    Timer::start('email_sent_timer');
                                    $this->counters['batch_count']++;
                                    $success = PrepareCampaign::sendEmail($campaign, $subscriber);

                                    if (!$success) {
                                        $this->counters['sendemail returned false']++;
                                    }
                                    if (Config::VERBOSE) {
                                        phpList::log()->info(
                                            s('It took') . ' ' . Timer::get('email_sent_timer')->elapsed(true) . ' ' .
                                            s('seconds to send')
                                        );
                                    }
                                } else {
                                    $throttlecount++;
                                }
                            }

                            #############################
                            # tried to send email , process succes / failure
                            if ($success) {
                                if (Config::USE_DOMAIN_THROTTLE) {
                                    list($mailbox, $domainname) = explode('@', $subscriber->getEmailAddress());
                                    if ($this->domainthrottle[$domainname]['interval'] != $interval) {
                                        $this->domainthrottle[$domainname]['interval'] = $interval;
                                        $this->domainthrottle[$domainname]['sent'] = 0;
                                    } else {
                                        $this->domainthrottle[$domainname]['sent']++;
                                    }
                                }
                                $this->sent++;
                                $campaign->updateSubscriberCampaignStatus($subscriber->id, 'sent');
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
                                        $subscriber->id,
                                        $campaign->id
                                    ));
                                } else {
                                    phpList::DB()->query(sprintf(
                                        'DELETE FROM %s
                                            WHERE userid = %d
                                            AND messageid = %d
                                            AND status = "active"',
                                        Config::getTableName('usermessage'),
                                        $subscriber->id,
                                        $campaign->id
                                    ));
                                }
                                if (Config::VERBOSE) {
                                    phpList::log()->debug(s('Failed sending to') . ' ' . $subscriber->getEmailAddress(), ['page' => 'porcessqueue']);
                                    phpList::log()->notice(sprintf('Failed sending campaign %d to %s', $campaign->id, $subscriber->getEmailAddress()));
                                }
                                # make sure it's not because it's an underdeliverable email
                                # unconfirm this subscriber, so they're not included next time
                                //TODO: since we don't allow invalid email addresses to be set, can we omit this check
                                // or is there a reason to keep it here?
                                if (!$throttled && !Validation::validateEmail($subscriber->getEmailAddress())) {
                                    $this->unconfirmed++;
                                    $this->counters['email address invalidated']++;
                                    phpList::log()->notice(sprintf('invalid email address %s subscriber marked unconfirmed', $subscriber->getEmailAddress()));
                                    $subscriber->confirmed  = false;
                                    $subscriber->save();
                                }
                            }

                            if ($this->script_stage < 5) {
                                $this->script_stage = 5; # we have actually sent one subscriber
                            }
                            if (isset($running_throttle_delay)) {
                                sleep($running_throttle_delay);
                                if ($this->sent % 5 == 0) {
                                    # retry running faster after some more campaigns, to see if that helps
                                    unset($running_throttle_delay);
                                }
                            } elseif (Config::MAILQUEUE_THROTTLE) {
                                usleep(Config::MAILQUEUE_THROTTLE * 1000000);
                            } elseif (Config::MAILQUEUE_BATCH_SIZE && Config::MAILQUEUE_AUTOTHROTTLE) {
                                $totaltime = Timer::get('process_queue')->elapsed(true);

                                $target = (Config::MAILQUEUE_BATCH_PERIOD / Config::MAILQUEUE_BATCH_SIZE) * $this->sent;
                                $delay = $target - $totaltime;

                                if ($delay > 0) {
                                    if (Config::VERBOSE) {
                                        phpList::log()->info(
                                            sprintf(
                                                s('waiting for %.1f seconds to meet target of %s seconds per campaign'),
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
                                phpList::log()->debug(s('not sending to ') . $subscriber->getEmailAddress(), ['page' => 'porcessqueue']);
                            }
                            $campaign->updateSubscriberCampaignStatus($subscriber->id, 'not sent');
                        }
                    } else {
                        # some "invalid emails" are entirely empty, ah, that is because they are unconfirmed

                        ## this is quite old as well, with the preselection that avoids unconfirmed subscribers
                        # it is unlikely this is every processed.

                        if (!$subscriber->confirmed || $subscriber->disabled) {
                            if (Config::VERBOSE) {
                                phpList::log()->info(
                                    s('Unconfirmed subscriber') . ': ' . $subscriber->id . ' ' . $subscriber->getEmailAddress() . ' ' . $subscriber->id
                                );
                            }
                            $this->unconfirmed++;
                            # when running from commandline we mark it as sent, otherwise we might get
                            # stuck when using batch processing
                            $campaign->updateSubscriberCampaignStatus($subscriber->id, 'unconfirmed subscriber');
                            //TODO: can probably remove below check
                        } elseif ($subscriber->getEmailAddress() || $subscriber->id) {
                            if (Config::VERBOSE) {
                                phpList::log()->debug(s('Invalid email address') . ': ' . $subscriber->getEmailAddress() . ' ' . $subscriber->id, ['page' => 'porcessqueue']);
                            }
                            phpList::log()->notice(
                                s('Invalid email address') . ': userid  ' . $subscriber->id . '  email ' . $subscriber->getEmailAddress()
                            );
                            # mark it as sent anyway
                            if ($subscriber->id > 0) {
                                $campaign->updateSubscriberCampaignStatus($subscriber->id, 'invalid email address');
                                $subscriber->confirmed = 0;
                                $subscriber->save();
                                $subscriber->addHistory(
                                    s('Subscriber marked unconfirmed for invalid email address'),
                                    s('Marked unconfirmed while sending campaign %d', $campaign->id),
                                    $campaign->id
                                );
                            }
                            $this->invalid++;
                        }
                    }
                } else {
                    //TODO: remove below
                    ## and this is quite historical, and also unlikely to be every called
                    # because we now exclude subscribers who have received the campaign from the
                    # query to find subscribers to send to

                    ## when trying to send the campaign, it was already marked for this subscriber
                    ## June 2010, with the multiple send process extension, that's quite possible to happen again

                    $um = $um->fetch();
                    $this->notsent++;
                    if (Config::VERBOSE) {
                        phpList::log()->info(
                            s('Not sending to').' '.$subscriber->id.', '.s('already sent').' '.$um[0]
                        );
                    }
                }
                $campaign->incrementProcessedAmount();
                $this->processed = $this->notsent + $this->sent + $this->invalid + $this->unconfirmed + $this->cannotsend + $this->failed_sent;
                #if ($this->processed % 10 == 0) {
                if (0) {
                    phpList::log()->info(
                        'AR' . $affrows . ' N ' . $this->counters['total_subscribers_for_campaign ' . $campaign->id] . ' P' . $this->processed . ' S' . $this->sent . ' N' . $this->notsent . ' I' . $this->invalid . ' U' . $this->unconfirmed . ' C' . $this->cannotsend . ' F' . $this->failed_sent
                    );
                    $rn = $reload * $this->num_per_batch;
                    phpList::log()->info(
                        'P ' . $this->processed . ' N' . $this->counters['total_subscribers_for_campaign ' . $campaign->id] . ' NB' . $this->num_per_batch . ' BT' . $batch_total . ' R' . $reload . ' RN' . $rn
                    );
                }
                /*
                 * don't calculate this here, but in the "msgstatus" instead, so that
                 * the total speed can be calculated, eg when there are multiple send processes
                 *
                 * re-added for commandline outputting
                 */


                $totaltime = Timer::get('process_queue')->elapsed(true);
                if ($this->sent > 0) {
                    $msgperhour = (3600 / $totaltime) * $this->sent;
                    $secpermsg = $totaltime / $this->sent;
                    $timeleft = ($this->counters['total_subscribers_for_campaign ' . $campaign->id] - $this->sent) * $secpermsg;
                    $eta = date('D j M H:i', time() + $timeleft);
                } else {
                    $msgperhour = 0;
                    $secpermsg = 0;
                    $timeleft = 0;
                    $eta = s('unknown');
                }
                $campaign->setDataItem('ETA', $eta);
                $campaign->setDataItem('msg/hr', $msgperhour);

                cl_progress('sent ' . $this->sent . ' ETA ' . $eta . ' sending ' . sprintf('%d', $msgperhour) . ' msg/hr');

                $campaign->setDataItem(
                    'to process',
                    $this->counters['total_subscribers_for_campaign ' . $campaign->id] - $this->sent
                );
                $campaign->setDataItem('last msg sent', time());
                if ($output_speed_stats) {
                    phpList::log()->info(
                        'end process subscriber ' . "\n" . '-----------------------------------' . "\n" . $subscriber->id
                    );
                }
            }
            $this->processed = $this->notsent + $this->sent + $this->invalid + $this->unconfirmed + $this->cannotsend + $this->failed_sent;
            phpList::log()->info(
                s(
                    'Processed %d out of %d subscribers',
                    $this->counters['processed_subscribers_for_campaign ' . $campaign->id],
                    $this->counters['total_subscribers_for_campaign ' . $campaign->id]
                ),
                ['page' => 'processqueue']
            );

            if (($this->counters['total_subscribers_for_campaign ' . $campaign->id] - $this->sent) <= 0 || $stop_sending) {
                # this campaign is done
                if (!$somesubscribers) {
                    phpList::log()->debug(s('Hmmm, No subscribers found to send to'), ['page' => 'porcessqueue']);
                }
                if (!$this->failed_sent) {
                    $campaign->repeatCampaign();
                    $campaign->setStatus('sent');

                    if (!empty($campaign->notify_end) && !isset($campaign->end_notified)) {
                        $notifications = explode(',', $campaign->notify_end);
                        foreach ($notifications as $notification) {
                            phpListMailer::sendMail(
                                $notification,
                                s('Campaign campaign finished'),
                                sprintf(
                                    s('phpList has finished sending the campaign with subject %s'),
                                    $campaign->subject
                                ) . "\n\n" .
                                sprintf(
                                    s('to view the results of this campaign, go to http://%s'),
                                    Config::get('website') . Config::get('adminpages') .
                                    '/?page=statsoverview&id=' . $campaign->id
                                )
                            );
                        }
                        $campaign->setDataItem('end_notified', 'CURRENT_TIMESTAMP');
                    }
                    phpList::log()->debug(
                        s('It took') . ' ' . Util::timeDiff($campaign->sent, $campaign->sendstart) . ' ' . s('to send this campaign')
                    );
                    $this->sendCampaignStats($campaign);
                }
                $cache = Cache::instance();
                ## flush cached campaign track stats to the DB
                if (isset($cache->linktrack_sent_cache[$campaign->id])) {
                    Cache::flushClicktrackCache();
                    # we're done with $campaign->id, so get rid of the cache
                    unset($cache->linktrack_sent_cache[$campaign->id]);
                }
            } else {
                if ($this->script_stage < 5) {
                    $this->script_stage = 5;
                }
            }
        }

        if (!$num_campaigns) {
            $this->script_stage = 6; # we are done
        }
        # shutdown will take care of reporting
        return true;
    }

    /**
     * Check if restrictions have been set
     * @param int $max
     * @return array
     */
    private function checkRestrictions($max = 0)
    {
        $maxbatch = -1;
        $minbatchperiod = -1;
        # check for batch limits
        $restrictions = array();
        $restrictions['rules'] = '';
        $restrictions['locked'] = false;

        if ($fp = @fopen('/etc/phplist.conf', 'r')) {
            $contents = fread($fp, filesize('/etc/phplist.conf'));
            fclose($fp);
            $lines = explode("\n", $contents);
            $restrictions['rules'] = s('The following restrictions have been set by your ISP:') . "\n";
            foreach ($lines as $line) {
                list($key, $val) = explode("=", $line);

                switch ($key) {
                    case 'maxbatch':
                        $maxbatch = sprintf('%d', $val);
                        $restrictions['rules'] .= "$key = $val\n";
                        break;
                    case 'minbatchperiod':
                        $minbatchperiod = sprintf('%d', $val);
                        $restrictions['rules'] .= "$key = $val\n";
                        break;
                    case 'lockfile':
                        $restrictions['locked'] = is_file($val);
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
                $this->batch_period = max(Config::MAILQUEUE_BATCH_PERIOD, $minbatchperiod);
            } else {
                $this->batch_period = Config::MAILQUEUE_BATCH_PERIOD;
            }
        }

        $max_process_queue_time = 0;
        if (Config::MAX_PROCESSQUEUE_TIME > 0) {
            $max_process_queue_time = (int)Config::MAX_PROCESSQUEUE_TIME;
        }
        # in-page processing force to a minute max, and make sure there's a batch size
        if (Config::get('commandline', false) === false) {
            $max_process_queue_time = min($max_process_queue_time, 60);
            if ($this->num_per_batch <= 0) {
                $this->num_per_batch = 10000;
            }
        }
        $restrictions['max_process_queue_time'] = $max_process_queue_time;

        if (Config::VERBOSE && $max_process_queue_time) {
            phpList::log()->debug(s('Maximum time for queue processing') . ': ' . $max_process_queue_time, ['page' => 'porcessqueue']);
        }

        if ($max > 0) {
            phpList::log()->info('Max to send is ' . $max . ' num per batch is ' . $this->num_per_batch, ['page' => 'processqueue']);
            $clinemax = (int)$max;
            ## slow down just before max
            if ($clinemax < 20) {
                $this->num_per_batch = min(2, $clinemax, $this->num_per_batch);
            } elseif ($clinemax < 200) {
                $this->num_per_batch = min(20, $clinemax, $this->num_per_batch);
            } else {
                $this->num_per_batch = min($clinemax, $this->num_per_batch);
            }
            phpList::log()->info('Max to send is ' . $max . ' setting num per batch to ' . $this->num_per_batch, ['page' => 'processqueue']);
        }

        if (ini_get('safe_mode')) {
            # keep an eye on timeouts
            $this->safemode = true;
            $this->num_per_batch = min(100, $this->num_per_batch);
            phpList::log()->notice(s('Running in safe mode') . "\n" . s('In safe mode, batches are set to a maximum of 100'), ['page'=>'processqueue']);
        }

        $recently_sent = 0;
        $this->original_num_per_batch = $this->num_per_batch;
        if ($this->num_per_batch && $this->batch_period) {
            # check how many were sent in the last batch period and subtract that
            # amount from this batch
            $result = phpList::DB()->query(
                sprintf(
                    'SELECT COUNT(*) FROM %s
                    WHERE entered > date_sub(CURRENT_TIMESTAMP,INTERVAL %d second)
                    AND status = "sent"',
                    Config::getTableName('usermessage'),
                    $this->batch_period
                )
            );
            $recently_sent = $result->fetchColumn(0);
            phpList::log()->info('Recently sent : ' . $recently_sent, ['page'=>'processqueue']);
            $this->num_per_batch -= $recently_sent;

            # if this ends up being 0 or less, don't send anything at all
            if ($this->num_per_batch == 0) {
                $this->num_per_batch = -1;
            }
        }
        $restrictions['recently_sent'] = $recently_sent;

        return $restrictions;
    }

    /**
     *
     * @param string $campaign
     */
    private function queueProcessError($campaign)
    {
        phpList::log()->addToReport($campaign);
        phpList::log()->debug("Error: $campaign", ['page' => 'porcessqueue']);
        exit;
    }

    /**
     * Fake sending a campaign for testing purposes
     * @param int $campaign_id
     * @param string $email
     * @return bool
     */
    private function sendEmailTest($campaign_id, $email)
    {
        $campaign = s('(test)') . ' ' . s('Would have sent') . ' ' . $campaign_id . s('to') . ' ' . $email;
        if (Config::VERBOSE) {
            phpList::log()->debug($campaign, ['page' => 'porcessqueue']);
        } else {
            phpList::log()->addToReport($campaign);
        }
        // fake a bit of a delay,
        usleep(0.75 * 1000000);
        // and say it was fine.
        return true;
    }

    /**
     * Send statistics to phplist server
     * @param Campaign $campaign
     */
    private function sendCampaignStats($campaign)
    {
        $msg = '';
        if (Config::NOSTATSCOLLECTION) {
            return;
        }

        $msg .= "phpList version ".PHPLIST_VERSION . "\n";
        $diff = Util::timeDiff($campaign->sendstart, $campaign->sent);

        if ($campaign->processed > 10 && $diff != 'very little time') {
            $msg .= "\n".'Time taken: '.$diff;
            foreach (array(
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
                $msg .= "\n".$item.' => '.$campaign->$item;
            }
            $mailto = Config::get('stats_collection_address', 'phplist-stats@phplist.com');
            mail($mailto, 'PHPlist stats', $msg);
        }
    }

    /**
     * Shutdown function for execution on shutdown
     * @link http://php.net/manual/en/function.register-shutdown-function.php
     */
    public function shutdown()
    {
        phpList::log()->debug(s('Script stage') . ': ' . $this->script_stage, ['page' => 'porcessqueue']);

        $some = $this->processed; #$this->sent;# || $this->invalid || $this->notsent;
        if (!$some) {
            phpList::log()->debug(s('Finished, Nothing to do'), ['page' => 'porcessqueue']);
            $this->nothingtodo = 1;
        }

        $totaltime = Timer::get('process_queue')->elapsed(true);
        if ($totaltime > 0) {
            $msgperhour = (3600 / $totaltime) * $this->sent;
        } else {
            $msgperhour = s('Calculating');
        }
        if ($this->sent) {
            phpList::log()->debug(
                sprintf(
                    '%d %s %01.2f %s (%d %s)',
                    $this->sent,
                    s('campaigns sent in'),
                    $totaltime,
                    s('seconds'),
                    $msgperhour,
                    s('msgs/hr')
                ),
                ['page' => 'processqueue']
            );
        }
        if ($this->invalid > 0) {
            phpList::log()->debug(s('%d invalid email addresses', $this->invalid), ['page' => 'porcessqueue']);
        }
        if ($this->failed_sent > 0) {
            phpList::log()->debug(s('%d failed (will retry later)', $this->failed_sent), ['page' => 'porcessqueue']);
            foreach ($this->counters as $label => $value) {
                phpList::log()->info(sprintf('%d %s', $value, s($label)), ['page' => 'processqueue']);
            }
        }
        if ($this->unconfirmed > 0) {
            phpList::log()->debug(sprintf(s('%d emails unconfirmed (not sent)'), $this->unconfirmed), ['page' => 'porcessqueue']);
        }

        Cache::flushClickTrackCache();
        Process::releaseLock($this->send_process_id);

        $subject = s('Maillist Processing info');
        if (!$this->nothingtodo) {
            phpList::log()->info(s('Finished this run'), ['page' => 'progress']);
            phpList::log()->info(
                s(
                    '%s of %s done',
                    $this->sent,
                    $this->counters['total_subscribers_for_campaign ' . $this->current_campaign->id]
                ),
                ['page' => 'progress']
            );
        }

        if ($this->script_stage < 5 && !$this->nothingtodo) {
            phpList::log()->info(
                s('Warning: script never reached stage 5') . "\n" . s(
                    'This may be caused by a too slow or too busy server'
                ) . " \n"
            );
            //TODO: remove globals
        } elseif ($this->script_stage == 5 && (!$this->nothingtodo || isset($GLOBALS['wait']))) {
            # if the script timed out in stage 5, reload the page to continue with the rest
            $this->reload++;
            if (!Config::get('commandline') && $this->num_per_batch && $this->batch_period) {
                if ($this->sent + 10 < $this->original_num_per_batch) {
                    phpList::log()->debug(s('Less than batch size were sent, so reloading imminently'), ['page' => 'porcessqueue']);
                    $delaytime = 10;
                } else {
                    // TODO: we should actually want batch period minus time already spent.
                    // might be nice to calculate that at some point
                    phpList::log()->info(
                        sprintf(s('Waiting for %d seconds before reloading'), $this->batch_period),
                        ['page' => 'processqueue']
                    );
                    $delaytime = $this->batch_period;
                }
                sleep($delaytime);
            }
        } elseif ($this->script_stage == 6 || $this->nothingtodo) {
            phpList::log()->debug(s('Finished, All done'), 0, ['page' => 'porcessqueue']);
        } else {
            phpList::log()->debug(s('Script finished, but not all campaigns have been sent yet.'), ['page' => 'porcessqueue']);
        }
        if (!Config::get('commandline') && empty($_GET['ajaxed'])) {
            include_once "footer.inc";
        } elseif (Config::get('commandline')) {
            @ob_end_clean();
        }
        exit;
    }
}
