<?php
namespace phpList\helper;

use phpList\phpList;
use phpList\Config;
use phpList\Subscriber;

class Util
{
    protected  $config;
    protected  $logger;
    protected  $db;

    public function __construct(Config $config, Logger $logger, Database $db)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->db = $db;
    }
    
    public function secs2time($secs)
    {
        $years = $days = $hours = $mins = 0;
        $hours = (int)($secs / 3600);
        $secs = $secs - ($hours * 3600);
        if ($hours > 24) {
            $days = (int)($hours / 24);
            $hours = $hours - (24 * $days);
        }
        if ($days > 365) { ## a well, an estimate
            $years = (int)($days / 365);
            $days = $days - ($years * 365);
        }
        $mins = (int)($secs / 60);
        $secs = (int)($secs % 60);

        $res = '';
        if ($years) {
            $res .= $years . ' ' . s('years');
        }
        if ($days) {
            $res .= ' ' . $days . ' ' . s('days');
        }
        if ($hours) {
            $res .= ' ' . $hours . ' ' . s('hours');
        }
        if ($mins) {
            $res .= " " . $mins . ' ' . s('mins');
        }
        if ($secs) {
            $res .= " " . sprintf('%02d', $secs) . ' ' . s('secs');
        }
        return $res;
    }

    /**
     * Verify that a redirection is to ourselves
     * @param string $url
     * @return int
     */
    public function isValidRedirect($url)
    {
        ## we might want to add some more checks here
        return strpos($url, $_SERVER['HTTP_HOST']);
    }

    /**
     * Check the url_append config and expand the url with it
     * @param $url
     * @return string
     */
    public function expandURL($url)
    {
        $url_append = $this->config->get('remoteurl_append');
        $url_append = strip_tags($url_append);
        $url_append = preg_replace('/\W/', '', $url_append);
        if ($url_append) {
            if (strpos($url, '?')) {
                $url = $url . $url_append;
            } else {
                $url = $url . '?' . $url_append;
            }
        }
        return $url;
    }

    /**
     * Test a URL
     * @param string $url
     * @return int|mixed
     */
    public function testUrl($url)
    {
        if ($this->config->get('VERBOSE')) {
            $this->logger->notice('Checking ' . $url);
        }
        $code = 500;
        if ($this->config->get('has_curl')) {
            if ($this->config->get('VERBOSE')) $this->logger->notice('Checking curl ');
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'phplist v' . PHPLIST_VERSION . ' (http://www.phplist.com)');
            curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } elseif ($this->config->get('has_pear_http_request')) {
            if ($this->config->get('VERBOSE')) $this->logger->notice('Checking PEAR ');
            @require_once "HTTP/Request.php";
            $headreq = new HTTP_Request($url, '' /*$request_parameters*/);
            $headreq->addHeader('User-Agent', 'phplist v' . PHPLIST_VERSION . ' (http://www.phplist.com)');
            if (!PEAR::isError($headreq->sendRequest(false))) {
                $code = $headreq->getResponseCode();
            }
        }
        if ($this->config->get('VERBOSE')) $this->logger->notice('Checking ' . $url . ' => ' . $code);
        return $code;
    }

    /**
     * Fetch a URL
     * @param string $url
     * @param Subscriber $subscriber
     * @return bool|int|mixed|string
     */
    public function fetchUrl($url, $subscriber = null)
    {
        $content = '';
        ## fix the Editor replacing & with &amp;
        $url = str_ireplace('&amp;', '&', $url);

        # $this->logger->notice("Fetching $url");
        //subscriber items to replace:
        if ($subscriber != null) {
            foreach (Subscriber::$DB_ATTRIBUTES as $key) {
                if ($key != 'password') {
                    $url = utf8_encode(str_ireplace("[$key]", urlencode($subscriber->$key), utf8_decode($url)));
                }
            }
        }

        $url = $this->expandUrl($url);
        #  print "<h1>Fetching ".$url."</h1>";

        # keep in memory cache in case we send a page to many emails

        $cache = Cache::instance();
        if (isset($cache->url_cache[$url]) && is_array($cache->url_cache[$url])
            && (time() - $cache->url_cache[$url]['fetched'] < $this->config->get('REMOTE_URL_REFETCH_TIMEOUT'))
        ) {
        #$this->logger->notice($url . " is cached in memory");
            if ($this->config->get('VERBOSE') && function_exists('output')) {
                output('From memory cache: ' . $url);
            }
            return $cache->url_cache[$url]['content'];
        }

        $timeout = time() - Cache::getPageCacheLastModified($url);
        if ($timeout < $this->config->get('REMOTE_URL_REFETCH_TIMEOUT')) {
        #$this->logger->notice($url.' was cached in database');
            if ($this->config->get('VERBOSE') && function_exists('output')) {
                output('From database cache: ' . $url);
            }
            return Cache::getPageCache($url);
        } else {
        #$this->logger->notice($url.' is not cached in database '.$timeout.' '. $dbcache_lastmodified." ".time());
        }

        $request_parameters = array(
            'timeout' => 600,
            'allowRedirects' => 1,
            'method' => 'HEAD',
        );

        //$remote_charset = 'UTF-8';
        ## relying on the last modified header doesn't work for many pages
        ## use current time instead
        ## see http://mantis.phplist.com/view.php?id=7684
        #$lastmodified = strtotime($header["last-modified"]);
        $lastmodified = time();
        $cache = Cache::getPageCache($url, $lastmodified);
        if (!$cache) {
            ## @#TODO, make it work with Request2
            if (function_exists('curl_init')) {
                $content = $this->fetchUrlCurl($url, $request_parameters);
            } elseif (0 && $this->config->get('has_pear_http_request') == 2) {
                @require_once "HTTP/Request2.php";
            } elseif ($this->config->get('has_pear_http_request')) {
                @require_once "HTTP/Request.php";
                $content = $this->fetchUrlPear($url, $request_parameters);
            } else {
                return false;
            }
        } else {
            if ($this->config->get('VERBOSE')) $this->logger->notice($url . ' was cached in database');
            $content = $cache;
        }

        if (!empty($content)) {
            $content = $this->addAbsoluteResources($content, $url);
            $this->logger->notice('Fetching ' . $url . ' success');
            Cache::setPageCache($url, $lastmodified, $content);

            Cache::instance()->url_cache[$url] = array(
                'fetched' => time(),
                'content' => $content,
            );

        }

        return $content;
    }

    public function fetchUrlCurl($url, $request_parameters)
    {
        if ($this->config->get('VERBOSE')) $this->logger->notice($url . ' fetching with curl ');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, $request_parameters['timeout']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'phplist v' . $this->config->get('VERSION') . 'c (http://www.phplist.com)');
        $raw_result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($this->config->get('VERBOSE')) $this->logger->notice('fetched ' . $url . ' status ' . $status);
        #var_dump($status); exit;
        return $raw_result;
    }

    //TODO: convert to using namespaces
    public function fetchUrlPear($url, $request_parameters)
    {
        if ($this->config->get('VERBOSE')) $this->logger->notice($url . ' fetching with PEAR');

        if (0 && $this->config->get('has_pear_http_request')== 2) {
            $headreq = new HTTP_Request2($url, $request_parameters);
            $headreq->setHeader('User-Agent', 'phplist v' . $this->config->get('VERSION') . 'p (http://www.phplist.com)');
        } else {
            $headreq = new HTTP_Request($url, $request_parameters);
            $headreq->addHeader('User-Agent', 'phplist v' . $this->config->get('VERSION') . 'p (http://www.phplist.com)');
        }
        if (!PEAR::isError($headreq->sendRequest(false))) {
            $code = $headreq->getResponseCode();
            if ($code != 200) {
                $this->logger->notice('Fetching ' . $url . ' failed, error code ' . $code);
                return 0;
            }
            $header = $headreq->getResponseHeader();

            if (preg_match('/charset=(.*)/i', $header['content-type'], $regs)) {
                $remote_charset = strtoupper($regs[1]);
            }

            $request_parameters['method'] = 'GET';
            if (0 && $this->config->get('has_pear_http_request') == 2) {
                $request = new HTTP_Request2($url, $request_parameters);
                $request->setHeader('User-Agent', 'phplist v' . $this->config->get('VERSION') . 'p (http://www.phplist.com)');
            } else {
                $request = new HTTP_Request($url, $request_parameters);
                $request->addHeader('User-Agent', 'phplist v' . $this->config->get('VERSION') . 'p (http://www.phplist.com)');
            }
            $this->logger->notice('Fetching ' . $url);
            if ($this->config->get('VERBOSE') && function_exists('output')) {
                output('Fetching remote: ' . $url);
            }
            if (!PEAR::isError($request->sendRequest(true))) {
                $content = $request->getResponseBody();

                if ($remote_charset != 'UTF-8' && function_exists('iconv')) {
                    $content = iconv($remote_charset, 'UTF-8//TRANSLIT', $content);
                }

            } else {
                $this->logger->notice('Fetching ' . $url . ' failed on GET ' . $request->getResponseCode());
                return 0;
            }
        } else {
            $this->logger->notice('Fetching ' . $url . ' failed on HEAD');
            return 0;
        }

        return $content;
    }

    public function cleanUrl($url,$disallowed_params = array('PHPSESSID')) {
        $parsed = @parse_url($url);
        $params = array();
        if (empty($parsed['query'])) {
            $parsed['query'] = '';
        }
        # hmm parse_str should take the delimiters as a parameter
        if (strpos($parsed['query'],'&amp;')) {
            $pairs = explode('&amp;',$parsed['query']);
            foreach ($pairs as $pair) {
                if (strpos($pair,'=') !== false) {
                    list($key,$val) = explode('=',$pair);
                    $params[$key] = $val;
                } else {
                    $params[$pair] = '';
                }
            }
        } else {
            ## parse_str turns . into _ which is wrong
        #    parse_str($parsed['query'],$params);
            $params= $this->parseQueryString($parsed['query']);
        }
        $uri = !empty($parsed['scheme']) ? $parsed['scheme'].':'.((strtolower($parsed['scheme']) == 'mailto') ? '':'//'): '';
        $uri .= !empty($parsed['user']) ? $parsed['user'].(!empty($parsed['pass'])? ':'.$parsed['pass']:'').'@':'';
        $uri .= !empty($parsed['host']) ? $parsed['host'] : '';
        $uri .= !empty($parsed['port']) ? ':'.$parsed['port'] : '';
        $uri .= !empty($parsed['path']) ? $parsed['path'] : '';
        #  $uri .= $parsed['query'] ? '?'.$parsed['query'] : '';
        $query = '';
        foreach ($params as $key => $val) {
            if (!in_array($key,$disallowed_params)) {
                //0008980: Link Conversion for Click Tracking. no = will be added if key is empty.
                $query .= $key . ( $val != "" ? '=' . $val . '&' : '&' );
            }
        }
        $query = substr($query,0,-1);
        $uri .= $query ? '?'.$query : '';
        #  if (!empty($params['p'])) {
        #    $uri .= '?p='.$params['p'];
        #  }
        $uri .= !empty($parsed['fragment']) ? '#'.$parsed['fragment'] : '';
        return $uri;
    }

    public function parseQueryString($str) {
        if (empty($str)) return array();
        $op = array();
        $pairs = explode('&', $str);
        foreach ($pairs as $pair) {
            if (strpos($pair,'=') !== false) {
                list($k, $v) = array_map('urldecode', explode('=', $pair));
                $op[$k] = $v;
            } else {
                $op[$pair] = '';
            }
        }
        return $op;
    }

    public function addAbsoluteResources($text,$url)
    {
        $parts = parse_url($url);
        $tags = array('src\s*=\s*','href\s*=\s*','action\s*=\s*','background\s*=\s*','@import\s+','@import\s+url\(');
        foreach ($tags as $tag) {
        #   preg_match_all('/'.preg_quote($tag).'"([^"|\#]*)"/Uim', $text, $foundtags);
        # we're only handling nicely formatted src="something" and not src=something, ie quotes are required
        # bit of a nightmare to not handle it with quotes.
            preg_match_all('/('.$tag.')"([^"|\#]*)"/Uim', $text, $foundtags);
            for ($i=0; $i< count($foundtags[0]); $i++) {
                $match = $foundtags[2][$i];
                $tagmatch = $foundtags[1][$i];
                #      print "$match<br/>";
                if (preg_match("#^(http|javascript|https|ftp|mailto):#i",$match)) {
                    # scheme exists, leave it alone
                } elseif (preg_match("#\[.*\]#U",$match)) {
                    # placeholders used, leave alone as well
                } elseif (preg_match("/^\//",$match)) {
                    # starts with /
                    $text = preg_replace('#'.preg_quote($foundtags[0][$i]).'#im',$tagmatch.'"'.$parts['scheme'].'://'.$parts['host'].$match.'"',$text,1);
                } else {
                    $path = '';
                    if (isset($parts['path'])) {
                        $path = $parts['path'];
                    }
                    if (!preg_match('#/$#',$path)) {
                        $pathparts = explode('/',$path);
                        array_pop($pathparts);
                        $path = join('/',$pathparts);
                        $path .= '/';
                    }
                    $text = preg_replace('#'.preg_quote($foundtags[0][$i]).'#im',
                        $tagmatch.'"'.$parts['scheme'].'://'.$parts['host'].$path.$match.'"',$text,1);
                }
            }
        }

        # $text = preg_replace('#PHPSESSID=[^\s]+
        return $text;
    }

    public function timeDiff($time1,$time2) {
        if (!$time1 || !$time2) {
            return s('Unknown');
        }
        $t1 = strtotime($time1);
        $t2 = strtotime($time2);

        if ($t1 < $t2) {
            $diff = $t2 - $t1;
        } else {
            $diff = $t1 - $t2;
        }
        if ($diff == 0)
            return s('very little time');
        return $this->secs2time($diff);
    }

    /**
     * Turn register globals off, even if it's on
     * taken from Wordpress
     *
     * @access public
     * @since 2.2.10
     * @return null Will return null if register_globals PHP directive was disabled
     */
    public function unregister_GLOBALS()
    {
        if ( !ini_get('register_globals') )
            return;

        ## https://mantis.phplist.com/view.php?id=16882
        ## no need to do this on commandline
        if (php_sapi_name() == "cli")
            return;

        if ( isset($_REQUEST['GLOBALS']) )
            die('GLOBALS overwrite attempt detected');

        // Variables that shouldn't be unset
        $noUnset = array('GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');

        $input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES, isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array());
        foreach ( $input as $k => $v ){
            if ( !in_array($k, $noUnset) && isset($GLOBALS[$k]) ) {
                $GLOBALS[$k] = NULL;
                unset($GLOBALS[$k]);
            }
        }

    }

    //TODO: should be removed
    public function magicQuotes()
    {
        if (!ini_get('magic_quotes_gpc') || ini_get('magic_quotes_gpc') == 'off') {
            $_POST = $this->addSlashesArray($_POST);
            $_GET = $this->addSlashesArray($_GET);
            $_REQUEST = $this->addSlashesArray($_REQUEST);
            $_COOKIE = $this->addSlashesArray($_COOKIE);
            $this->config->setRunningConfig('NO_MAGIC_QUOTES', true);
        }else{
            #magic quotes are deprecated, so try to switch off if possible
            ini_set('magic_quotes_gpc','off');
            $this->config->setRunningConfig('NO_MAGIC_QUOTES', false);
        }
    }

    public function addSlashesArray($array)
    {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $array[$key] = $this->addSlashesArray($val);
            } else {
                $array[$key] = addslashes($val);
            }
        }
        return $array;
    }

    public function removeXss($string)
    {
        if (is_array($string)) {
            $return = array();
            foreach ($string as $key => $val) {
                $return[$this->removeXss($key)] = $this->removeXss($val);
            }
            return $return;
        }
        #$string = preg_replace('/<script/im','&lt;script',$string);
        $string = htmlspecialchars($string);
        return $string;
    }

    public function parseCline() {
        $res = array();
        $cur = "";
        foreach ($GLOBALS['argv'] as $clinearg) {
            if (substr($clinearg,0,1) == '-') {
                $par = substr($clinearg,1,1);
                $clinearg = substr($clinearg,2,strlen($clinearg));
                # $res[$par] = "";
                $cur = strtolower($par);
                $res[$cur] .= $clinearg;
            } elseif ($cur) {
                if ($res[$cur])
                    $res[$cur] .= ' '.$clinearg;
                else
                    $res[$cur] .= $clinearg;
            }
        }
        /*  ob_end_clean();
          foreach ($res as $key => $val) {
            print "$key = $val\n";
          }
          ob_start();*/
        return $res;
    }


    public function clean2 ($value) {
        $value = trim($value);
        $value = preg_replace("/\r/","",$value);
        $value = preg_replace("/\n/","",$value);
        $value = str_replace('"',"&quot;",$value);
        $value = str_replace("'","&rsquo;",$value);
        $value = str_replace("`","&lsquo;",$value);
        $value = stripslashes($value);
        return $value;
    }

    public function cleanEmail ($value) {
        $value = trim($value);
        $value = preg_replace("/\r/","",$value);
        $value = preg_replace("/\n/","",$value);
        $value = preg_replace('/"/',"&quot;",$value);
        $value = preg_replace('/^mailto:/i','',$value);
        $value = str_replace('(','',$value);
        $value = str_replace(')','',$value);
        $value = preg_replace('/\.$/','',$value);

        ## these are allowed in emails
        //  $value = preg_replace("/'/","&rsquo;",$value);
        $value = preg_replace("/`/","&lsquo;",$value);
        $value = stripslashes($value);
        return $value;
    }

    /**
     * Check if an email addres is blacklisted
     * $immediate specifies if a gracetime is allowed for a last message
     * @param string $email
     * @param bool $immediate
     * @return bool
     */
    public function isEmailBlacklisted($email, $immediate = true)
    {
        //TODO: @Michiel why is there a check on the blacklist table?
        if (!$this->db->tableExists($this->config->getTableName('user_blacklist'))) return false;
        if (!$immediate) {
            # allow 5 minutes to send the last message acknowledging unsubscription
            $gracetime = sprintf('%d', $this->config->get('BLACKLIST_GRACETIME'));
            if (!$gracetime || $gracetime > 15 || $gracetime < 0) {
                $gracetime = 5;
            }
        } else {
            $gracetime = 0;
        }
        $result = $this->db->query(
            sprintf(
                'SELECT COUNT(email) FROM %s
                WHERE email = "%s"
                AND date_add(added, interval %d minute) < CURRENT_TIMESTAMP)',
                $this->config->getTableName('user_blacklist'),
                $this->db->sqlEscape($email),
                $gracetime
            )
        );
        return ($result->fetchColumn(0) > 0);
    }

    /**
     * Check if the subscriber with given id is blacklisted
     * @param int $subscriber_id
     * @return bool
     */
    public function isSubscriberIDBlacklisted($subscriber_id = 0)
    {
        $subscriber = Subscriber::getSubscriber($subscriber_id);
        return ($subscriber == null || $subscriber->blacklisted == 0) ? false : true;
    }

    /**
     * Blacklist a subscriber by his email address
     * @param string $email_address
     * @param string $reason
     */
    public function blacklistSubscriberByEmail($email_address, $reason = '')
    {
        #0012262: blacklist only email when email bounces. (not subscribers): Function split so email can be blacklisted without blacklisting subscriber
        $subscriber = Subscriber::getSubscriberByEmailAddress($email_address);
        $subscriber->blacklisted = true;
        $subscriber->save();
        Subscriber::addHistory(s('Added to blacklist'), s('Added to blacklist for reason %s', $reason), $subscriber->id);
    }

    /**
     * Blacklist an email address, not a subscriber specifically
     * @param string $email_address
     * @param string $reason
     * @param string $date
     */
    public function blacklistEmail($email_address, $reason = '', $date = '')
    {
        if (empty($date)) {
            $sqldate = 'CURRENT_TIMESTAMP';
        } else {
            $sqldate = '"' . $date . '"';
        }
        $email_address = String::sqlEscape($email_address);

        #0012262: blacklist only email when email bounces. (not subscribers): Function split so email can be blacklisted without blacklisting subscriber
        $this->db->query(
            sprintf(
                'INSERT IGNORE INTO %s (email,added)
                VALUES("%s",%s)',
                $this->config->getTableName('user_blacklist'),
                String::sqlEscape($email_address),
                $sqldate
            )
        );

        # save the reason, and other data
        $this->db->query(
            sprintf(
                'INSERT IGNORE INTO %s (email, name, data)
                VALUES("%s","%s","%s"),
                ("%s","%s","%s")',
                $this->config->getTableName('user_blacklist_data'),
                $email_address,
                'reason',
                addslashes($reason),
                $email_address,
                'REMOTE_ADDR',
                addslashes($_SERVER['REMOTE_ADDR'])
            )
        );

        /*foreach (array("REMOTE_ADDR") as $item ) { # @@@do we want to know more?
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $this->db->Sql_Query(sprintf(
                    'INSERT IGNORE INTO %s (email, name, data)
                    VALUES("%s","%s","%s")',
                    $this->config->getTableName('user_blacklist_data'),addslashes($email_address),
                    $item,addslashes($_SERVER['REMOTE_ADDR'])));
            }
        }*/
        //when blacklisting only an email address, don't add this to the history, only do this when blacklisting a subscriber
        //addSubscriberHistory($email_address,s('Added to blacklist'),s('Added to blacklist for reason %s',$reason));
    }

    /**
     * Remove subscriber from blacklist
     * @param int $subscriber_id
     * @param string $admin_name
     */
    public function unBlackList($subscriber_id = 0, $admin_name = '')
    {
        if (!$subscriber_id) return;
        $subscriber = Subscriber::getSubscriber($subscriber_id);

        $tables = array(
            $this->config->getTableName('user_blacklist') => 'email',
            $this->config->getTableName('user_blacklist_data') => 'email'
        );
        $this->db->deleteFromArray($tables, $subscriber->getEmailAddress());

        $subscriber->blacklisted = 0;
        $subscriber->update();

        if ($admin_name != '') {
            $msg = s("Removed from blacklist by %s", $admin_name);
        } else {
            $msg = s('Removed from blacklist');
        }
        Subscriber::addHistory($msg, '', $subscriber->id);
    }


    /**
     * Check if email address exists in database
     * @param $email_address
     * @return bool
     */
    public function emailExists($email_address)
    {
        $prep_statement = $this->db->prepare(
            sprintf(
                'SELECT id FROM %s
                WHERE email = :email_address',
                $this->config->getTableName('user', true)
            )
        );
        $prep_statement->execute(array(':email_address' => $email_address));
        return ($prep_statement->rowCount() > 0);
    }

    /**
     * Parse the from field into it's components - email and name
     * @param $string
     * @param $default_address
     * @return array with fields 'email' and 'name'
     */
    public function parseEmailAndName($string, $default_address)
    {
        if (preg_match("/([^ ]+@[^ ]+)/", $string, $regs)) {
            # if there is an email in the from, rewrite it as "name <email>"
            $name = str_replace($regs[0], "", $string);
            $email_address = $regs[0];
            # if the email has < and > take them out here
            $email_address = str_replace('<', '', $email_address);
            $email_address = str_replace('>', '', $email_address);
            # make sure there are no quotes around the name
            $name = str_replace('"', '', ltrim(rtrim($name)));
        } elseif (strpos($string, ' ')) {
            # if there is a space, we need to add the email
            $name = $string;
            $email_address = $default_address;
        } else {
            $email_address = $default_address;
            $name = $string;
        }

        return [
            'email' =>  $email_address,
            'name'  =>  String::removeDoubleSpaces(trim($name))
        ];
    }


    public function addSubscriberStatistics($item = '', $amount, $list = 0) {
        switch ($this->config->get('STATS_INTERVAL')) {
            case 'monthly':
                # mark everything as the first day of the month
                $time = mktime(0,0,0,date('m'),1,date('Y'));
                break;
            case 'weekly':
                # mark everything for the first sunday of the week
                $time = mktime(0,0,0,date('m'),date('d') - date('w'),date('Y'));
                break;
            case 'daily':
            default:
                $time = mktime(0,0,0,date('m'),date('d'),date('Y'));
                break;
        }
        $result = $this->db->query(sprintf(
                'UPDATE %s
                SET value = value + %d
                WHERE unixdate = "%s"
                AND item = "%s"
                AND listid = %d',
                $this->config->getTableName('userstats'),
                $amount,
                $time,
                $item,
                $list
            ));
        if ($result->rowCount() <= 0) {
            //TODO: why not use REPLACE INTO?
            $this->db->query(sprintf(
                    'INSERT INTO %s (value, unixdate, item, listid)
                    VALUES("%s", "%s", "%s", %d)',
                    $this->config->getTableName('userstats'),
                    $amount,
                    $time,
                    $item,
                    $list
                ));
        }
    }
} 