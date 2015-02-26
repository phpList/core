<?php
namespace phpList\test;

use phpList\Config;
use phpList\EmailAddress;
use phpList\entities\SubscriberEntity;
use phpList\helper\Database;
use phpList\Password;
use phpList\phpList;
use phpList\Subscriber;
use phpList\helper\Util;

class ConfigTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        // $this->config = new Config($this->configFile);
        // $this->db = new Database($this->config);
        // $this->subscriber = new Subscriber($this->config, $this->db);
        //
        // $this->emailAddress = $emailAddress = 'unittest-' . rand( 0, 999999 ) . '@example.com';
    }
}
