<?php
namespace phpList\test;

use phpList\Config;
use phpList\EmailUtil;
use phpList\helper\Database;
use phpList\Pass;
use phpList\phpList;
use phpList\SubscriberManager;
use phpList\helper\Util;
use phpList\Model\SubscriberModel;
use phpList\Entity\SubscriberEntity;

// Symfony namespaces
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SubscriberTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Create a randomised email addy to register with
        $this->emailAddress = 'unittest-' . rand( 0, 999999 ) . '@example.com';
        $this->plainPass = 'easypassword';

        // Instantiate config object
        $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        $this->config = new Config( $this->configFile );

        // Instantiate util classes
        $this->emailUtil = new EmailUtil();
        $this->pass = new Pass();

        // Instantiate remaining classes
        $this->db = new Database( $this->config );
        $this->subscriberModel = new SubscriberModel( $this->config, $this->db );
        $this->subscriberManager = new SubscriberManager(
            $this->config
            , $this->emailUtil
            , $this->pass
            , $this->subscriberModel
        );

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
    * @expectedException \Exception
    */
    public function testAddInvalidEmail()
    {
        // Test passes if this throws an exception
        $this->subscriberManager->add( 'deliberately-invalid-email-address', 'testpass' );
    }

    /**
     * @note This belongs in a test class for SubscriberEntity, not here
     */
    public function testAdd()
    {
        // Add new subscriber properties to the entity
        $scrEntity = new SubscriberEntity( $this->emailAddress, $this->plainPass );
        // Copy the email address to test it later
        $emailCopy = $this->emailAddress;
        // Save the subscriber
        $newSubscriberId = $this->subscriberManager->add( $scrEntity );

        // Test that an ID was returned
        $this->assertNotEmpty( $newSubscriberId );
        $this->assertTrue( is_numeric( $newSubscriberId ) );

        // Pass on to the next test
        return array( 'id' => $newSubscriberId, 'email' => $emailCopy, 'encPass' => $scrEntity->encPass );
    }

    /**
     * @depends testAdd
     */
    public function testGetSubscriber( array $vars )
    {
        $scrEntity = $this->subscriberManager->getSubscriber( $vars['id'] );

        // Check that the correct entity was returned
        $this->assertInstanceOf( '\phpList\Entity\SubscriberEntity', $scrEntity );
        // Check that the saved password isn't in plain text
        $this->assertNotEquals( $this->plainPass, $scrEntity->encPass );
        // Check that retrieved email matches what was set
        $this->assertEquals( $vars['email'] , $scrEntity->emailAddress );

        return $scrEntity;
    }

    /**
    * @depends testGetSubscriber
    */
    public function testUpdatePass( $scrEntity )
    {
        // Set a new password for testing
        $newPlainPass = 'newEasyPassword';
        // Update the password
        $this->subscriberManager->updatePass( $newPlainPass, $scrEntity );
        // Get a fresh copy of the subscriber from db to check updated details
        $updatedScrEntity = $this->subscriberManager->getSubscriber( $scrEntity->id );

        // Check that the passwords are not the same; that it was updated
        $this->assertNotEquals( $scrEntity->encPass, $updatedScrEntity->encPass );
    }

    /**
    * @depends testGetSubscriber
    */
    public function testDelete( $scrEntity )
    {
        // Delete the testing subscribers
        // NOTE: These entities are used in other tests and must be deleted in
        // whatever method users them last
        $result = $this->subscriberManager->delete( $scrEntity->id );

        // Check that delete was successful
        $this->assertTrue( $result );
    }
}
