<?php
namespace phpList;

use phpList\helper\Database;
use phpList\helper\Language;
use phpList\helper\Validation;

class Config
{
    public $configFileOrigin;
    private $running_config = [];
    private $default_config = [];

    public function parseIniFile($configFile)
    {
        // Load the config file
        $parsed = parse_ini_file($configFile);
        if (! is_array($parsed)) {
            throw new \Exception('Could not parse specified ini config file: ' . $configFile);
        }
        return $parsed;
    }

    /**
     * Default constructor
     * load configuration from database each new session
     * TODO: probably not a good idea when using an installation with multiple subscribers
     */
    public function __construct($configFile = null)
    {
        /*
         * Constants used for debugging and developping
         */
        defined('DEBUG') ? null : define('DEBUG', true);
        defined('PHPLIST_DEVELOPER_EMAIL') ? null : define('PHPLIST_DEVELOPER_EMAIL', 'dev@localhost.local');
        defined('PHPLIST_DEV_VERSION') ? null : define('PHPLIST_DEV_VERSION', true);
        defined('PHPLIST_VERSION') ? null : define('PHPLIST_VERSION', '4.0.0 dev');

        // Find the config file to use
        $foundConfigFile = $this->findConfigFile($configFile);
        // Check config file is valid
        $this->validateConfigFile($foundConfigFile);
        // Load the config file path as an ini file
        $this->running_config = $this->parseIniFile($this->configFilePath);
        //Initialise further config
        $this->initConfig();
    }

    /**
     * Find the right config file to use
     */
    public function findConfigFile($configFile)
    {
        // If no config file path provided
        if ($configFile !== null) {
            $this->configFileOrigin = 'supplied file path';
            $this->configFilePath = $configFile;
        } else { // If no config file specified, look for one
            // determine which config file to use
            if (isset($_SESSION['running_config'])
              && count($_SESSION['running_config']) > 15
            ) { // do we have a configuration saved in session?
                // If phpList is being used as a library, the config file may be set in session
                $this->configFileOrigin = 'session';
                $this->configFilePath = $_SESSION['running_config'];
            } elseif (isset($GLOBALS['configfile'])) { // Is a phpList 3 config file stored in globals?

                $this->configFileOrigin = 'globals: phpList3 ini file path';
                $this->configFilePath = $GLOBALS['configfile'];
            } elseif (isset($GLOBALS['phplist4-ini-config-file-path'])) { // Is a phpList 4 config file stored in globals?
                $this->configFileOrigin = 'globals: phpList4 ini file path';
                $this->configFilePath = $GLOBALS['phplist4-ini-config-file-path'];
            } else {
                throw new \Exception('Could not find config file, none specified');
            }
        }
        return $this->configFilePath;
    }

    /**
     * Check that config file is valid
     *
     * @param string $configFilePath Path to check
     */
    public function validateConfigFile($configFilePath)
    {
        if (! is_string($configFilePath)) {
            throw new \Exception('Config file path is not a string (' . gettype($configFilePath) . ')');
        } elseif (! is_file($configFilePath)) {
            throw new \Exception('Config file is not a file: ' . $configFilePath);
        } elseif (! filesize($configFilePath) > 20) {
            throw new \Exception('Config file too small: ' . $configFilePath);
        } elseif (! parse_ini_file($configFilePath)) {
            throw new \Exception('Config file not an INI file: ' . $configFilePath);
        } else {
            return true;
        }
    }

    /**
     * Run this after db has been initialized, so we get the config from inside the database as well.
     *
     * @param Database $db
     */
    public function runAfterDBInitialised(Database $db)
    {
        $this->loadDBConfig($db);
    }

    /**
     * Run this after language has been initialized.
     *
     * @param Language $lan
     */
    public function runAfterLanguageInitialised(Language $lan)
    {
    }

    /**
     * Get the table name including prefix
     *
     * @param $table_name
     * @param bool $is_user_table
     *
     * @return string
     * @FIXME: Why is user_table a special case? Find a nicer way to handle this
     */
    public function getTableName($table_name, $is_user_table = false)
    {
        return
            ($is_user_table ? $this->running_config['USERTABLE_PREFIX'] : $this->running_config['TABLE_PREFIX'])
            . $table_name;
    }

    /**
     * Set an item in the running configuration
     * These values are only available in the current session
     *
     * @param string $item
     * @param mixed $value
     */
    public function setRunningConfig($item, $value)
    {
        $this->running_config[$item] = $value;
        $_SESSION['running_config'][$item] = $value;
    }

    /**
     * Load the entire configuration from the database
     * and put it in the session, so we don't need to reload it from db
     */
    private function loadDBConfig(Database $db)
    {
        //try to load additional configuration from db
        $this->running_config['has_db_config'] =  true;

        $result = $db->query(sprintf('SELECT item, value FROM %s', $this->getTableName('config')));
        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $this->running_config[$row['item']] = $row['value'];
        }

        $_SESSION['running_config'] = $this->running_config;
    }

    /**
     * Write a configuration value to the database
     *
     * @param helper\Database $db
     * @param string $item
     * @param mixed $value
     * @param int $editable
     *
     * @return bool|string
     */
    public function setDBConfig(Database $db, $item, $value, $editable = 1)
    {
        ## in case DB hasn't been initialised
        if (!$this->get('has_db_config')) {
            return false;
        }

        if (!isset($this->default_config[$item])) {
            $configInfo = [
                'type' => 'unknown',
                'allowempty' => true,
                'value' => '',
            ];
        } else {
            $configInfo = $this->default_config[$item];
        }
        ## to validate we need the actual values
        $value = str_ireplace('[domain]', $this->get('domain'), $value);
        $value = str_ireplace('[website]', $this->get('website'), $value);

        switch ($configInfo['type']) {
            case 'boolean':
                if ($value == 'false' || $value == 'no') {
                    $value = 0;
                } elseif ($value == 'true' || $value == 'yes') {
                    $value = 1;
                }
                break;
            case 'integer':
                $value = sprintf('%d', $value);
                if ($value < $configInfo['min']) {
                    $value = $configInfo['min'];
                }
                if ($value > $configInfo['max']) {
                    $value = $configInfo['max'];
                }
                break;
            case 'email':
                if (!Validation::validateEmail($value, $this->get('EMAIL_ADDRESS_VALIDATION_LEVEL'), $this->get('internet_tlds'))) {
                    return $configInfo['description'] . ': ' . 'Invalid value for email address';
                }
                break;
            case 'emaillist':
                $valid = [];
                $emails = explode(',', $value);
                foreach ($emails as $email) {
                    if (Validation::validateEmail($email, $this->get('EMAIL_ADDRESS_VALIDATION_LEVEL'), $this->get('internet_tlds'))) {
                        $valid[] = $email;
                    } else {
                        return $configInfo['description'] . ': ' . 'Invalid value for email address';
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

        $db->replaceQuery(
            $this->getTableName('config'),
            ['item' => $item, 'value' => $value, 'editable' => $editable],
            'item'
        );

        //add to running config
        $this->setRunningConfig($item, $value);

        return true;
    }

    /**
     * Override a configuration setting with a new one
     */
    public function set($item, $value)
    {
        // If a custom config has been loaded
        if (isset($this->running_config[ $item ])) {
            $this->running_config[ $item ] = $value;
            // If the default config has been loaded
        } elseif (isset($this->default_config[ $item ])) {
            $this->default_config[ $item ] = $value;
        }
    }

    /**
     * Get an item from the config, provide a default value if needed
     *
     * @param string $item
     * @param mixed $default
     *
     * @return mixed|null|string
     */
    public function get($item, $default = null)
    {
        // If a custom config has been loaded
        if (isset($this->running_config[ $item ])) {
            $value = $this->running_config[ $item ];
        // If the default config has been loaded
        } elseif (isset($this->default_config[ $item ])) {
            $value = $this->default_config[ $item ];
        // If no configuration has been loaded yet
        } else {
            $value = $default;
        }

        if (is_string($value)) {
            //TODO: should probably move this somewhere else
            $find = [ '[WEBSITE]', '[DOMAIN]', '<?=VERSION?>' ];
            $replace = [
                $this->running_config['website'],
                $this->running_config['domain'],
                PHPLIST_VERSION,
            ];
            $value = str_replace($find, $replace, $value);
        }

        return $value;
    }

    /**
     * Get config item and replace with subscriber unique id where needed
     *
     * @param string $item
     * @param int $subscriber_id
     *
     * @return mixed|null|string
     */
    public function getUserConfig($item, $subscriber_id = 0)
    {
        $value = $this->get($item, false);

        # if this is a subpage item, and no value was found get the global one
        if (!$value && strpos($item, ':') !== false) {
            list($a, $b) = explode(':', $item);
            $value = $this->getUserConfig($a, $subscriber_id);
        }
        if ($subscriber_id != 0) {
            $uniq_id = Subscriber::getUniqueId($subscriber_id);
            # parse for placeholders
            # do some backwards compatibility:
            # hmm, reverted back to old system

            $url = $this->get('unsubscribeurl');
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $value = str_ireplace('[UNSUBSCRIBEURL]', $url . $sep . 'uid=' . $uniq_id, $value);
            $url = $this->get('confirmationurl');
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $value = str_ireplace('[CONFIRMATIONURL]', $url . $sep . 'uid=' . $uniq_id, $value);
            $url = $this->get('preferencesurl');
            $sep = strpos($url, '?') !== false ? '&' : '?';
            $value = str_ireplace('[PREFERENCESURL]', $url . $sep . 'uid=' . $uniq_id, $value);
        }
        $value = str_ireplace('[SUBSCRIBEURL]', $this->get('subscribeurl'), $value);
        if ($value == '0') {
            $value = 'false';
        } elseif ($value == '1') {
            $value = 'true';
        }
        return $value;
    }

    /**
     * Try to initialize some configuration values
     *
     * @throws \Exception
     */
    private function initConfig()
    {
        // Set encoding type
        // Check which method to use based on PHP Version
        if (function_exists('iconv') && PHP_VERSION_ID < 50600) {
            // Use older, depreciated iconv settings
            iconv_set_encoding('internal_encoding', 'UTF-8');
            iconv_set_encoding('input_encoding', 'UTF-8');
            iconv_set_encoding('output_encoding', 'UTF-8');
        } elseif (PHP_VERSION_ID >= 50600) {
            // Use newer settings
            ini_set('default_charset', 'UTF-8');
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
        $this->running_config['compression_used'] = ($zlib_compression || $gzhandler);

        ## @@ would be nice to move this to the config file at some point
        # http://mantis.phplist.com/view.php?id=15521
        ## set it on the fly, although that will probably only work with Apache
        ## we need to save this in the DB, so that it'll work on commandline
        $public_scheme = (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on')) ? 'https' : 'http';
        $this->running_config['scheme'] =  $public_scheme;

        if ($this->running_config['USE_CUSTOM_PUBLIC_PROTOCOL']) {
            $this->running_config['public_scheme'] = $this->running_config['PUBLIC_PROTOCOL'];
        } else {
            $this->running_config['public_scheme'] = $public_scheme;
        }

        # set some defaults if they are not specified
        $this->running_config['DEVSITE'] = false;
        $this->running_config['TRANSLATIONS_XML'] = 'http://translate.phplist.com/translations.xml';

        $this->running_config['TLD_AUTH_LIST'] = 'https://www.phplist.com/files/tlds-alpha-by-domain.txt';
        $this->running_config['TLD_AUTH_MD5'] = 'https://www.phplist.com/files/tlds-alpha-by-domain.txt.md5';
        $this->running_config['TLD_REFETCH_TIMEOUT'] = 15552000; ## 180 days, about 6 months
        $this->running_config['SHOW_UNSUBSCRIBELINK'] = true;

        // Check if desired hashing algo is supported by server
        if (function_exists('hash_algos')
            && !in_array($this->running_config['ENCRYPTION_ALGO'], hash_algos())
        ) {
            throw new \Exception('Encryption algorithm "' . $this->running_config['ENCRYPTION_ALGO'] . '" not supported, change your configuration');
        }

        // check and store the length of a hash using the desired algo
        $this->running_config['hash_length'] = strlen(hash($this->running_config['ENCRYPTION_ALGO'], 'some text'));

        $this->running_config['NUMATTACHMENTS'] = 1;
        $this->running_config['FCKIMAGES_DIR'] = 'uploadimages';
        $this->running_config['USE_OUTLOOK_OPTIMIZED_HTML'] = 0;
        $this->running_config['USE_PREPARE'] = 0;
        $this->running_config['HTMLEMAIL_ENCODING'] = 'quoted-printable';
        $this->running_config['TEXTEMAIL_ENCODING'] = '7bit';
        $this->running_config['STATS_INTERVAL'] = 'weekly';
        $this->running_config['CLICKTRACK_LINKMAP'] = 0;
        $this->running_config['MERGE_DUPLICATES_DELETE_DUPLICATE'] = 1;
        $this->running_config['USE_PERSONALISED_REMOTEURLS'] = 1;
        $this->running_config['USE_LOCAL_SPOOL'] = 0;
        $this->running_config['SEND_LISTADMIN_COPY'] = true;
        $this->running_config['BLACKLIST_EMAIL_ON_BOUNCE'] = 5;
        $this->running_config['ENCRYPT_ADMIN_PASSWORDS'] = 1;
        $this->running_config['PASSWORD_CHANGE_TIMEFRAME'] = '1 day';
        $this->running_config['MAX_SENDPROCESSES'] = 1;
        $this->running_config['SENDPROCESS_SERVERNAME'] = 'localhost';
        $this->running_config['DB_TRANSLATION'] = 0;
        $this->running_config['ALLOW_DELETEBOUNCE'] = 1;
        $this->running_config['MESSAGE_SENDSTATUS_INACTIVETHRESHOLD'] = 120;
        $this->running_config['MESSAGE_SENDSTATUS_SAMPLETIME'] = 600;
        $this->running_config['SEND_QUEUE_PROCESSING_REPORT'] = true;
        $this->running_config['MAX_AVATAR_SIZE'] = 2000;
        $this->running_config['ADD_EMAIL_THROTTLE'] = 1; ## seconds between addemail ajax requests
        $this->running_config['SENDTEST_THROTTLE'] = 1; ## seconds between send test
        $this->running_config['SENDTEST_MAX'] = 999; ## max number of emails in a send test
        $this->running_config['NAME'] = 'phpList';
        $this->running_config['installation_name'] = 'phpList';

        if ($this->running_config['USE_AMAZONSES']) {
            if ($this->running_config['AWS_ACCESSKEYID']  == '') {
                throw new \Exception('Invalid Amazon SES configuration: AWS_ACCESSKEYID not set');
            } elseif (!function_exists('curl_init')) {
                throw new \Exception('Invalid Amazon SES configuration: CURL not enabled');
            }
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $this->running_config['ACCESS_CONTROL_ALLOW_ORIGIN'] = 'http://' . $_SERVER['HTTP_HOST'];
        }

        $this->running_config['RFC_DIRECT_DELIVERY'] = false;  ## Request for Confirmation, delivery with SMTP
        # check whether Pear HTTP/Request is available, and which version
        # try 2 first

        # @@TODO finish this, as it is more involved than just renaming the class
        if (0 && class_exists('HTTP_Request2')) {
            $this->running_config['has_pear_http_request'] = 2;
        } else {
            @include_once 'HTTP/Request.php';
            $this->running_config['has_pear_http_request'] = class_exists('HTTP_Request');
        }
        $this->running_config['has_curl'] = function_exists('curl_init');
        $this->running_config['can_fetch_url'] = class_exists('HTTP_Request') || function_exists('curl_init');

        $system_tmpdir = ini_get('upload_tmp_dir');
        if ($this->running_config['TMPDIR'] && !empty($system_tmpdir)) {
            $this->running_config['tmpdir'] = $system_tmpdir;
        } elseif ($this->running_config['TMPDIR']) {
            $this->running_config['tmpdir'] = '/tmp';
        }
        if (!is_dir($this->running_config['TMPDIR']) || !is_writable($this->running_config['TMPDIR']) && !empty($system_tmpdir)) {
            $this->running_config['tmpdir'] = $system_tmpdir;
        }

        ## as the 'admin' in adminpages is hardcoded, don't put it in the config file
        ## remove possibly duplicated // at the beginning
        $this->running_config['adminpages'] = preg_replace('~^//~', '/', $this->running_config['PAGEROOT'] . '/admin');

        $this->running_config['systemroot'] = dirname(__FILE__);

        ## when click track links are detected, block sending
        ## if false, will only show warning. For now defaulting to false, but may change that later
        $this->running_config['BLOCK_PASTED_CLICKTRACKLINKS'] = false;

        if ($this->running_config['FORWARD_EMAIL_COUNT'] < 1) {
            throw new \Exception('Config Error: FORWARD_EMAIL_COUNT must be > (int) 0');
        }

        # allows FORWARD_EMAIL_COUNT forwards per subscriber per period in mysql interval terms default one day
        $this->running_config['FORWARD_EMAIL_PERIOD'] = '1 day';
        $this->running_config['EMBEDUPLOADIMAGES'] = 0;
        $this->running_config['IMPORT_FILESIZE'] = 5;
        $this->running_config['SMTP_TIMEOUT'] = 5;

        $this->running_config['noteditableconfig'] = [];

        ## global counters array to keep track of things
        $this->running_config['counters'] = [
                'campaign' => 0,
                'num_subscribers_for_message' => 0,
                'batch_count' => 0,
                'batch_total' => 0,
                'sendemail returned false' => 0,
                'send blocked by domain throttle' => 0,
            ];

        $this->running_config['disallowpages'] = [];

        # Set revision
        $this->running_config['CODEREVISION'] = '$Rev$';
        if (preg_match('/Rev: (\d+)/', '$Rev$', $match)) {
            $this->running_config['REVISION'] = $match[1];
        }

        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'cmd_line';

        $this->running_config['organisation_name'] = $server_name;
        $this->running_config['domain'] = $server_name;
        $this->running_config['website'] = $server_name;

        $xormask = md5(uniqid(rand(), true));
        $this->running_config['xormask'] = $xormask;
        $this->running_config['XORmask'] = $xormask;

        # identify pages that can be run on commandline
        $this->running_config['commandline_pages'] = [
                'dbcheck','send','processqueueforked','processqueue',
                'processbounces','import','upgrade','convertstats','reindex',
                'blacklistemail','systemstats','converttoutf8','initlanguages',
        ];

        $this->running_config['envelope'] = '-f' . $this->running_config['MESSAGE_ENVELOPE'];
        $this->running_config['coderoot'] = dirname(__FILE__) . '/';

        /*
          We request you retain the $PoweredBy variable including the links.
          This not only gives respect to the large amount of time given freely
          by the developers  but also helps build interest, traffic and use of
          PHPlist, which is beneficial to it's future development.

          You can configure your PoweredBy options in your config file

          Michiel Dethmers, phpList Ltd 2001-2015
        */
        $v = PHPLIST_DEV_VERSION ? 'dev' : PHPLIST_VERSION;

        if ($this->running_config['REGISTER']) {
            $PoweredByImage = '<p class="poweredby"><a href="https://www.phplist.com/poweredby?utm_source=pl' . $v . '&amp;utm_medium=poweredhostedimg&amp;utm_campaign=phpList" title="visit the phpList website" ><img src="http://powered.phplist.com/images/' . $v . '/power-phplist.png" width="70" height="30" title="powered by phpList version ' . $v . ', &copy; phpList ltd" alt="powered by phpList ' . $v . ', &copy; phpList ltd" border="0" /></a></p>';
        } else {
            $PoweredByImage = '<p class="poweredby"><a href="https://www.phplist.com/poweredby?utm_source=pl' . $v . '&amp;utm_medium=poweredlocalimg&amp;utm_campaign=phpList" title="visit the phpList website"><img src="images/power-phplist.png" width="70" height="30" title="powered by phpList version ' . $v . ', &copy; phpList ltd" alt="powered by phpList ' . $v . ', &copy; phpList ltd" border="0"/></a></p>';
        }
        $PoweredByText = '<div style="clear: both; font-family: arial, verdana, sans-serif; font-size: 8px; font-variant: small-caps; font-weight: normal; padding: 2px; padding-left:10px;padding-top:20px;">powered by <a href="https://www.phplist.com/poweredby?utm_source=download' . $v . '&amp;utm_medium=poweredtxt&amp;utm_campaign=phpList" target="_blank" title="powered by phpList version ' . $v . ', &copy; phpList ltd">phpList</a></div>';
        $this->running_config['PoweredBy'] = $this->running_config['PAGETEXTCREDITS'] ? $PoweredByText : $PoweredByImage;

        if (DEBUG && @($_SERVER['HTTP_HOST'] != 'dev.phplist.com')) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            foreach ($_REQUEST as $key => $val) {
                unset($$key);
            }
        } else {
            error_reporting(0);
        }

        ## experimental, mark mails 'todo' in the DB and process the 'todo' list, to avoid the subscriber query being run every queue run
        if ($this->running_config['MESSAGEQUEUE_PREPARE']) {
            ## with a multi-process config, we need the queue prepare mechanism and memcache
            if ($this->get('MAX_SENDPROCESSES', 1) > 1) {
                $this->running_config['MESSAGEQUEUE_PREPARE'] = true;
            } else {
                $this->running_config['MESSAGEQUEUE_PREPARE'] = false;
            }
        }

        if ($this->running_config['MESSAGE_ENVELOPE'] == '') {
            # why not try set it to "person in charge of this system". Will help get rid of a lot of bounces to nobody@server :-)
            $this->running_config['message_envelope'] = $this->get('admin_address');
        }
    }

    /**
     * Load default configuration
     *
     * @param Language $lan
     */
    private function loadDefaultConfig(Language $lan)
    {
        $defaultheader = '</head><body>';
        $defaultfooter = '</body></html>';

        if (isset($_SERVER['HTTP_HOST'])) {
            $D_website = $_SERVER['HTTP_HOST'];
        } else {
            $D_website = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        }
        $D_domain = $D_website;
        if (preg_match('#^www\.(.*)#i', $D_domain, $regs)) {
            $D_domain = $regs[1];
        }

        $this->default_config = [
                # what is your website location (url)
                'website' => [
                    'value' => $D_website,
                    'description' => $lan->get('Website address (without http://)'),
                    'type' => 'text',
                    'allowempty' => false, ## indication this value cannot be empty (1 being it can be empty)
                    'category' => 'general',
                ],
                # what is your domain (for sending emails)
                'domain' => [
                    'value' => $D_domain,
                    'description' => $lan->get('Domain Name of your server (for email)'),
                    'type' => 'text',
                    'allowempty' => false,
                    'category' => 'general',
                ],
                # admin address is the person who is in charge of this system
                'admin_address' => [
                    'value' => 'webmaster@[DOMAIN]',
                    'description' => $lan->get('Person in charge of this system (one email address)'),
                    'type' => 'email',
                    'allowempty' => false,
                    'category' => 'general',
                ],
                # name of the organisation
                'organisation_name' => [
                    'value' => '',
                    'description' => $lan->get('Name of the organisation'),
                    'type' => 'text',
                    'allowempty' => true,
                    'category' => 'general',
                ],
                # how often to check for new versions of PHPlist
                'check_new_version' => [
                    'value' => '7',
                    'description' => $lan->get('How often do you want to check for a new version of phplist (days)'),
                    'type' => 'integer',
                    'min' => 1,
                    'max' => 180,
                    'category' => 'security',
                ],
                # admin addresses are other people who receive copies of subscriptions
                'admin_addresses' => [
                    'value' => '',
                    'description' => $lan->get('List of email addresses to CC in system messages (separate by commas)'),
                    'type' => 'emaillist',
                    'allowempty' => true,
                    'category' => 'reporting',
                ],
                'campaignfrom_default' => [
                    'value' => '',
                    'description' => $lan->get('Default for \'From:\' in a campaign'),
                    'type' => 'text',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                'notifystart_default' => [
                    'value' => '',
                    'description' => $lan->get('Default for \'address to alert when sending starts\''),
                    'type' => 'email',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                'notifyend_default' => [
                    'value' => '',
                    'description' => $lan->get('Default for \'address to alert when sending finishes\''),
                    'type' => 'email',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                'always_add_googletracking' => [
                    'value' => '0',
                    'description' => $lan->get('Always add Google tracking code to campaigns'),
                    'type' => 'boolean',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                # report address is the person who gets the reports
                'report_address' => [
                    'value' => 'listreports@[DOMAIN]',
                    'description' => $lan->get('Who gets the reports (email address, separate multiple emails with a comma)'),
                    'type' => 'emaillist',
                    'allowempty' => true,
                    'category' => 'reporting',
                ],
                # where will messages appear to come from
                'message_from_address' => [
                    'value' => 'noreply@[DOMAIN]',
                    'description' => $lan->get('From email address for system messages'),
                    'type' => 'email',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                'message_from_name' => [
                    'value' => $lan->get('Webmaster'),
                    'description' => $lan->get('Name for system messages'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # what is the reply-to on messages?
                'message_replyto_address' => [
                    'value' => 'noreply@[DOMAIN]',
                    'description' => $lan->get('Reply-to email address for system messages'),
                    'type' => 'email',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # if there is only one visible list, do we hide it and automatically
                # subscribe subscribers who sign up
                ## not sure why you would not want this :-) maybe it should not be an option at all
                'hide_single_list' => [
                    'value' => '1',
                    'description' => $lan->get(
                        'If there is only one visible list, should it be hidden in the page and automatically subscribe subscribers who sign up'
                    ),
                    'type' => 'boolean',
                    'allowempty' => true,
                    'category' => 'subscription-ui',
                ],
                # categories for lists, to organise them a little bit
                # comma separated list of words
                'list_categories' => [
                    'value' => '',
                    'description' => $lan->get('Categories for lists. Separate with commas.'),
                    'type' => 'text',
                    'allowempty' => true,
                    'category' => 'segmentation',
                ],
                # width of a textline field
                'textline_width' => [
                    'value' => '40',
                    'description' => $lan->get('Width of a textline field (numerical)'),
                    'type' => 'integer',
                    'min' => 20,
                    'max' => 150,
                    'category' => 'subscription-ui',
                ],
                # dimensions of a textarea field
                'textarea_dimensions' => [
                    'value' => '10,40',
                    'description' => $lan->get('Dimensions of a textarea field (rows,columns)'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'subscription-ui',
                ],
                # send copies of subscribe, update unsubscribe messages to the administrator
                'send_admin_copies' => [
                    'value' => '0',
                    'description' => $lan->get('Send notifications about subscribe, update and unsubscribe'),
                    'type' => 'boolean',
                    'allowempty' => true,
                    'category' => 'reporting',
                ],
                # the main subscribe page, when there are multiple
                'defaultsubscribepage' => [
                    'value' => 1,
                    'description' => $lan->get('The default subscribe page when there are multiple'),
                    'type' => 'integer',
                    'min' => 1,
                    'max' => 999, // max(id) from subscribepage
                    'allowempty' => true,
                    'category' => 'subscription',
                ],
                # the default template for sending an html campaign
                'defaultcampaigntemplate' => [
                    'value' => 0,
                    'description' => $lan->get('The default HTML template to use when sending a campaign'),
                    'type' => 'text',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                # the template for system messages (welcome confirm subscribe etc)
                'systemmessagetemplate' => [
                    'value' => 0,
                    'description' => $lan->get('The HTML wrapper template for system messages'),
                    'type' => 'integer',
                    'min' => 0,
                    'max' => 999, // or max(id) from template
                    'allowempty' => true,
                    'category' => 'transactional',
                ],
                # the location of your subscribe script
                'subscribeurl' => [
                    'value' => $this->get('scheme') . '://[WEBSITE]' . $this->get('PAGEROOT') . '/?p=subscribe',
                    'description' => $lan->get('URL where subscribers can sign up'),
                    'type' => 'url',
                    'allowempty' => 0,
                    'category' => 'subscription',
                ],
                # the location of your unsubscribe script:
                'unsubscribeurl' => [
                    'value' => $this->get('scheme') . '://[WEBSITE]' . $this->get('PAGEROOT') . '/?p=unsubscribe',
                    'description' => $lan->get('URL where subscribers can unsubscribe'),
                    'type' => 'url',
                    'allowempty' => 0,
                    'category' => 'subscription',
                ],
                #0013076: Blacklisting posibility for unknown subscribers
                # the location of your blacklist script:
                'blacklisturl' => [
                    'value' => $this->get('scheme') . '://[WEBSITE]' . $this->get('PAGEROOT') . '/?p=donotsend',
                    'description' => $lan->get('URL where unknown subscriber can unsubscribe (do-not-send-list)'),
                    'type' => 'url',
                    'allowempty' => 0,
                    'category' => 'subscription',
                ],
                # the location of your confirm script:
                'confirmationurl' => [
                    'value' => $this->get('scheme') . '://[WEBSITE]' . $this->get('PAGEROOT') . '/?p=confirm',
                    'description' => $lan->get('URL where subscribers have to confirm their subscription'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'subscription',
                ],
                # url to change their preferences
                'preferencesurl' => [
                    'value' => $this->get('scheme') . '://[WEBSITE]' . $this->get('PAGEROOT') . '/?p=preferences',
                    'description' => $lan->get('URL where subscribers can update their details'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'subscription',
                ],
                # url to change their preferences
                'forwardurl' => [
                    'value' => $this->get('scheme') . '://[WEBSITE]' . $this->get('PAGEROOT') . '/?p=forward',
                    'description' => $lan->get('URL for forwarding campaigns'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'subscription',
                ],
                'ajax_subscribeconfirmation' => [
                    'value' => $lan->get(
                        '<h3>Thanks, you have been added to our newsletter</h3><p>You will receive an email to confirm your subscription. Please click the link in the email to confirm</p>'
                    ),
                    'description' => $lan->get('Text to display when subscription with an AJAX request was successful'),
                    'type' => 'textarea',
                    'allowempty' => true,
                    'category' => 'subscription',
                ],
                # the subject of the message
                'subscribesubject' => [
                    'value' => $lan->get('Request for confirmation'),
                    'description' => $lan->get('Subject of the message subscribers receive when they sign up'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # message that is sent when people sign up to a list
                # [LISTS] will be replaced with the list of lists they have signed up to
                # [CONFIRMATIONURL] will be replaced with the URL where a subscriber has to confirm
                # their subscription
                'subscribemessage' => [
                    'value' => '

  Almost welcome to our newsletter(s) ...

  Someone, hopefully you, has subscribed your email address to the following newsletters:

  [LISTS]

  If this is correct, please click the following link to confirm your subscription.
  Without this confirmation, you will not receive any newsletters.

  [CONFIRMATIONURL]

  If this is not correct, you do not need to do anything, simply delete this message.

  Thank you

    ',
                    'description' => $lan->get('Campaign subscribers receive when they sign up'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # subject of the message when they unsubscribe
                'unsubscribesubject' => [
                    'value' => $lan->get('Goodbye from our Newsletter'),
                    'description' => $lan->get('Subject of the message subscribers receive when they unsubscribe'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # message that is sent when they unsubscribe
                'unsubscribemessage' => [
                    'value' => '

  Goodbye from our Newsletter, sorry to see you go.

  You have been unsubscribed from our newsletters.

  This is the last email you will receive from us. Our newsletter system, phpList,
  will refuse to send you any further messages, without manual intervention by our administrator.

  If there is an error in this information, you can re-subscribe:
  please go to [SUBSCRIBEURL] and follow the steps.

  Thank you

  ',
                    'description' => $lan->get('Campaign subscribers receive when they unsubscribe'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # confirmation of subscription
                'confirmationsubject' => [
                    'value' => $lan->get('Welcome to our Newsletter'),
                    'description' => $lan->get('Subject of the message subscribers receive after confirming their email address'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # message that is sent to confirm subscription
                'confirmationmessage' => [
                    'value' => '

  Welcome to our Newsletter

  Please keep this message for later reference.

  Your email address has been added to the following newsletter(s):
  [LISTS]

  To update your details and preferences please go to [PREFERENCESURL].
  If you do not want to receive any more messages, please go to [UNSUBSCRIBEURL].

  Thank you

  ',
                    'description' => $lan->get('Campaign subscribers receive after confirming their email address'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # the subject of the message sent when changing the subscriber details
                'updatesubject' => [
                    'value' => $lan->get('[notify] Change of List-Membership details'),
                    'description' => $lan->get('Subject of the message subscribers receive when they have changed their details'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # the message that is sent when a subscriber updates their information.
                # just to make sure they approve of it.
                # confirmationinfo is replaced by one of the options below
                # userdata is replaced by the information in the database
                'updatemessage' => [
                    'value' => '

  This message is to inform you of a change of your details on our newsletter database

  You are currently member of the following newsletters:

  [LISTS]

  [CONFIRMATIONINFO]

  The information on our system for you is as follows:

  [USERDATA]

  If this is not correct, please update your information at the following location:

  [PREFERENCESURL]

  Thank you

    ',
                    'description' => $lan->get('Campaign subscribers receive when they have changed their details'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # this is the text that is placed in the [!-- confirmation --] location of the above
                # message, in case the email is sent to their new email address and they have changed
                # their email address
                'emailchanged_text' => [
                    'value' => '
  When updating your details, your email address has changed.
  Please confirm your new email address by visiting this webpage:

  [CONFIRMATIONURL]

  ',
                    'description' => $lan->get(
                        'Part of the message that is sent to their new email address when subscribers change their information, and the email address has changed'
                    ),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                # this is the text that is placed in the [!-- confirmation --] location of the above
                # message, in case the email is sent to their old email address and they have changed
                # their email address
                'emailchanged_text_oldaddress' => [
                    'value' => '
  Please Note: when updating your details, your email address has changed.

  A message has been sent to your new email address with a URL
  to confirm this change. Please visit this website to activate
  your membership.
  ',
                    'description' => $lan->get(
                        'Part of the message that is sent to their old email address when subscribers change their information, and the email address has changed'
                    ),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                'personallocation_subject' => [
                    'value' => $lan->get('Your personal location'),
                    'description' => $lan->get('Subject of message when subscribers request their personal location'),
                    'type' => 'text',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                'campaignfooter' => [
                    'value' => '--

    <div class="footer" style="text-align:left; font-size: 75%;">
      <p>This campaign was sent to [EMAIL] by [FROMEMAIL]</p>
      <p>To forward this campaign, please do not use the forward button of your email application, because this campaign was made specifically for you only. Instead use the <a href="[FORWARDURL]">forward page</a> in our newsletter system.<br/>
      To change your details and to choose which lists to be subscribed to, visit your personal <a href="[PREFERENCESURL]">preferences page</a><br/>
      Or you can <a href="[UNSUBSCRIBEURL]">opt-out completely</a> from all future mailings.</p>
    </div>

  ',
                    'description' => $lan->get('Default footer for sending a campaign'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'campaign',
                ],
                'forwardfooter' => [
                    'value' => '
     <div class="footer" style="text-align:left; font-size: 75%;">
      <p>This campaign has been forwarded to you by [FORWARDEDBY].</p>
      <p>You have not been automatically subscribed to this newsletter.</p>
      <p>If you think this newsletter may interest you, you can <a href="[SUBSCRIBEURL]">Subscribe</a> and you will receive our next newsletter directly to your inbox.</p>
      <p>You can also <a href="[BLACKLISTURL]">opt out completely</a> from receiving any further email from our newsletter application, phpList.</p>
    </div>
  ',
                    'description' => $lan->get('Footer used when a campaign has been forwarded'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'campaign',
                ],
                'pageheader' => [
                    'value' => $defaultheader,
                    'description' => $lan->get('Header of public pages.'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'subscription-ui',
                ],
                'pagefooter' => [
                    'value' => $defaultfooter,
                    'description' => $lan->get('Footer of public pages'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'subscription-ui',
                ],
                'personallocation_message' => [
                    'value' => '

You have requested your personal location to update your details from our website.
The location is below. Please make sure that you use the full line as mentioned below.
Sometimes email programmes can wrap the line into multiple lines.

Your personal location is:
[PREFERENCESURL]

Thank you.
',
                    'description' => $lan->get('Campaign to send when they request their personal location'),
                    'type' => 'textarea',
                    'allowempty' => 0,
                    'category' => 'transactional',
                ],
                'remoteurl_append' => [
                    'value' => '',
                    'description' => $lan->get('String to always append to remote URL when using send-a-webpage'),
                    'type' => 'text',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                'wordwrap' => [
                    'value' => '75',
                    'description' => $lan->get('Width for Wordwrap of Text messages'),
                    'type' => 'text',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                'html_email_style' => [
                    'value' => '',
                    'description' => $lan->get('CSS for HTML messages without a template'),
                    'type' => 'textarea',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                'alwayssendtextto' => [
                    'value' => '',
                    'description' => $lan->get('Domains that only accept text emails, one per line'),
                    'type' => 'textarea',
                    'allowempty' => true,
                    'category' => 'campaign',
                ],
                'tld_last_sync' => [
                    'value' => '0',
                    'description' => $lan->get('last time TLDs were fetched'),
                    'type' => 'text',
                    'allowempty' => true,
                    'category' => 'system',
                    'hidden' => true,
                ],
                'internet_tlds' => [
                    'value' => '',
                    'description' => $lan->get('Top level domains'),
                    'type' => 'textarea',
                    'allowempty' => true,
                    'category' => 'system',
                    'hidden' => true,
                ],

            ];
    }
}
