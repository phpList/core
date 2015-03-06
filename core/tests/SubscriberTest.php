<?php
namespace phpList\test;

use phpList\Config;
use phpList\EmailUtil;
use phpList\Entity\SubscriberEntity;
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
        $this->emailAddress = 'unittest-' . rand( 0, 999999 ) . '@example.com';
        $this->plainPass = 'easypassword';

        // Instantiate config object
        $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        $this->config = new Config( $this->configFile );

        // Instantiate util classes
        $this->emailUtil = new EmailUtil();
        $this->pass = new Pass();

        // Instantiate remaning classes
        $this->db = new Database( $this->config );
        $this->subscriber = new Subscriber( $this->config, $this->db, $this->emailUtil, $this->pass );

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
    public function testAddSubscriberInvalidEmail()
    {
        // Test passes if this throws an exception
        $this->subscriber->addSubscriber( 'deliberately-invalid-email-address', 'testpass' );
    }

    public function testAddSubscriber()
    {
        $plainPass = 'testpass';

        // Save subscriber to DB
        $newScrId = $this->subscriber->addSubscriber( $this->emailAddress, $plainPass );

        // Check that something looking like an ID was returned
        $this->assertTrue( is_numeric( $newScrId ) ) ;

        // Pass on to the next test
        return array( 'id' => $newScrId, 'email' => $this->emailAddress, 'plainPass' => $plainPass );
    }

    /**
     * @note This belongs in a test class for SubscriberEntity, not here
     */
    public function testSave()
    {
        $encPass = $this->pass->encrypt( $this->plainPass );
        // Add new subscriber properties to the entity
        $this->scrEntity = new SubscriberEntity( $this->emailAddress, $encPass );
        // Copy the email address to test it later
        $emailCopy = $this->emailAddress;
        // Save the subscriber
        $this->subscriber->save( $this->scrEntity );
        // Pass on to the next test
        return array( 'id' => $this->scrEntity->id, 'email' => $emailCopy, 'encPass' => $encPass );
    }

    public function testSaveNew()
    {
        $encPass = $this->pass->encrypt( $this->plainPass );
        // Add new subscriber properties to the entity
        $this->scrEntity = new SubscriberEntity( $this->emailAddress, $encPass );
        // Copy the email address to test it later
        $emailCopy = $this->emailAddress;
        // Save the subscriber
        $this->subscriber->save( $this->scrEntity );
        // Pass on to the next test
        return array( 'id' => $this->scrEntity->id, 'email' => $emailCopy, 'encPass' => $encPass );
    }

    /**
     * @depends testAddSubscriber
     * @param SubscriberEntity $scrEntity [description]
     */
    public function testGetSubscriber( array $vars )
    {
        $scrEntity = $this->subscriber->getSubscriber( $vars['id'] );
        // Check that the saved password isn't in plain text
        $this->assertNotEquals( $scrEntity->plainPass , $vars['plainPass'] );
        // Check that retrieved email matches what was set
        $this->assertEquals( $vars['email'] , $scrEntity->emailAddress );

        // Delete the testing subscribers
        // NOTE: These entities are used in other tests and must be deleted in
        // whatever method users them last
        // $this->subscriber->delete( $vars['id'] );

        return $scrEntity;
    }
}
