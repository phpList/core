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

class SubscriberTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        $this->config = new Config($this->configFile);
        $this->db = new Database($this->config);
        $this->subscriber = new Subscriber($this->config, $this->db);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSubscriberAddInvalidArgument()
    {
        $this->subscriber->addSubscriber('tester@', 'testpass');
    }

    public function testSubscriberAddEditSave()
    {
        $email = 'unittest@example.com';

        $new_subscriber = new SubscriberEntity(
            new EmailAddress($this->config, $email),
            new Password($this->config, 'IHAVEANEASYPASSWORD')
        );
        $this->subscriber->save($new_subscriber);

        $second_subscriber = $this->subscriber->getSubscriber($new_subscriber->id);
        $this->assertEquals($new_subscriber->password->getEncryptedPassword(), $second_subscriber->password->getEncryptedPassword());

        $this->subscriber->delete($second_subscriber->id);
    }




}
 