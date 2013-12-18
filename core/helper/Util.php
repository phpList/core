<?php
/**
 * User: SaWey
 * Date: 17/12/13
 */

namespace phpList;


class Util
{
    public static function flushBrowser()
    {
        ## push some more output to the browser, so it displays things sooner
        for ($i = 0; $i < 10000; $i++) {
            print ' ' . "\n";
        }
        flush();
    }

    public static function flushClickTrackCache()
    {
        //TODO: remove globals and output
        if (!isset($GLOBALS['cached']['linktracksent'])) {
            return;
        }
        foreach ($GLOBALS['cached']['linktracksent'] as $mid => $numsent) {
            foreach ($numsent as $fwdid => $fwdtotal) {
                if (Config::VERBOSE) {
                    output("Flushing clicktrack stats for $mid: $fwdid => $fwdtotal");
                }
                phpList::DB()->query(
                    sprintf(
                        'UPDATE %s SET total = %d
                        WHERE messageid = %d
                        AND forwardid = %d',
                        Config::getTableName('linktrack_ml'),
                        $fwdtotal,
                        $mid,
                        $fwdid
                    )
                );
            }
        }
    }

    public static function cl_output($message)
    {
        if (Config::get('commandline')) {
            @ob_end_clean();
            print Config::get('installation_name') . ' - ' . strip_tags($message) . "\n";
            @ob_start();
        }
    }

    public static function secs2time($secs)
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
    public static function isValidRedirect($url)
    {
        ## we might want to add some more checks here
        return strpos($url, $_SERVER['HTTP_HOST']);
    }

    /**
     * Check the url_append config and expand the url with it
     * @param $url
     * @return string
     */
    public static function expandURL($url)
    {
        $url_append = Config::get('remoteurl_append');
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
    public static function testUrl($url)
    {
        if (Config::VERBOSE) {
            Logger::logEvent('Checking ' . $url);
        }
        $code = 500;
        if (Config::get('has_curl')) {
            if (Config::VERBOSE) Logger::logEvent('Checking curl ');
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'phplist v' . Config::get('VERSION') . ' (http://www.phplist.com)');
            $raw_result = curl_exec($curl);
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } elseif (Config::get('has_pear_http_request')) {
            if (Config::VERBOSE) Logger::logEvent('Checking PEAR ');
            @require_once "HTTP/Request.php";
            $headreq = new HTTP_Request($url, '' /*$request_parameters*/);
            $headreq->addHeader('User-Agent', 'phplist v' . Config::get('VERSION') . ' (http://www.phplist.com)');
            if (!PEAR::isError($headreq->sendRequest(false))) {
                $code = $headreq->getResponseCode();
            }
        }
        if (Config::VERBOSE) Logger::logEvent('Checking ' . $url . ' => ' . $code);
        return $code;
    }

    /**
     * Fetch a URL
     * @param string $url
     * @param array $userdata
     * @return bool|int|mixed|string
     */
    public static function fetchUrl($url, $userdata = array())
    {
        $content = '';
        ## fix the Editor replacing & with &amp;
        $url = str_ireplace('&amp;', '&', $url);

        # Logger::logEvent("Fetching $url");
        if (sizeof($userdata)) {
            foreach ($userdata as $key => $val) {
                if ($key != 'password') {
                    $url = utf8_encode(str_ireplace("[$key]", urlencode($val), utf8_decode($url)));
                }
            }
        }

        $url = Util::expandUrl($url);
        #  print "<h1>Fetching ".$url."</h1>";

        # keep in memory cache in case we send a page to many emails
        if (isset(Cache::urlCache()[$url]) && is_array(Cache::urlCache()[$url])
            && (time() - Cache::urlCache()[$url]['fetched'] < Config::REMOTE_URL_REFETCH_TIMEOUT)
        ) {
        #Logger::logEvent($url . " is cached in memory");
            if (Config::VERBOSE && function_exists('output')) {
                output('From memory cache: ' . $url);
            }
            return Cache::urlCache()[$url]['content'];
        }

        $timeout = time() - Cache::getPageCacheLastModified($url);
        if ($timeout < Config::REMOTE_URL_REFETCH_TIMEOUT) {
        #Logger::logEvent($url.' was cached in database');
            if (Config::VERBOSE && function_exists('output')) {
                output('From database cache: ' . $url);
            }
            return Cache::getPageCache($url);
        } else {
        #Logger::logEvent($url.' is not cached in database '.$timeout.' '. $dbcache_lastmodified." ".time());
        }

        $request_parameters = array(
            'timeout' => 600,
            'allowRedirects' => 1,
            'method' => 'HEAD',
        );

        $remote_charset = 'UTF-8';
        ## relying on the last modified header doesn't work for many pages
        ## use current time instead
        ## see http://mantis.phplist.com/view.php?id=7684
        #$lastmodified = strtotime($header["last-modified"]);
        $lastmodified = time();
        $cache = Cache::getPageCache($url, $lastmodified);
        if (!$cache) {
            ## @#TODO, make it work with Request2
            if (function_exists('curl_init')) {
                $content = Util::fetchUrlCurl($url, $request_parameters);
            } elseif (0 && Config::get('has_pear_http_request') == 2) {
                @require_once "HTTP/Request2.php";
            } elseif (Config::get('has_pear_http_request')) {
                @require_once "HTTP/Request.php";
                $content = Util::fetchUrlPear($url, $request_parameters);
            } else {
                return false;
            }
        } else {
            if (Config::VERBOSE) Logger::logEvent($url . ' was cached in database');
            $content = $cache;
        }

        if (!empty($content)) {
            $content = Util::addAbsoluteResources($content, $url);
            Logger::logEvent('Fetching ' . $url . ' success');
            Cache::setPageCache($url, $lastmodified, $content);

            Cache::urlCache()[$url] = array(
                'fetched' => time(),
                'content' => $content,
            );
        }

        return $content;
    }

    public static function fetchUrlCurl($url, $request_parameters)
    {
        if (Config::VERBOSE) Logger::logEvent($url . ' fetching with curl ');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, $request_parameters['timeout']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'phplist v' . Config::get('VERSION') . 'c (http://www.phplist.com)');
        $raw_result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (Config::VERBOSE) Logger::logEvent('fetched ' . $url . ' status ' . $status);
        #var_dump($status); exit;
        return $raw_result;
    }

    //TODO: convert to using namespaces
    public static function fetchUrlPear($url, $request_parameters)
    {
        if (Config::VERBOSE) Logger::logEvent($url . ' fetching with PEAR');

        if (0 && Config::get('has_pear_http_request')== 2) {
            $headreq = new HTTP_Request2($url, $request_parameters);
            $headreq->setHeader('User-Agent', 'phplist v' . Config::get('VERSION') . 'p (http://www.phplist.com)');
        } else {
            $headreq = new HTTP_Request($url, $request_parameters);
            $headreq->addHeader('User-Agent', 'phplist v' . Config::get('VERSION') . 'p (http://www.phplist.com)');
        }
        if (!PEAR::isError($headreq->sendRequest(false))) {
            $code = $headreq->getResponseCode();
            if ($code != 200) {
                Logger::logEvent('Fetching ' . $url . ' failed, error code ' . $code);
                return 0;
            }
            $header = $headreq->getResponseHeader();

            if (preg_match('/charset=(.*)/i', $header['content-type'], $regs)) {
                $remote_charset = strtoupper($regs[1]);
            }

            $request_parameters['method'] = 'GET';
            if (0 && Config::get('has_pear_http_request') == 2) {
                $req = new HTTP_Request2($url, $request_parameters);
                $req->setHeader('User-Agent', 'phplist v' . Config::get('VERSION') . 'p (http://www.phplist.com)');
            } else {
                $req = new HTTP_Request($url, $request_parameters);
                $req->addHeader('User-Agent', 'phplist v' . Config::get('VERSION') . 'p (http://www.phplist.com)');
            }
            Logger::logEvent('Fetching ' . $url);
            if (Config::VERBOSE && function_exists('output')) {
                output('Fetching remote: ' . $url);
            }
            if (!PEAR::isError($req->sendRequest(true))) {
                $content = $req->getResponseBody();

                if ($remote_charset != 'UTF-8' && function_exists('iconv')) {
                    $content = iconv($remote_charset, 'UTF-8//TRANSLIT', $content);
                }

            } else {
                Logger::logEvent('Fetching ' . $url . ' failed on GET ' . $req->getResponseCode());
                return 0;
            }
        } else {
            Logger::logEvent('Fetching ' . $url . ' failed on HEAD');
            return 0;
        }

        return $content;
    }

    public static function addAbsoluteResources($text,$url)
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
                        $path = $parts["path"];
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
} 