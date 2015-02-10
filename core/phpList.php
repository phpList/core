<?php
namespace phpList;

use phpList\helper\Database;
use phpList\helper\Language;
use phpList\helper\Util;
use phpList\helper\Timer;

class phpList
{
    protected $config;
    protected $lan;
    protected $db;

    /**
     * Default constructor
     */
    public function __construct(Config $config, Database $db, Language $lan)
    {
        $this->config = $config;
        $this->db = $db;
        $this->lan = $lan;

        date_default_timezone_set($this->config->get('SYSTEM_TIMEZONE'));

        Timer::start('pagestats');

        //Using phpmailer?
        if ($this->config->get('PHPMAILER_PATH') && is_file($this->config->get('PHPMAILER_PATH'))) {
            #require_once '/usr/share/php/libphp-phpmailer/class.phpmailer.php';
            require_once $this->config->get('PHPMAILER_PATH');
        }

        //Make sure some other inits have been executed
        Language::initialise();

        if(!DEBUG){
            error_reporting(0);
        }
        //check for commandline and cli version
        if (!isset($_SERVER['SERVER_NAME']) && PHP_SAPI != 'cli') {
            throw new \Exception('Warning: commandline only works well with the cli version of PHP');
        }
        if (isset($_REQUEST['_SERVER'])) { return; }

        //TODO: maybe move everything command line to separate class
        $cline = null;

        Util::unregister_GLOBALS();
        Util::magicQuotes();

        # setup commandline
        if (php_sapi_name() == 'cli' && !strstr($GLOBALS['argv'][0], 'phpunit')) {
            for ($i=0; $i<$_SERVER['argc']; $i++) {
                $my_args = array();
                if (preg_match('/(.*)=(.*)/',$_SERVER['argv'][$i], $my_args)) {
                    $_GET[$my_args[1]] = $my_args[2];
                    $_REQUEST[$my_args[1]] = $my_args[2];
                }
            }
            $this->config->setRunningConfig('commandline', true);
            $cline = Util::parseCLine();
            $dir = dirname($_SERVER['SCRIPT_FILENAME']);
            chdir($dir);

            if (!isset($cline['c']) || !is_file($cline['c'])) {
                throw new \Exception('Cannot find config file');
            }

        } else {
            $this->config->setRunningConfig('commandline', false);
        }
        $this->config->setRunningConfig('ajax', isset($_GET['ajaxed']));

        ##todo: this needs more testing, and docs on how to set the Timezones in the DB
        if ($this->config->get('USE_CUSTOM_TIMEZONE')) {
            #  print('set time_zone = "'.SYSTEM_TIMEZONE.'"<br/>');
            $this->db->query('SET time_zone = "'.$this->config->get('SYSTEM_TIMEZONE').'"');
            ## verify that it applied correctly
            $tz = $this->db->query('SELECT @@session.time_zone')->fetch();
            if ($tz[0] != $this->config->get('SYSTEM_TIMEZONE')) {
                throw new \Exception('Error setting timezone in Sql Database');
            } else {
                #    print "Mysql timezone set to $tz[0]<br/>";
            }
            $phptz_set = date_default_timezone_set($this->config->get('SYSTEM_TIMEZONE'));
            $phptz = date_default_timezone_get ();
            if (!$phptz_set || $phptz != $this->config->get('SYSTEM_TIMEZONE')) {
                ## I18N doesn't exist yet, @@TODO need better error catching here
                throw new \Exception('Error setting timezone in PHP');
            } else {
                #    print "PHP system timezone set to $phptz<br/>";
            }
            #  print "Time now: ".date('Y-m-d H:i:s').'<br/>';
        }

        if( !DEBUG ) {
            ini_set('error_append_string',
                'phpList version '.PHPLIST_VERSION
            );
            ini_set(
                'error_prepend_string',
                '<p class="error">Sorry a software error occurred:<br/>
                Please <a href="http://mantis.phplist.com">report a bug</a> when reporting the bug, please include URL and the entire content of this page.<br/>'
            );
        }


        //TODO: we probably will need >5.3
        if (version_compare(PHP_VERSION, '5.1.2', '<') && $this->config->get('WARN_ABOUT_PHP_SETTINGS')) {
            throw new \Exception($this->lan->get('phpList requires PHP version 5.1.2 or higher'));
        }

        if ($this->config->get('ALLOW_ATTACHMENTS')
            && $this->config->get('WARN_ABOUT_PHP_SETTINGS')
            && (!is_dir($this->config->get('ATTACHMENT_REPOSITORY'))
                || !is_writable ($this->config->get('ATTACHMENT_REPOSITORY'))
            )
        ) {
            $tmperror = '';
            if(ini_get('open_basedir')) {
                $tmperror = $this->lan->get('open_basedir restrictions are in effect, which may be the cause of the next warning: ');
            }
            $tmperror .= $this->lan->get('The attachment repository does not exist or is not writable');
            throw new \Exception($tmperror);
        }

        $this_doc = getenv('REQUEST_URI');
        if (preg_match('#(.*?)/admin?$#i',$this_doc,$regs)) {
            $check_pageroot = $this->config->get('PAGEROOT');
            $check_pageroot = preg_replace('#/$#','',$check_pageroot);
            if ($check_pageroot != $regs[1] && $this->config->get('WARN_ABOUT_PHP_SETTINGS'))
                throw new \Exception($this->lan->get('The pageroot in your config does not match the current locationCheck your config file.'));
        }
    }

    /**
     * Function to end current round of statistics gathering and write to log if required
     */
    public function endStatistics()
    {
        /*
        print "\n\n".'<!--';
        if (DEBUG) {
            print '<br clear="all" />';
            print $this->db->getQueryCount().' db queries in $elapsed seconds';
            if (function_exists('memory_get_peak_usage')) {
                $memory_usage = 'Peak: ' .memory_get_peak_usage();
            } elseif (function_exists('memory_get_usage')) {
                $memory_usage = memory_get_usage();
            } else {
                $memory_usage = 'Cannot determine with this PHP version';
            }
            print '<br/>Memory usage: '.$memory_usage;
        }*/

        if ($this->config->get('statslog', false) !== false) {
            if ($fp = @fopen($this->config->get('statslog'),'a')) {
                @fwrite(
                    $fp,
                    $this->db->getQueryCount()."\t".
                    Timer::get('pagestats')->elapsed()."\t".
                    $_SERVER['REQUEST_URI']."\t".
                    $this->config->get('installation_name')."\n"
                );
            }
        }
    }
}