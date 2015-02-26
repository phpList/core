<?php
namespace phpList\test;

use phpList\Config;
use phpList\EmailAddress;
use phpList\entities\SubscriberEntity;
use phpList\helper\Database;
use phpList\Pass;
use phpList\phpList;
use phpList\Subscriber;
use phpList\helper\Util;

// Symfony namespaces
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SubscriberTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Create a randomised email addy to register with
        $this->plainEmail = $emailAddress = 'unittest-' . rand( 0, 999999 ) . '@example.com';

        $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        $this->config = new Config($this->configFile);

        $this->emailAddress = new EmailAddress( $this->plainEmail );

        $this->pass = new Pass();

        $this->db = new Database($this->config);
        $this->subscriber = new Subscriber( $this->config, $this->db, $this->emailAddress, $this->pass );
        $this->scrEntity = new SubscriberEntity( $this->emailAddress, 'IHAVEANEASYPASSWORD' );

        // Optional: use DI to load the subscriber class instead:
        // // Create Symfony DI service container object for use by other classes
        // $this->container = new ContainerBuilder();
        // // Create new Symfony file loader to handle the YAML service config file
        // $loader = new YamlFileLoader( $this->container, new FileLocator(__DIR__) );
        // // Load the service config file, which is in YAML format
        // $loader->load( '../services.yml' );
        // $this->container->setParameter( 'config.configfile', '/var/www/pl4/config.ini' );
        // $this->subscriber = $this->container->get( 'Subscriber' );
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
        $emailCopy = $this->plainEmail;
        $this->subscriber->save( $this->scrEntity );

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
            , $scrEntity->emailAddress
        );

        // Delete the testing subscribers
        // NOTE: These entities are used in other tests and must be deleted in
        // whatever method users them last
        $this->subscriber->delete( $vars['id'] );

        return $scrEntity;
    }
}
