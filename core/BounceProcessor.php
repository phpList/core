<?php
/**
 * User: SaWey
 * Date: 21/12/13
 */

namespace phpList;


class BounceProcessor {
    //TODO: replace print statements
    /**
     * @var BounceProcessor $_instance
     */
    private static $_instance;
    private $process_id;


    private function __construct(){}

    public static function instance()
    {
        if (!BounceProcessor::$_instance instanceof self) {
            BounceProcessor::$_instance = new self();
        }
        return BounceProcessor::$_instance;
    }

    /**
     * @throws \Exception
     */
    public function startProcessing()
    {
        $this->preflightCheck();
        # lets not do this unless we do some locking first
        register_shutdown_function(array(&$this, 'processBouncesShutdown'));
        ignore_user_abort(1);
        if (Config::get('commandline', false) !== false && Config::get('commandline_force', false) !== false) {
            # force set, so kill other processes
            Output::cl_output('Force set, killing other send processes');
            $this->process_id = Process::getPageLock('BounceProcessor', true);
        } else {
            $this->process_id = Process::getPageLock('BounceProcessor');
        }
        if (empty($this->process_id)) {
            return;
        }

        switch (Config::BOUNCE_PROTOCOL) {
            case 'pop':
                processPop(
                    Config::BOUNCE_MAILBOX_HOST,
                    Config::BOUNCE_MAILBOX_USER,
                    Config::BOUNCE_MAILBOX_PASSWORD
                );
                break;
            case 'mbox':
                processMbox(Config::BOUNCE_MAILBOX);
                break;
            default:
                Error(s('bounce_protocol not supported'));
                return;
        }

        # now we have filled database with all available bounces
        ## reprocess the unidentified ones, as the bounce detection has improved, so it might catch more

        Output::cl_output('reprocessing');
        $reparsed = $count = 0;
        $reidentified = 0;

        $req = phpList::DB()->query(sprintf(
                'SELECT * FROM %s
                WHERE status = "unidentified bounce"',
                Config::getTableName('bounce')
            ));
        $total = phpList::DB()->affectedRows();
        Output::cl_output(s('%d bounces to reprocess', $total));

        while ($bounce = phpList::DB()->fetchAssoc($req)) {
            $count++;
            if ($count % 25 == 0) {
                Output::cl_progress(s('%d out of %d processed', $count, $total));
            }
            $bounceBody = $this->decodeBody($bounce->header, $bounce->data);
            $user = $this->findUser($bounceBody);
            $message_id = $this->findMessageId($bounceBody);
            if ($user !== false || !empty($message_id)) {
                $reparsed++;
                if ($this->processBounceData($bounce['id'], $message_id, $user->id)) {
                    $reidentified++;
                }
            }
        }
        Output::cl_output(s('%d out of %d processed', $count, $total));
        if (Config::VERBOSE) {
            $this->output(s('%d bounces were re-processed and %d bounces were re-identified',$reparsed,$reidentified));
        }
        $advanced_report = '';
        if (Config::USE_ADVANCED_BOUNCEHANDLING) {
            $this->output(s('Processing bounces based on active bounce rules'));
            $bouncerules = BounceRule::getAllBounceRules();
            $matched = 0;
            $notmatched = 0;
            //$limit =  ' limit 10';
            //$limit =  ' limit 10000';
            $limit = '';
            # @@ not sure whether this one would make sense
            #  $req = Sql_Query(sprintf('select * from %s as bounce, %s as umb,%s as user where bounce.id = umb.bounce
            #    and user.id = umb.user and !user.confirmed and !user.blacklisted %s',
            #    $GLOBALS['tables']['bounce'],$GLOBALS['tables']['user_message_bounce'],$GLOBALS['tables']['user'],$limit));
            $req = phpList::DB()->query(sprintf(
                    'SELECT * FROM %s AS bounce, %s AS umb
                    WHERE bounce.id = umb.bounce %s',
                    Config::getTableName('bounce'),
                    Config::getTableName('user_message_bounce'),
                    $limit
                ));
            while ($row = phpList::DB()->fetchAssoc($req)) {
                $alive = Process::checkLock($this->process_id);
                if ($alive)
                    Process::keepLock($this->process_id);
                else
                    $this->bounceProcessError(s('Process Killed by other process'));
                #    output('User '.$row['user']);
                $rule = BounceRule::matchedBounceRules($row['data'],$bouncerules);
                #    output('Action '.$rule['action']);
                #    output('Rule'.$rule['id']);
                $userdata = array();
                if ($rule && is_array($rule)) {
                    if ($row['user']) {
                        $user = User::getUser($row['user']);
                    }
                    $report_linkroot = Config::get('admin_scheme').'://'.Config::get('website').Config::get('adminpages');
                    phpList::DB()->query(sprintf(
                            'UPDATE %s SET count = count + 1
                            WHERE id = %d',
                            Config::getTableName('bounceregex'),
                            $rule['id']
                        ));
                    phpList::DB()->query(sprintf(
                            'INSERT IGNORE INTO %s
                            (regex,bounce)
                            VALUES(%d,%d)',
                            Config::getTableName('bounceregex_bounce'),
                            $rule['id'],
                            $row['bounce']
                        ));

                    switch ($rule['action']) {
                        case 'deleteuser':
                            Logger::logEvent('User '.$user->email.' deleted by bounce rule '.PageLink2('bouncerule&amp;id='.$rule['id'],$rule['id']));
                            $advanced_report .= 'User '.$user->email.' deleted by bounce rule '.$rule['id']."\n";
                            $advanced_report .= 'User: '.$report_linkroot.'/?page=user&amp;id='.$user->id."\n";
                            $advanced_report .= 'Rule: '.$report_linkroot.'/?page=bouncerule&amp;id='.$rule['id']."\n";
                            deleteUser($row['user']);
                            break;
                        case 'unconfirmuser':
                            Logger::logEvent('User '.$user->email.' unconfirmed by bounce rule '.PageLink2('bouncerule&amp;id='.$rule['id'],$rule['id']));
                            $user->confirmed = 0;
                            $user->update();
                            $advanced_report .= 'User '.$user->email.' made unconfirmed by bounce rule '.$rule['id']."\n";
                            $advanced_report .= 'User: '.$report_linkroot.'/?page=user&amp;id='.$user->id."\n";
                            $advanced_report .= 'Rule: '.$report_linkroot.'/?page=bouncerule&amp;id='.$rule['id']."\n";
                            $user->addHistory(s('Auto Unconfirmed'),s('Subscriber auto unconfirmed for')." ".s('bounce rule').' '.$rule['id'], $user->id);
                            Util::addSubscriberStatistics('auto unsubscribe',1);
                            break;
                        case 'deleteuserandbounce':
                            logEvent('User '.$row['user'].' deleted by bounce rule '.PageLink2('bouncerule&amp;id='.$rule['id'],$rule['id']));
                            $advanced_report .= 'User '.$user->email.' deleted by bounce rule '.$rule['id']."\n";
                            $advanced_report .= 'User: '.$report_linkroot.'/?page=user&amp;id='.$user->id."\n";
                            $advanced_report .= 'Rule: '.$report_linkroot.'/?page=bouncerule&amp;id='.$rule['id']."\n";
                            $user->delete();
                            Bounce::deleteBounce($row['bounce']);
                            break;
                        case 'unconfirmuseranddeletebounce':
                            Logger::logEvent('User '.$user->email.' unconfirmed by bounce rule '.PageLink2('bouncerule&amp;id='.$rule['id'],$rule['id']));
                            $user->confirmed = 0;
                            $user->update();
                            $advanced_report .= 'User '.$user->email.' made unconfirmed by bounce rule '.$rule['id']."\n";
                            $advanced_report .= 'User: '.$report_linkroot.'/?page=user&amp;id='.$user->id."\n";
                            $advanced_report .= 'Rule: '.$report_linkroot.'/?page=bouncerule&amp;id='.$rule['id']."\n";
                            $user->addHistory(s('Auto unconfirmed'),s('Subscriber auto unconfirmed for')." ".s("bounce rule").' '.$rule['id'], $user->id);
                            Util::addSubscriberStatistics('auto unsubscribe',1);
                            Bounce::deleteBounce($row['bounce']);
                            break;
                        case 'blacklistuser':
                            Logger::logEvent('User '.$user->email.' blacklisted by bounce rule '.PageLink2('bouncerule&amp;id='.$rule['id'],$rule['id']));
                            $user->blacklistUser($user->email,s("Auto Blacklisted"),s("User auto blacklisted for")." ".s("bounce rule").' '.$rule['id']);
                            $advanced_report .= 'User '.$user->email.' blacklisted by bounce rule '.$rule['id']."\n";
                            $advanced_report .= 'User: '.$report_linkroot.'/?page=user&amp;id='.$user->id."\n";
                            $advanced_report .= 'Rule: '.$report_linkroot.'/?page=bouncerule&amp;id='.$rule['id']."\n";
                            $user->addHistory(s("Auto Unsubscribed"),s("User auto unsubscribed for")." ".s("bounce rule").' '.$rule['id'], $user->id);
                            Ustil::addSubscriberStatistics('auto blacklist',1);
                            break;
                        case 'blacklistuseranddeletebounce':
                            Logger::logEvent('User '.$user->email.' blacklisted by bounce rule '.PageLink2('bouncerule&amp;id='.$rule['id'],$rule['id']));
                            $user->blacklistUser($user->email,s("Auto Blacklisted"),s("User auto blacklisted for")." ".s("bounce rule").' '.$rule['id']);
                            $advanced_report .= 'User '.$user->email.' blacklisted by bounce rule '.$rule['id']."\n";
                            $advanced_report .= 'User: '.$report_linkroot.'/?page=user&amp;id='.$user->id."\n";
                            $advanced_report .= 'Rule: '.$report_linkroot.'/?page=bouncerule&amp;id='.$rule['id']."\n";
                            $user->addHistory(s("Auto Unsubscribed"),s("User auto unsubscribed for")." ".s("bounce rule").' '.$rule['id'], $user->id);
                            Util::addSubscriberStatistics('auto blacklist',1);
                            Bounce::deleteBounce($row['bounce']);
                            break;
                        case 'deletebounce':
                            Bounce::deleteBounce($row['bounce']);
                            break;
                    }
                    $matched++;
                } else {
                    $notmatched++;
                }
            }
            $this->output($matched.' '.s('bounces processed by advanced processing'));
            $this->output($notmatched.' '.s('bounces were not matched by advanced processing rules'));
        }

        # have a look who should be flagged as unconfirmed
        $this->output(s("Identifying consecutive bounces"));

        # we only need users who are confirmed at the moment
        $userid_req = phpList::DB()->query(sprintf(
                'SELECT DISTINCT umb.user FROM %s AS umb, %s AS u
                WHERE u.id = umb.user
                AND u.confirmed',
                Config::getTableName('user_message_bounce'),
                Config::getTableName('user', true)
            ));
        $total = phpList::DB()->affectedRows();
        if (!$total){
            $this->output(s("Nothing to do"));
        }

        $usercnt = 0;
        $unsubscribed_users = "";
        while ($user = phpList::DB()->fetchRow($userid_req)) {
            Process::keepLock($this->process_id);
            set_time_limit(600);
            $msg_req = phpList::DB()->query(sprintf(
                    'SELECT * FROM %s AS um
                    LEFT JOIN %s AS umb
                      ON (um.messageid = umb.message AND userid = user)
                    WHERE userid = %d
                      AND um.status = "sent"
                    ORDER BY entered DESC',
                    Config::getTableName('usermessage'),
                    Config::getTableName('user_message_bounce'),
                    $user[0]
                ));
            /*  $cnt = 0;
              $alive = 1;$removed = 0;
              while ($alive && !$removed && $bounce = Sql_Fetch_Array($msg_req)) {
                $alive = checkLock($process_id);
                if ($alive)
                  keepLock($process_id);
                else
                  ProcessError(s("Process Killed by other process"));
                if (sprintf('%d',$bounce["bounce"]) == $bounce["bounce"]) {
                  $cnt++;
                  if ($cnt >= $bounce_unsubscribe_threshold) {
                    $removed = 1;
                    output(sprintf('unsubscribing %d -> %d bounces',$user[0],$cnt));
                    $userurl = PageLink2("user&amp;id=$user[0]",$user[0]);
                    logEvent(s("User")." $userurl ".s("has consecutive bounces")." ($cnt) ".s("over threshold, user marked unconfirmed"));
                    $emailreq = Sql_Fetch_Row_Query("select email from {$tables["user"]} where id = $user[0]");
                    addUserHistory($emailreq[0],s("Auto Unsubscribed"),s("User auto unsubscribed for")." $cnt ".s("consecutive bounces"));
                    Sql_Query(sprintf('update %s set confirmed = 0 where id = %d',$tables["user"],$user[0]));
                    addSubscriberStatistics('auto unsubscribe',1);
                    $email_req = Sql_Fetch_Row_Query(sprintf('select email from %s where id = %d',$tables["user"],$user[0]));
                    $unsubscribed_users .= $email_req[0] . " [$user[0]] ($cnt)\n";
                  }
                } elseif ($bounce["bounce"] == "") {
                  $cnt = 0;
                }
              }*/
            #$alive = 1;$removed = 0; DT 051105
            $cnt=0;
            $alive = 1;
            $removed = $msgokay = $unconfirmed = $unsubscribed = 0;
            #while ($alive && !$removed && $bounce = Sql_Fetch_Array($msg_req)) { DT 051105
            while ($alive && !$removed && !$msgokay && $bounce = Sql_Fetch_Array($msg_req)) {

                $alive = Process::checkLock($this->process_id);
                if ($alive) {
                    Process::keepLock($this->process_id);
                } else {
                    $this->bounceProcessError('Process Killed by other process');
                }

                if (sprintf('%d',$bounce['bounce']) == $bounce['bounce']) {
                    $cnt++;
                    if ($cnt >= Config::BOUNCE_UNSUBSCRIBE_THRESHOLD) {
                        if (!$unsubscribed) {
                            $this->output(sprintf('unsubscribing %d -> %d bounces',$user[0],$cnt));
                            $userurl = PageLink2("user&amp;id=$user[0]",$user[0]);
                            Logger::logEvent(
                                s('User (url:%s) has consecutive bounces (%d) over threshold (%d), user marked unconfirmed',
                                    $userurl,
                                    $cnt,
                                    Config::BOUNCE_UNSUBSCRIBE_THRESHOLD
                                ));
                            User::addHistory(s('Auto Unconfirmed'),s('Subscriber auto unconfirmed for %d consecutive bounces',$cnt), $user[0]);
                            phpList::DB()->query(sprintf(
                                    'UPDATE %s SET confirmed = 0
                                    WHERE id = %d',
                                    Config::getTableName('user', true),
                                    $user[0]
                                ));
                            $email_req = phpList::DB()->query(sprintf('SELECT email FROM %s WHERE id = %d', Config::getTableName('user', true), $user[0]));
                            $unsubscribed_users .= $email_req[0]."\t\t($cnt)\t\t". $GLOBALS['scheme'].'://'.getConfig('website').$GLOBALS['adminpages'].'/?page=user&amp;id='.$user[0]. "\n";
                            $unsubscribed = 1;
                        }
                        if (Config::get('BLACKLIST_EMAIL_ON_BOUNCE') && $cnt >= Config::get('BLACKLIST_EMAIL_ON_BOUNCE')) {
                            $removed = 1;
                            #0012262: blacklist email when email bounces
                            Output::cl_output(s('%d consecutive bounces, threshold reached, blacklisting subscriber',$cnt));
                            User::blacklistUser($user[0], s('%d consecutive bounces, threshold reached',$cnt));
                        }
                    }
                } elseif ($bounce['bounce'] == '') {
                    #$cnt = 0; DT 051105
                    $cnt = 0;
                    $msgokay = 1; #DT 051105 - escaping loop if message received okay
                }
            }
            if ($usercnt % 5 == 0) {
            #    output(s("Identifying consecutive bounces"));
                Output::cl_output(s('processed %d out of %d subscribers',$usercnt, $total),1);
            }
            $usercnt++;
            flush();
        }
        if (Config::get('commandline')) {
            print '<script language="Javascript" type="text/javascript"> finish(); </script>';
        }

        #output(s("Identifying consecutive bounces"));
        $this->output("\n".s('total of %d subscribers processed',$total). '                            ');

        /*
        $report = '';

        if (Logger::getReport()) {
            $report .= s('Report:')."\n" . Logger::getReport() . "\n";
        }

        if ($advanced_report) {
            $report .= s('Report of advanced bounce processing:')."\n$advanced_report\n";
        }
        if ($unsubscribed_users) {
            $report .= "\n".s('Below are users who have been marked unconfirmed. The in () is the number of consecutive bounces.')."\n";
            $report .= "\n$unsubscribed_users";
        } else {
            # don't send a report email, if only some bounces were downloaded, but no users unsubscribed.
            $report = '';
        }
        # shutdown will take care of reporting
        #finish("info",$report);
        */
        # IMAP errors following when Notices are on are a PHP bug
        # http://bugs.php.net/bug.php?id=7207

    }

    /**
     * @throws \Exception
     */
    private function preflightCheck()
    {
        if (!function_exists('imap_open')) {
            throw new \Exception(
                "IMAP is not included in your PHP installation, cannot continue\n".
                s('Check out').
                "\n <a href=\"http://www.php.net/manual/en/ref.imap.php\">http://www.php.net/manual/en/ref.imap.php</a>"
            );
        }

        if (Config::BOUNCE_MAILBOX == ''
            && (Config::BOUNCE_MAILBOX_HOST == ''
                || Config::BOUNCE_MAILBOX_USER == ''
                || Config::BOUNCE_MAILBOX_PASSWORD == ''
            )
        ) {
            throw new \Exception(s('Bounce mechanism not properly configured'));
        }
    }

    public function finish ($flag, $message) {
        if (!Config::TEST && $message) {
            $subject = s('Bounce Processing info');
            if ($flag == 'error') {
                $subject = s('Bounce processing error');
            }
            phpListMailer::sendReport($subject, $message);
        }
    }

    private function bounceProcessError ($message) {
        $this->output($message);
        $this->finish('error', $message);
        exit;
    }

    public function processBouncesShutdown() {
        Process::releaseLock($this->process_id);
        # Logger::addToReport('Connection status:'.connection_status());
        $this->finish('info', Logger::getReport());
    }

    private function output ($message, $reset = false) {
        #$infostring = "[". date("D j M Y H:i",time()) . "] [" . getenv("REMOTE_HOST") ."] [" . getenv("REMOTE_ADDR") ."]";
        #print "$infostring $message<br/>\n";
        $message = preg_replace('/\n/','',$message);
        ## contribution from http://forums.phplist.com/viewtopic.php?p=14648
        ## in languages with accented characters replace the HTML back
        //Replace the "&rsquo;" which is not replaced by html_decode
        $message = preg_replace('/&rsquo;/',"'",$message);
        //Decode HTML chars
        #$message = html_entity_decode($message,ENT_QUOTES,$_SESSION['adminlanguage']['charset']);
        $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
        if (Config::get('commandline')) {
            Output::cl_output($message);
        } else {
            if ($reset)
                print
    '<script language="Javascript" type="text/javascript">
          document.outputform.output.value = "";
          document.outputform.output.value += "\n";
     </script>';

            printf(
    '<script language="Javascript" type="text/javascript">
        document.outputform.output.value += "%s";
        document.outputform.output.value += "\n";
    </script>',
                $message
            );
        }
        flush();
    }

    /**
     * Try to find the message id in the header
     * @param string $text
     * @return int|string
     */
    private function findMessageId($text) {
        $msgid = 0;
        preg_match ('/X-MessageId: (.*)\R/iU', $text, $match);
        if (is_array($match) && isset($match[1])) {
            $msgid = trim($match[1]);
        }
        if (!$msgid) {
            # older versions use X-Message
            preg_match ('/X-Message: (.*)\R/iU', $text, $match);
            if (is_array($match) && isset($match[1])) {
                $msgid = trim($match[1]);
            }
        }
        return $msgid;
    }

    /**
     * Find which user this message was sent to
     * @param string $text
     * @return bool|User
     */
    private function findUser($text) {
        $user = false;
        $user_id = '';
        preg_match ('/X-ListMember: (.*)\R/iU',$text,$match);
        if (is_array($match) && isset($match[1])) {
            $user_id = trim($match[1]);
        } else {
            # older version use X-User
            preg_match ('/X-User: (.*)\R/iU',$text,$match);
            if (is_array($match) && isset($match[1])) {
                $user_id = trim($match[1]);
            }
        }
        if($user_id != ''){
            # some versions used the email to identify the users, some the userid and others the uniqid
            # use backward compatible way to find user
            if (strpos($user_id,'@') !== false) {
                $user = User::getUserByEmail($user_id);
            } elseif (preg_match('/^\d$/',$user_id)) {
                $user = User::getUser($user_id);
            } elseif (!empty($user_id)) {
                $user = User::getUserByUniqueId($user_id);
            }
        }

        if($user === false){
            ### if we didn't find any, parse anything looking like an email address and check if it's a subscriber.
            ## this is probably fairly time consuming, but as the process is only done once every so often
            ## that should not be too bad

            preg_match_all('/[\S]+@[\S\.]+/',$text,$regs);
            foreach ($regs[0] as $match) {
                $userObj = User::getUserByEmail(Util::cleanEmail($match));
                if ($userObj !== false) {
                    return $userObj;
                }
            }
        }
        return $user;
    }

    private function decodeBody($header, $body) {
        $transfer_encoding = '';
        if (preg_match('/Content-Transfer-Encoding: ([\w-]+)/i',$header,$regs)) {
            $transfer_encoding = strtolower($regs[1]);
        }
        switch ($transfer_encoding) {
            case 'quoted-printable':
                $decoded_body = @imap_qprint($body);break;
            case 'base64':
                $decoded_body = @imap_base64($body);break;
            case '7bit':
            case '8bit':
            default:
                # $body = $body;
        }
        if (!empty($decoded_body)) {
            return $decoded_body;
        } else {
            return $body;
        }
    }

    private function processImapBounce ($link, $num, $header) {
        $headerinfo = imap_headerinfo($link,$num);
        $bounceDate = @strtotime($headerinfo->date);
        $body = imap_body ($link,$num);
        $body = $this->decodeBody($header,$body);

        $message_id = $this->findMessageId($body);
        $user = $this->findUser($body);
        if (Config::VERBOSE) {
            Output::output('UID'.$user->id.' MSGID'.$message_id);
        }

        ## @TODO add call to plugins to determine what to do.
        # for now, quick hack to zap MsExchange Delayed messages
        if (preg_match('/Action: delayed\s+Status: 4\.4\.7/im',$body)) {
            ## just say we did something, when actually we didn't
            return true;
        }
        $bounce = new Bounce();
        $bounce->date = new \DateTime($bounceDate);
        $bounce->header = $header;
        $bounce->data = $body;
        $bounce->save();

        return $this->processBounceData($bounce, $message_id, $user);
    }

    /**
     * Porcess the bounce data and update the database
     * @param Bounce $bounce
     * @param int $message_id
     * @param User $user
     * @return bool
     */
    private function processBounceData($bounce, $message_id, $user) {

        if ($message_id === 'systemmessage' && $user !== false) {
            $bounce->status = 'bounced system message';
            $bounce->comment = sprintf('%s marked unconfirmed', $user->id);
            $bounce->update();
            Logger::logEvent($user->id . ' '.s('system message bounced, user marked unconfirmed'));
            $user->addHistory(
                s('Bounced system message'),
                sprintf(
                    '<br/>%s<br/><a href="./?page=bounce&amp;id=%d">%s</a>',
                    s('User marked unconfirmed'),
                    $bounce->id,
                    s('View Bounce')
                ),
                $user->id
            );
            $user->confirmed = 0;
            $user->update();
        } elseif (!empty($message_id) && $user !== false) {
            $bounce->connectMeToUserAndMessage($user, $message_id);
        } elseif ($user !== false) {
            $bounce->status = 'bounced unidentified message';
            $bounce->comment = $user->id . ' bouncecount increased';
            $bounce->update();

            $user->bouncecount++;
            $user->update();
        } elseif ($message_id === 'systemmessage') {
            $bounce->status = 'bounced system message';
            $bounce->comment = 'unknown user';
            $bounce->update();
            Logger::logEvent($user->id . ' ' . s('system message bounced, but unknown user'));
        } elseif ($message_id) {
            $bounce->status = sprintf('bounced list message %d', $message_id);
            $bounce->comment = 'unknown user';
            $bounce->update();
            phpList::DB()->query(sprintf(
                    'UPDATE %s
                     SET bouncecount = bouncecount + 1
                     WHERE id = %d',
                    Config::getTableName('message'),
                    $message_id
                ));
        } else {
            $bounce->status = 'unidentified bounce';
            $bounce->comment = 'not processed';
            $bounce->update();
            return false;
        }
        return true;
    }

    //TODO: move to separate class
    /**
     * @param string $server
     * @param string $user
     * @param string $password
     * @return bool
     * @throws \Exception
     */
    private function processPop ($server, $user, $password) {
        $port = Config::BOUNCE_MAILBOX_PORT;
        if (!$port) {
            $port = '110/pop3/notls';
        }
        set_time_limit(6000);

        if (!Config::TEST) {
            $link= imap_open('{'.$server.':'.$port.'}INBOX',$user,$password,CL_EXPUNGE);
        } else {
            $link= imap_open('{'.$server.':'.$port.'}INBOX',$user,$password);
        }

        if (!$link) {
            throw new \Exception(s("Cannot create POP3 connection to")." $server: ".imap_last_error());
        }
        return $this->processMessages($link,100000);
    }

    //TODO: move to separate class
    /**
     * @param string $file
     * @return bool
     * @throws \Exception
     */
    private function processMbox ($file) {
        set_time_limit(6000);

        if (!Config::TEST) {
            $link= imap_open($file,'','',CL_EXPUNGE);
        } else {
            $link= imap_open($file,'','');
        }
        if (!$link) {
            throw new \Exception(s('Cannot open mailbox file').' '.imap_last_error());
        }
        return $this->processMessages($link, 100000);
    }

    /**
     * @param resource $link IMAP stream
     * @param int $max
     * @return bool
     */
    private function processMessages($link,$max = 3000) {
        $num = imap_num_msg($link);
        $this->output( $num . ' '.s('bounces to fetch from the mailbox')."\n");
        $this->output( s('Please do not interrupt this process')."\n");
        $report = $num . ' '.s('bounces to process')."\n";
        if ($num > $max) {
            print s('Processing first')." $max ".s('bounces').'<br/>';
            Logger::addToReport($num . ' '.s('processing first')." $max ".s('bounces')."\n");
            $num = $max;
        }
        if (Config::TEST) {
            print s('Running in test mode, not deleting messages from mailbox').'<br/>';
        } else {
            print s('Processed messages will be deleted from mailbox').'<br/>';
        }
        #  for ($x=1;$x<150;$x++) {
        for($x=1; $x <= $num; $x++) {
            set_time_limit(60);
            $header = imap_fetchheader($link,$x);
            if ($x % 25 == 0)
                #    $this->output( $x . " ". nl2br($header));
                $this->output($x . ' done',1);
            print "\n";
            flush();
            $processed = $this->processImapBounce($link,$x,$header);
            if ($processed) {
                if (!Config::TEST && Config::BOUNCE_MAILBOX_PURGE) {
                    if (Config::VERBOSE) $this->output( s('Deleting message').' '.$x);
                    imap_delete($link,$x);
                } elseif (Config::VERBOSE) {
                    $this->output(s('Not deleting processed message').' '.$x.' '.Config::BOUNCE_MAILBOX_PURGE);
                }
            } else {
                if (!Config::TEST && Config::BOUNCE_MAILBOX_PURGE_UNPROCESSED) {
                    if (Config::VERBOSE) $this->output( s('Deleting message').' '.$x);
                    imap_delete($link,$x);
                } elseif (Config::VERBOSE) {
                    $this->output(s('Not deleting unprocessed message').' '.$x);
                }
            }
            flush();
        }
        flush();
        $this->output(s('Closing mailbox, and purging messages'));
        set_time_limit(60 * $num);
        imap_close($link);
        /*if ($num)
            return $report;*/
        return ($num>0);
    }

} 