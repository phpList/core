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
    protected $util;
    protected $db;

    /**
     * Default constructor
     * @param Config $config
     * @param Database $db
     * @param Language $lan
     * @param Util $util
     * @throws \Exception
     */
    public function __construct(Config $config, Database $db, Language $lan, Util $util)
    {
        $this->config = $config;
        $this->db = $db;
        $this->lan = $lan;
        $this->util = $util;

        $this->startStats();

        $this->configMailer();
        $this->configDebug();
        $this->configPhpInternals();
        $this->configCli();
        $this->configTimezone();
        $this->configPhpVer();
        $this->configAttachments();
        $this->configPageroot();

        $this->endStats();
    }

    protected function startStats()
    {
        Timer::start('pagestats');
    }

    /**
      * Function to end current round of statistics gathering and write to log if required
      */
    protected function endStats()
    {
        if ($this->config->get('statslog', false) !== false) {
            if ($fp = @fopen($this->config->get('statslog'), 'a')) {
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

    protected function configDebug()
    {
        if (!DEBUG) {
            error_reporting(0);
        }

        if (!DEBUG) {
            ini_set(
                'error_append_string',
                'phpList version '.PHPLIST_VERSION
            );
            ini_set(
                'error_prepend_string',
                '<p class="error">Sorry a software error occurred:<br/>
            Please <a href="http://mantis.phplist.com">report a bug</a> when reporting the bug, please include URL and the entire content of this page.<br/>'
            );
        }
    }

    protected function configCli()
    {
        # setup commandline
        if (php_sapi_name() == 'cli' && !strstr($GLOBALS['argv'][0], 'phpunit')) {
            for ($i=0; $i<$_SERVER['argc']; $i++) {
                $my_args = array();
                if (preg_match('/(.*)=(.*)/', $_SERVER['argv'][$i], $my_args)) {
                    $_GET[$my_args[1]] = $my_args[2];
                    $_REQUEST[$my_args[1]] = $my_args[2];
                }
            }
            $this->config->setRunningConfig('commandline', true);
            $cline = $this->util->parseCLine();
            $dir = dirname($_SERVER['SCRIPT_FILENAME']);
            chdir($dir);

            if (!isset($cline['c']) || !is_file($cline['c'])) {
                throw new \Exception('Cannot find config file');
            }
        } else {
            $this->config->setRunningConfig('commandline', false);
        }
        $this->config->setRunningConfig('ajax', isset($_GET['ajaxed']));

        //TODO: maybe move everything command line to separate class
        $cline = null;

        //check for commandline and cli version
        if (!isset($_SERVER['SERVER_NAME']) && PHP_SAPI != 'cli') {
            throw new \Exception('Warning: commandline only works well with the cli version of PHP');
        }
        if (isset($_REQUEST['_SERVER'])) {
            return;
        }
    }

    protected function configMailer()
    {
        //Using phpmailer?
        if ($this->config->get('PHPMAILER_PATH')
            && is_file($this->config->get('PHPMAILER_PATH'))
        ) {
            #require_once '/usr/share/php/libphp-phpmailer/class.phpmailer.php';
            require_once $this->config->get('PHPMAILER_PATH');
        }
    }

    protected function configTimezone()
    {
        date_default_timezone_set($this->config->get('SYSTEM_TIMEZONE'));

        ##todo: this needs more testing, and docs on how to set the Timezones in the DB
        if ($this->config->get('USE_CUSTOM_TIMEZONE')) {
            $this->db->query('SET time_zone = "'.$this->config->get('SYSTEM_TIMEZONE').'"');
            ## verify that it applied correctly
            $tz = $this->db->query('SELECT @@session.time_zone')->fetch();
            if ($tz[0] != $this->config->get('SYSTEM_TIMEZONE')) {
                throw new \Exception('Error setting timezone in Sql Database');
            }
            $phptz_set = date_default_timezone_set($this->config->get('SYSTEM_TIMEZONE'));
            $phptz = date_default_timezone_get();
            if (!$phptz_set || $phptz != $this->config->get('SYSTEM_TIMEZONE')) {
                ## I18N doesn't exist yet, @@TODO need better error catching here
                throw new \Exception('Error setting timezone in PHP');
            }
        }
    }

    protected function configPageroot()
    {
        $this_doc = getenv('REQUEST_URI');
        if (preg_match('#(.*?)/admin?$#i', $this_doc, $regs)) {
            $check_pageroot = $this->config->get('PAGEROOT');
            $check_pageroot = preg_replace('#/$#', '', $check_pageroot);
            if ($check_pageroot != $regs[1] && $this->config->get('WARN_ABOUT_PHP_SETTINGS')) {
                throw new \Exception($this->lan->get('The pageroot in your config does not match the current locationCheck your config file.'));
            }
        }
    }

    protected function configPhpVer()
    {
        if (version_compare(PHP_VERSION, '7.0.0', '<') && $this->config->get('WARN_ABOUT_PHP_SETTINGS')) {
            throw new \Exception($this->lan->get('phpList requires PHP version 7.0.0 or higher'));
        }
    }

    protected function configAttachments()
    {
        if ($this->config->get('ALLOW_ATTACHMENTS')
        && $this->config->get('WARN_ABOUT_PHP_SETTINGS')
        && (!is_dir($this->config->get('ATTACHMENT_REPOSITORY'))
        || !is_writable($this->config->get('ATTACHMENT_REPOSITORY'))
        )
        ) {
            $tmperror = '';
            if (ini_get('open_basedir')) {
                $tmperror = $this->lan->get('open_basedir restrictions are in effect, which may be the cause of the next warning: ');
            }
            $tmperror .= $this->lan->get('The attachment repository does not exist or is not writable');
            throw new \Exception($tmperror);
        }
    }

    protected function configPhpInternals()
    {
        $this->util->unregister_GLOBALS();
        $this->util->magicQuotes();
    }
}
