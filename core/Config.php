<?php
/**
 * User: SaWey
 * Date: 16/12/13
 */

namespace phpList;


use phpList\helper\DefaultConfig;
use phpList\helper\Validation;

//TODO: lots of configuration not related to core needs to be filtered out
class Config extends UserConfig
{
    /**
     * Constants used for debugging and developping
     */
    const DEBUG = true;
    const DEVELOPER_EMAIL = 'dev@localhost.local';
    const DEV_VERSION = true;

    const VERSION = '4.0.0 dev';


    /**
     * @var Config $_instance
     */
    private static $_instance;
    private $config_ready = false;
    public $running_config = array();

    /**
     * Default constructor
     * load configuration from database each new session
     * TODO: probably not a good idea when using an installation with multiple subscribers
     */
    private function __construct(){}

    /**
     * Get an instance of the configuration object
     * @return Config
     */
    private static function instance()
    {
        if (!Config::$_instance instanceof self) {
            Config::$_instance = new self();

            //do we have a configuration saved in session?
            if (isset($_SESSION['running_config'])) {
                Config::$_instance->running_config = $_SESSION['running_config'];
            } else {
                Config::$_instance->initConfig();
                Config::$_instance->loadAllFromDB();
                Config::$_instance->afterInit();
            }
        }
        return Config::$_instance;
    }

    /**
     * Initialise configuration
     */
    public static function initialise(){
        Config::instance();
        Config::$_instance->config_ready = true;
    }

    /**
     * Load the entire configuration from the database
     * and put it in the session, so we don't need to reload it from db
     */
    private function loadAllFromDB()
    {
        //try to load additional configuration from db
        //$has_db_config = phpList::DB()->tableExists(Config::getTableName('config'), 1);
        Config::setRunningConfig('has_db_config', true);

        $result = phpList::DB()->query(sprintf('SELECT item, value FROM %s', Config::getTableName('config')));
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $this->running_config[$row['item']] = $row['value'];
        }

        $_SESSION['running_config'] = $this->running_config;
    }

    /**
     * Get the table name including prefix
     * @param $table_name
     * @param bool $is_user_table
     * @return string
     */
    public static function getTableName($table_name, $is_user_table = false)
    {
        return ($is_user_table ? Config::USERTABLE_PREFIX : Config::TABLE_PREFIX) . $table_name;
    }

    /**
     * Get a config item from the database
     * @param string $item
     * @param null $default
     * @return null|mixed
     */
    private function fromDB($item, $default = null)
    {
        if(!$this->config_ready) return $default;
        if (Config::get('has_db_config')) {
            $query = sprintf(
                'SELECT value, editable
                FROM %s
                WHERE item = "%s"',
                Config::getTableName('config'),
                $item
            );
            $result = phpList::DB()->query($query);
            if (phpList::DB()->numRows($result) == 0) {
                if ($default == null) {
                    //try to get from default config
                    $dc = DefaultConfig::get($item);
                    if ($dc != false) {
                        //TODO: Old version would save default cfg to db, is this needed?
                        # save the default value to the database, so we can obtain
                        # the information when running from commandline
                        //if (Sql_Table_Exists($tables["config"]))
                        //    saveConfig($item, $value);
                        #    print "$item => $value<br/>";
                        return $dc['value'];
                    }
                } else {
                    return $default;
                }
            } else {
                $row = phpList::DB()->fetchRow($result);
                return $row[0];
            }
        }
        return $default;
    }

    /**
     * Set an item in the running configuration
     * These values are only available in the current session
     * @param string $item
     * @param mixed $value
     */
    public static function setRunningConfig($item, $value)
    {
        Config::instance()->running_config[$item] = $value;
        $_SESSION['running_config'][$item] = $value;
    }

    /**
     * Write a configuration value to the database
     * @param string $item
     * @param mixed $value
     * @param int $editable
     * @return bool|string
     */
    public static function setDBConfig($item, $value, $editable = 1)
    {
        ## in case DB hasn't been initialised
        if (!Config::get('has_db_config')) {
            return false;
        }

        $configInfo = DefaultConfig::get($item);
        if (!$configInfo) {
            $configInfo = array(
                'type' => 'unknown',
                'allowempty' => true,
                'value' => '',
            );
        }
        ## to validate we need the actual values
        $value = str_ireplace('[domain]', Config::get('domain'), $value);
        $value = str_ireplace('[website]', Config::get('website'), $value);

        switch ($configInfo['type']) {
            case 'boolean':
                if ($value == "false" || $value == "no") {
                    $value = 0;
                } elseif ($value == "true" || $value == "yes") {
                    $value = 1;
                }
                break;
            case 'integer':
                $value = sprintf('%d', $value);
                if ($value < $configInfo['min']) $value = $configInfo['min'];
                if ($value > $configInfo['max']) $value = $configInfo['max'];
                break;
            case 'email':
                if (!Validation::isEmail($value)) {
                    return $configInfo['description'] . ': ' . s('Invalid value for email address');
                }
                break;
            case 'emaillist':
                $valid = array();
                $emails = explode(',', $value);
                foreach ($emails as $email) {
                    if (Validation::isEmail($email)) {
                        $valid[] = $email;
                    } else {
                        return $configInfo['description'] . ': ' . s('Invalid value for email address');
                    }
                }
                $value = join(',', $valid);
                break;
        }
        ## reset to default if not set, and required
        if (empty($configInfo['allowempty']) && empty($value)) {
            $value = $configInfo['value'];
        }
        if (!empty($configInfo['hidden'])) {
            $editable = false;
        }

        phpList::DB()->replaceQuery(
            Config::getTableName('config'),
            array('item' => $item, 'value' => $value, 'editable' => $editable),
            'item'
        );

        //add to running config
        Config::setRunningConfig($item, $value);

        return true;
    }

    /**
     * Get an item from the config, provide a default value if needed
     * @param string $item
     * @param mixed $default
     * @return mixed|null|string
     */
    public static function get($item, $default = null)
    {
        $config = Config::instance();
        if($config->config_ready){
            if (isset($config->running_config[$item])) {
                $value = $config->running_config[$item];
            } else {
                //try to find it in db
                //$value = $cofig->fromDB($item, $default);
                $dc = DefaultConfig::get($item);
                if ($dc !== false) {
                    //TODO: Old version would save default cfg to db, is this needed?
                    # save the default value to the database, so we can obtain
                    # the information when running from commandline
                    //if (Sql_Table_Exists($tables["config"]))
                    //    saveConfig($item, $value);
                    #    print "$item => $value<br/>";
                    $value = $dc['value'];
                }else{
                    $value = $default;
                }
            }

            if(is_string($value)){
                //TODO: should probably move this somewhere else
                $find = array('[WEBSITE]', '[DOMAIN]', '<?=VERSION?>');
                $replace = array(
                    $config->running_config['website'],
                    $config->running_config['domain'],
                    Config::VERSION
                );
                $value = str_replace($find, $replace, $value);
            }
        }else{
            if (isset($config->running_config[$item])) {
                $value = $config->running_config[$item];
            }else{
                $value = $default;
            }
        }

        return $value;
    }

    /**
     * Get config item and replace with subscriber unique id where needed
     * @param string $item
     * @param int $subscriber_id
     * @return mixed|null|string
     */
    public static function getUserConfig($item, $subscriber_id = 0)
    {
        $value = Config::get($item, false);

        # if this is a subpage item, and no value was found get the global one
        if (!$value && strpos($item, ":") !== false) {
            list ($a, $b) = explode(":", $item);
            $value = Config::getUserConfig($a, $subscriber_id);
        }
        if ($subscriber_id != 0) {
            $uniq_id = Subscriber::getUniqueId($subscriber_id);
            # parse for placeholders
            # do some backwards compatibility:
            # hmm, reverted back to old system

            $url = Config::get('unsubscribeurl');
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $value = str_ireplace('[UNSUBSCRIBEURL]', $url . $sep . 'uid=' . $uniq_id, $value);
            $url = Config::get('confirmationurl');
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $value = str_ireplace('[CONFIRMATIONURL]', $url . $sep . 'uid=' . $uniq_id, $value);
            $url = Config::get('preferencesurl');
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $value = str_ireplace('[PREFERENCESURL]', $url . $sep . 'uid=' . $uniq_id, $value);
        }
        $value = str_ireplace('[SUBSCRIBEURL]', Config::get('subscribeurl'), $value);
        if ($value == '0') {
            $value = 'false';
        } elseif ($value == '1') {
            $value = 'true';
        }
        return $value;
    }

    /**
     * Wrapper for <u>DefaultConfig::get()</u>
     * @param string $item
     * @return mixed
     * @see DefaultConfig::get()
     */
    public static function defaultConfig($item)
    {
        return DefaultConfig::get($item);
    }


    /**
     * Try to initialize some configuration values
     * @throws \Exception
     */
    private static function initConfig()
    {
        if (function_exists('iconv_set_encoding')) {
            iconv_set_encoding('input_encoding', 'UTF-8');
            iconv_set_encoding('internal_encoding', 'UTF-8');
            iconv_set_encoding('output_encoding', 'UTF-8');
        }

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        $zlib_compression = ini_get('zlib.output_compression');
        # hmm older versions of PHP don't have this, but then again, upgrade php instead?
        $handlers = ob_list_handlers();
        $gzhandler = 0;
        foreach ($handlers as $handler) {
            $gzhandler = $gzhandler || $handler == 'ob_gzhandler';
        }
        # @@@ needs more work
        Config::setRunningConfig('compression_used', ($zlib_compression || $gzhandler));

        ## @@ would be nice to move this to the config file at some point
        # http://mantis.phplist.com/view.php?id=15521
        ## set it on the fly, although that will probably only work with Apache
        ## we need to save this in the DB, so that it'll work on commandline
        $public_scheme = (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on')) ? 'https' : 'http';
        Config::setRunningConfig('scheme', $public_scheme);

        if (Config::USE_CUSTOM_PUBLIC_PROTOCOL) {
            Config::setRunningConfig('public_scheme', Config::PUBLIC_PROTOCOL);
        } else {
            Config::setRunningConfig('public_scheme', $public_scheme);
        }

        # set some defaults if they are not specified
        Config::setRunningConfig('DEVSITE', false);
        Config::setRunningConfig('TRANSLATIONS_XML', 'http://translate.phplist.com/translations.xml');

        //define('TLD_AUTH_LIST','http://data.iana.org/TLD/tlds-alpha-by-domain.txt');
        //define('TLD_AUTH_MD5','http://data.iana.org/TLD/tlds-alpha-by-domain.txt.md5');
        Config::setRunningConfig('TLD_AUTH_LIST','http://www.phplist.com/files/tlds-alpha-by-domain.txt');
        Config::setRunningConfig('TLD_AUTH_MD5','http://www.phplist.com/files/tlds-alpha-by-domain.txt.md5');
        Config::setRunningConfig('TLD_REFETCH_TIMEOUT',15552000); ## 180 days, about 6 months
        Config::setRunningConfig('USEFCK', true);
        Config::setRunningConfig('USECK', false); ## ckeditor integration, not finished yet
        Config::setRunningConfig('SHOW_UNSUBSCRIBELINK',true);

        if (function_exists('hash_algos') && !in_array(Config::ENCRYPTION_ALGO, hash_algos())) {
            throw new \Exception('Encryption algorithm "' . Config::ENCRYPTION_ALGO . '" not supported, change your configuration');
        }
        ## remember the length of a hashed string
        Config::setRunningConfig('hash_length', strlen(hash(Config::ENCRYPTION_ALGO,'some text')));


        Config::setRunningConfig('NUMATTACHMENTS',1);
        Config::setRunningConfig('USE_EDITMESSAGE',0);
        Config::setRunningConfig('FCKIMAGES_DIR','uploadimages');
        Config::setRunningConfig('NAME','phpList');
        Config::setRunningConfig('USE_OUTLOOK_OPTIMIZED_HTML',0);
        Config::setRunningConfig('USE_PREPARE',0);
        Config::setRunningConfig('HTMLEMAIL_ENCODING','quoted-printable');
        Config::setRunningConfig('TEXTEMAIL_ENCODING','7bit');
        Config::setRunningConfig('WARN_SAVECHANGES',1);
        Config::setRunningConfig('USETINYMCEMESG',0);
        Config::setRunningConfig('USETINYMCETEMPL',0);
        Config::setRunningConfig('TINYMCEPATH','');
        Config::setRunningConfig('STATS_INTERVAL','weekly');
        Config::setRunningConfig('ALLOW_IMPORT',1);
        Config::setRunningConfig('CLICKTRACK_LINKMAP',0);
        Config::setRunningConfig('MERGE_DUPLICATES_DELETE_DUPLICATE',1);
        Config::setRunningConfig('USE_PERSONALISED_REMOTEURLS',1);
        Config::setRunningConfig('USE_LOCAL_SPOOL',0);
        Config::setRunningConfig('SEND_LISTADMIN_COPY',true);
        Config::setRunningConfig('BLACKLIST_EMAIL_ON_BOUNCE',5);
        Config::setRunningConfig('UNBLACKLIST_IN_PROFILE',false);
        Config::setRunningConfig('ENCRYPT_ADMIN_PASSWORDS',1);
        Config::setRunningConfig('PASSWORD_CHANGE_TIMEFRAME','1 day');
        Config::setRunningConfig('MAX_SENDPROCESSES',1);
        Config::setRunningConfig('SENDPROCESS_SERVERNAME','localhost');
        Config::setRunningConfig('DB_TRANSLATION',0);
        Config::setRunningConfig('ALLOW_DELETEBOUNCE',1);
        Config::setRunningConfig('MESSAGE_SENDSTATUS_INACTIVETHRESHOLD',120);
        Config::setRunningConfig('MESSAGE_SENDSTATUS_SAMPLETIME',600);
        Config::setRunningConfig('SEND_QUEUE_PROCESSING_REPORT',true);
        Config::setRunningConfig('MAX_AVATAR_SIZE',2000);
        Config::setRunningConfig('ADD_EMAIL_THROTTLE',1); ## seconds between addemail ajax requests
        Config::setRunningConfig('SENDTEST_THROTTLE',1); ## seconds between send test
        Config::setRunningConfig('SENDTEST_MAX',999); ## max number of emails in a send test
        Config::setRunningConfig('installation_name', 'phpList');

        ## this doesn't yet work with the FCKEditor
        #ini_set('session.name',str_replace(' ','',SESSIONNAME));

        if (Config::USE_AMAZONSES){
            if(Config::AWS_ACCESSKEYID  == ''){
                throw new \Exception('Invalid Amazon SES configuration: AWS_ACCESSKEYID not set');
            }else if(!function_exists('curl_init')){
                throw new \Exception('Invalid Amazon SES configuration: CURL not enabled');
            }
        }

        if(isset($_SERVER['HTTP_HOST'])){
            Config::setRunningConfig('ACCESS_CONTROL_ALLOW_ORIGIN','http://'.$_SERVER['HTTP_HOST']);
        }

        Config::setRunningConfig('RFC_DIRECT_DELIVERY',false);  ## Request for Confirmation, delivery with SMTP
        # check whether Pear HTTP/Request is available, and which version
        # try 2 first

        # @@TODO finish this, as it is more involved than just renaming the class
        #@include_once 'HTTP/Request2.php';
        if (0 && class_exists('HTTP_Request2')) {
            Config::setRunningConfig('has_pear_http_request', 2);
        } else {
            @include_once 'HTTP/Request.php';
            Config::setRunningConfig('has_pear_http_request', class_exists('HTTP_Request'));
        }
        Config::setRunningConfig('has_curl', function_exists('curl_init'));
        Config::setRunningConfig('can_fetch_url', class_exists('HTTP_Request') || function_exists('curl_init'));
        Config::setRunningConfig('jQuery', 'jquery-1.7.1.min.js');

        $system_tmpdir = ini_get('upload_tmp_dir');
        if (Config::TMPDIR && !empty($system_tmpdir)) {
            Config::setRunningConfig('tmpdir', $system_tmpdir);
        }else if (Config::TMPDIR) {
            Config::setRunningConfig('tmpdir', '/tmp');
        }
        if (!is_dir(Config::TMPDIR) || !is_writable(Config::TMPDIR) && !empty($system_tmpdir)) {
            Config::setRunningConfig('tmpdir', $system_tmpdir);
        }

        ## as the 'admin' in adminpages is hardcoded, don't put it in the config file
        ## remove possibly duplicated // at the beginning
        Config::setRunningConfig(
            'adminpages',
            preg_replace('~^//~', '/', Config::PAGEROOT.'/admin')
        );

        Config::setRunningConfig('systemroot', dirname(__FILE__));

        ## when click track links are detected, block sending
        ## if false, will only show warning. For now defaulting to false, but may change that later
        Config::setRunningConfig('BLOCK_PASTED_CLICKTRACKLINKS',false);

        if (Config::FORWARD_EMAIL_COUNT < 1) {
            throw new \Exception('Config Error: FORWARD_EMAIL_COUNT must be > (int) 0');
        }

        # allows FORWARD_EMAIL_COUNT forwards per subscriber per period in mysql interval terms default one day
        Config::setRunningConfig('FORWARD_EMAIL_PERIOD', '1 day');
        Config::setRunningConfig('EMBEDUPLOADIMAGES',0);
        Config::setRunningConfig('IMPORT_FILESIZE',5);
        Config::setRunningConfig('SMTP_TIMEOUT',5);

        Config::setRunningConfig('noteditableconfig', array());

        ## experimental, use minified JS and CSS
        Config::setRunningConfig('USE_MINIFIED_ASSETS',false);

        ## global counters array to keep track of things
        Config::setRunningConfig(
            'counters',
            array(
                'campaign' => 0,
                'num_subscribers_for_message' => 0,
                'batch_count' => 0,
                'batch_total' => 0,
                'sendemail returned false' => 0,
                'send blocked by domain throttle' => 0
            ));

        Config::setRunningConfig('disallowpages', array());
        # list of pages and categorisation in the system
        ## old version
        Config::setRunningConfig(
            'system_pages',
            array (
                'system' => array (
                    'adminattributes' => 'none',
                    'attributes' => 'none',
                    'upgrade' => 'none',
                    'configure' => 'none',
                    'spage' => 'owner',
                    'spageedit' => 'owner',
                    'defaultconfig' => 'none',
                    'defaults' => 'none',
                    'initialise' => 'none',
                    'bounces' => 'none',
                    'bounce' => 'none',
                    'processbounces' => 'none',
                    'eventlog' => 'none',
                    'reconcilesubscribers' => 'none',
                    'getrss' => 'owner',
                    'viewrss' => 'owner',
                    'purgerss' => 'none',
                    'setup' => 'none',
                    'dbcheck' => 'none',

                ),
                'list' => array (
                    'list' => 'owner',
                    'editlist' => 'owner',
                    'members' => 'owner'
                ),
                'subscriber' => array (
                    'subscriber' => 'none',
                    'subscribers' => 'none',
                    'dlsubscribers' => 'none',
                    'editattributes' => 'none',
                    'subscribercheck' => 'none',
                    'import1' => 'none',
                    'import2' => 'none',
                    'import3' => 'none',
                    'import4' => 'none',
                    'import' => 'none',
                    'export' => 'none',
                    'massunconfirm' => 'none',

                ),
                'message' => array (
                    'message' => 'owner',
                    'messages' => 'owner',
                    'processqueue' => 'none',
                    'send' => 'owner',
                    'preparesend' => 'none',
                    'sendprepared' => 'all',
                    'template' => 'none',
                    'templates' => 'none'
                ),
                'clickstats' => array (
                    'statsmgt' => 'owner',
                    'mclicks' => 'owner',
                    'uclicks' => 'owner',
                    'subscriberclicks' => 'owner',
                    'mviews' => 'owner',
                    'statsoverview' => 'owner',

                ),
                'admin' => array (
                    'admins' => 'none',
                    'admin' => 'owner'
                )
            ));

        # Set revision
        Config::setRunningConfig('CODEREVISION', '$Rev$');
        if (preg_match('/Rev: (\d+)/','$Rev$',$match)) {
            Config::setRunningConfig('REVISION',$match[1]);
        }

        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'cmd_line';

        Config::setRunningConfig('organisation_name', $server_name);
        Config::setRunningConfig('domain', $server_name);
        Config::setRunningConfig('website', $server_name);

        $xormask = md5(uniqid(rand(), true));
        Config::setRunningConfig('xormask',$xormask);
        Config::setRunningConfig('XORmask',$xormask);

        # if keys need expanding with 0-s
        Config::setRunningConfig('checkboxgroup_storesize', 1); # this will allow 10000 options for checkboxes

        # identify pages that can be run on commandline
        Config::setRunningConfig(
            'commandline_pages',
            array(
                'dbcheck','send','processqueueforked','processqueue',
                'processbounces','import','upgrade','convertstats','reindex',
                'blacklistemail','systemstats','converttoutf8','initlanguages'
            ));

        Config::setRunningConfig('envelope', '-f' . Config::MESSAGE_ENVELOPE);
        Config::setRunningConfig('coderoot', dirname(__FILE__).'/');

        /*
          We request you retain the $PoweredBy variable including the links.
          This not only gives respect to the large amount of time given freely
          by the developers  but also helps build interest, traffic and use of
          PHPlist, which is beneficial to it's future development.

          You can configure your PoweredBy options in your config file

          Michiel Dethmers, phpList Ltd 2001-2013
        */
        $v = Config::DEV_VERSION ? 'dev' : Config::VERSION;

        if (Config::REGISTER) {
            $PoweredByImage = '<p class="poweredby"><a href="http://www.phplist.com/poweredby?utm_source=pl'.$v.'&amp;utm_medium=poweredhostedimg&amp;utm_campaign=phpList" title="visit the phpList website" ><img src="http://powered.phplist.com/images/'.$v.'/power-phplist.png" width="70" height="30" title="powered by phpList version '.$v.', &copy; phpList ltd" alt="powered by phpList '.$v.', &copy; phpList ltd" border="0" /></a></p>';
        } else {
            $PoweredByImage = '<p class="poweredby"><a href="http://www.phplist.com/poweredby?utm_source=pl'.$v.'&amp;utm_medium=poweredlocalimg&amp;utm_campaign=phpList" title="visit the phpList website"><img src="images/power-phplist.png" width="70" height="30" title="powered by phpList version '.$v.', &copy; phpList ltd" alt="powered by phpList '.$v.', &copy; phpList ltd" border="0"/></a></p>';
        }
        $PoweredByText = '<div style="clear: both; font-family: arial, verdana, sans-serif; font-size: 8px; font-variant: small-caps; font-weight: normal; padding: 2px; padding-left:10px;padding-top:20px;">powered by <a href="http://www.phplist.com/poweredby?utm_source=download'.$v.'&amp;utm_medium=poweredtxt&amp;utm_campaign=phpList" target="_blank" title="powered by phpList version '.$v.', &copy; phpList ltd">phpList</a></div>';
        Config::setRunningConfig('PoweredBy', Config::PAGETEXTCREDITS ? $PoweredByText : $PoweredByImage);


        # some other configuration variables, which need less tweaking
        # number of subscribers to show per page if there are more
        Config::setRunningConfig('MAX_USER_PP',50);
        Config::setRunningConfig('MAX_MSG_PP',5);

        Config::setRunningConfig('homepage', 'home');
    }

    /**
     * Some more initialisation that can only be done after basic init is done
     */
    private function afterInit(){
        //TODO: move this somewhere else
        if (Config::DEBUG
            && @($_SERVER['HTTP_HOST'] != 'dev.phplist.com')
        ) {
            error_reporting(E_ALL);
            ini_set('display_errors',1);
            foreach ($_REQUEST as $key => $val) {
                unset($$key);
            }
        } else {
            error_reporting(0);
        }
        if (Config::get('ui', false) === false || !is_dir(dirname(__FILE__).'/ui/'.Config::get('ui', false))) {
            ## prefer dressprow over orange
            if (is_dir(dirname(__FILE__).'/ui/dressprow')) {
                Config::setRunningConfig('ui', 'dressprow');
            } else {
                Config::setRunningConfig('ui', 'default');
            }
        }

        Config::setRunningConfig(
            'SESSIONNAME',
            Config::get('SESSIONNAME', 'phpList'.Config::get('installation_name').'session'
            ));

        ## experimental, mark mails 'todo' in the DB and process the 'todo' list, to avoid the subscriber query being run every queue run
        if (Config::MESSAGEQUEUE_PREPARE) {
            ## with a multi-process config, we need the queue prepare mechanism and memcache
            if (Config::get('MAX_SENDPROCESSES', 1) > 1) {
                Config::setRunningConfig('MESSAGEQUEUE_PREPARE',true);
            } else {
                Config::setRunningConfig('MESSAGEQUEUE_PREPARE',false);
            }
        }

        ## set up a memcached global object, and test it
        if (Config::get('MEMCACHED', false) !== false) {
            include_once dirname(__FILE__).'/class.memcached.php';
            if (class_exists('phpListMC')) {
                $MC = new phpListMC();
                list($mc_server,$mc_port) = explode(':',Config::get('MEMCACHED'));
                $MC->addServer($mc_server,$mc_port);

                /* check that the MC connection is ok
                $MC->add('Hello','World');
                $test = $MC->get('Hello');
                if ($test != 'World') {
                  unset($MC);
                }
                */
                Config::setRunningConfig('MC', $MC);
            }
        }

        if (Config::MESSAGE_ENVELOPE == '') {
            # why not try set it to "person in charge of this system". Will help get rid of a lot of bounces to nobody@server :-)
            Config::setRunningConfig('message_envelope', Config::get('admin_address'));
        }

        /*
        if (defined("IN_WEBBLER") && is_object($GLOBALS["config"]["plugins"]["phplist"])) {
            $GLOBALS["tables"] = $GLOBALS["config"]["plugins"]["phplist"]->tables;
        }
        */
        Config::setRunningConfig(
            'bounceruleactions',
            array(
                'deletesubscriber' => s('delete subscriber'),
                'unconfirmsubscriber' => s('unconfirm subscriber'),
                'blacklistsubscriber' => s('blacklist subscriber'),
                'deletesubscriberandbounce' => s('delete subscriber and bounce'),
                'unconfirmsubscriberanddeletebounce' => s('unconfirm subscriber and delete bounce'),
                'blacklistsubscriberanddeletebounce' => s('blacklist subscriber and delete bounce'),
                'deletebounce' => s('delete bounce'),
            ));
    }

}