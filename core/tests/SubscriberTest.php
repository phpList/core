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

        $this->emailAddress = $emailAddress = 'unittest-' . rand( 0, 999999 ) . '@example.com';
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSubscriberAddInvalidArgument()
    {
        $this->subscriber->addSubscriber('tester@', 'testpass');
    }

    public function testSave()
    {
        $emailCopy = $this->emailAddress;
        $scrEntity = new SubscriberEntity(
            new EmailAddress( $this->config, $this->emailAddress ),
            new Password( $this->config, 'IHAVEANEASYPASSWORD' )
        );
        $this->subscriber->save( $scrEntity );

        return array( 'id' => $scrEntity->id, 'email' => $emailCopy );
    }

    /**
     * @depends testSave
     * @param SubscriberEntity $scrEntity [description]
     */
    public function testGetSubscriber( array $vars )
    {
        $scrEntity = $this->subscriber->getSubscriber( $vars['id'] );
        // Check that the saved passwords can be retrieved and are equal
        $this->assertEquals(
            $scrEntity->password->getEncryptedPassword()
            , $scrEntity->password->getEncryptedPassword()
        );
        // Check that retrieved email matches what was set
        $this->assertEquals(
            $vars['email']
            , $scrEntity->email_address->getAddress()
        );

        // Delete the testing subscribers
        // NOTE: These entities are used in other tests and must be deleted in
        // whatever method users them last
        $this->subscriber->delete( $vars['id'] );

        return $scrEntity;
    }
}
