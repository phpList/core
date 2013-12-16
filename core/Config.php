<?php
/**
 * User: SaWey
 * Date: 16/12/13
 */

namespace phpList;

class Config extends UserConfig{
    private static $_instance;
    public $running_config = array();

    /**
     * Default constructor
     * load configuration from database each new session
     * TODO: probably not a good idea when using an installation with multiple users
     */
    private function __construct(){
        //do we have a configuration saved in session?
        if(isset($_SESSION['running_config'])){
            $this->running_config = $_SESSION['running_config'];
        }else{
            $this->loadAllFromDB();
        }
    }

    /**
     * Load the entire configuration from the database
     */
    private function loadAllFromDB(){
        //try to load additional configuration from db
        /*$has_db_config = phpList::DB()->Sql_Table_Exists(Config::getTableName('config'), 1);
        //TODO: Should we not automatically load config from db or do this selectively
        Config::setRunningConfig('has_db_config', $has_db_config);
        */
        $this->running_config = array();
        $result = phpList::DB()->Sql_Query(sprintf('SELECT item, value FROM %s', Config::getTableName('config')));
        while($row = phpList::DB()->Sql_Fetch_Assoc_Query($result)){
            $this->running_config[$row['item']] = $row['value'];
        }

        $_SESSION['running_config'] = $this->running_config;
    }

    /**
     * @return Config
     */
    private static function instance(){
        if (!Config::$_instance instanceof self) {
            Config::$_instance = new self();
        }
        return Config::$_instance;
    }

    /**
     * Get the table name including prefix
     * @param $table_name
     * @param bool $is_user_table
     * @return string
     */
    public static function getTableName($table_name, $is_user_table = false){
        return ($is_user_table ? Config::USERTABLE_PREFIX : Config::TABLE_PREFIX) . $table_name;
    }

    /**
     * Get an config item from the database
     * @param string $item
     * @param null $default
     * @return null|mixed
     */
    private function fromDB($item, $default = null){
        if (Config::get('has_db_config')) {
            $query = sprintf(
                'SELECT value, editable
                FROM %s
                WHERE item = "%s"',
                Config::getTableName('config'), $item);
            $result = phpList::DB()->Sql_Query($query);
            if (phpList::DB()->Sql_Num_Rows($result) == 0) {
                if($default == null){
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
                }else{
                    return $default;
                }
            } else {
                $row = phpList::DB()->Sql_Fetch_Row($result);
                return $row[0];
            }
        }
        return $default;
    }

    /**
     * Set an item in the running configuration
     * These values are only available in the current session
     * @param string $item
     * @param mixed value
     */
    public static function setRunningConfig($item, $value){
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
    public static function setDBConfig($item, $value, $editable=1){
        ## in case DB hasn't been initialised
        if(!Config::get('has_db_config')) return false;

        $configInfo = DefaultConfig::get($item);
        if (!$configInfo) {
            $configInfo = array(
                'type' => 'unknown',
                'allowempty' => true,
                'value' => '',
            );
        }
        ## to validate we need the actual values
        $value = str_ireplace('[domain]',Config::get('domain'),$value);
        $value = str_ireplace('[website]',Config::get('website'),$value);

        switch ($configInfo['type']) {
            case 'boolean':
                if ($value == "false" || $value == "no") {
                    $value = 0;
                } elseif ($value == "true" || $value == "yes") {
                    $value = 1;
                }
                break;
            case 'integer':
                $value = sprintf('%d',$value);
                if ($value < $configInfo['min']) $value = $configInfo['min'];
                if ($value > $configInfo['max']) $value = $configInfo['max'];
                break;
            case 'email':
                if (!Validation::isEmail($value)) {
                    return $configInfo['description'].': '.s('Invalid value for email address');
                }
                break;
            case 'emaillist':
                $valid = array();
                $emails = explode(',',$value);
                foreach ($emails as $email) {
                    if (Validation::isEmail($email)) {
                        $valid[] = $email;
                    } else {
                        return $configInfo['description'].': '.s('Invalid value for email address');
                    }
                }
                $value = join(',',$valid);
                break;
        }
        ## reset to default if not set, and required
        if (empty($configInfo['allowempty']) && empty($value)) {
            $value = $configInfo['value'];
        }
        if (!empty($configInfo['hidden'])) {
            $editable = false;
        }

        phpList::DB()->Sql_Replace( Config::getTableName('config'), array('item'=>$item, 'value'=>$value, 'editable'=>$editable), 'item');

        //add to running config
        Config::setRunningConfig($item, $value);

        return true;
    }

    /**
     * Get an item from the config
     * @param string $item
     * @return mixed|null|string
     */
    public static function get($item){
        $value = '';
        if(isset(Config::instance()->running_config[$item])){
            $value = Config::instance()->running_config[$item];
        }else{
            //try to find it in db
            $value = Config::instance()->fromDB($item);
        }

        $find =     array('[WEBSITE]', '[DOMAIN]', '<?=VERSION?>');
        $replace =  array(
            Config::instance()->running_config['website'],
            Config::instance()->running_config['domain'],
            Config::instance()->running_config['VERSION']
        );
        $value = str_replace($find, $replace, $value);
        return $value;
    }

    /**
     * Get config item and replace with user unique id where needed
     * @param string $item
     * @param int $user_id
     * @return mixed|null|string
     */
    public static function getUserConfig($item, $user_id = 0) {
        $value = Config::get($item, false);

        # if this is a subpage item, and no value was found get the global one
        if(!$value && strpos( $item,":") !== false) {
            list ($a, $b) = explode(":", $item);
            $value = Config::getUserConfig($a, $user_id);
        }
        if ($user_id != 0) {
            $uniq_id = User::getUniqueId($user_id);
            # parse for placeholders
            # do some backwards compatibility:
            # hmm, reverted back to old system

            $url = Config::get('unsubscribeurl');
            $sep = strpos($url,'?') !== false ? '&' : '?';
            $value = str_ireplace('[UNSUBSCRIBEURL]', $url . $sep . 'uid=' . $uniq_id, $value);
            $url = Config::get('confirmationurl');
            $sep = strpos($url,'?') !== false ? '&' : '?';
            $value = str_ireplace('[CONFIRMATIONURL]', $url . $sep . 'uid=' . $uniq_id, $value);
            $url = Config::get('preferencesurl');
            $sep = strpos($url,'?') !== false ? '&' : '?';
            $value = str_ireplace('[PREFERENCESURL]', $url . $sep . 'uid=' . $uniq_id, $value);
        }
        $value = str_ireplace('[SUBSCRIBEURL]', Config::get('subscribeurl'), $value);
        if ($value == '0') {
            $value = 'false';
        }
        elseif ($value == '1') {
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
    public static function defaultConfig($item){
        return DefaultConfig::get($item);
    }

}