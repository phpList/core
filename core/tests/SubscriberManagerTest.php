<?php
namespace phpList\test;

use phpList\Config;
use phpList\Entity\SubscriberEntity;
use phpList\Pass;
use phpList\SubscriberManager;

// Symfony namespaces
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SubscriberManagerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // Create a randomised email addy to register with
        $this->emailAddress = 'unittest-' . rand(0, 999999) . '@example.com';
        $this->plainPass = 'easypassword';

        // Optional: use DI to load the subscriber class instead:
        // Create Symfony DI service container object for use by other classes
        $this->container = new ContainerBuilder();
        // Create new Symfony file loader to handle the YAML service config file
        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
        // Load the service config file, which is in YAML format
        $loader->load('../services.yml');
        $this->container->setParameter('config.configfile', __DIR__ . '/../../config.ini.dist');
        $this->subscriberManager = $this->container->get('SubscriberManager');
    }

    /**
     * @expectedException \Exception
     */
    public function testAddInvalidEmail()
    {
        // Test passes if this throws an exception
        $this->subscriberManager->add('deliberately-invalid-email-address', 'testpass');
    }

    /**
     * @note This belongs in a test class for SubscriberEntity, not here
     */
    public function testAdd()
    {
        // Add new subscriber properties to the entity
        $scrEntity = new SubscriberEntity();
        $scrEntity->emailAddress = $this->emailAddress;
        $scrEntity->plainPass = $this->plainPass;

        // Copy the email address to test it later
        $emailCopy = $this->emailAddress;
        // Save the subscriber
        $newSubscriberId = $this->subscriberManager->add($scrEntity);

        // Test that an ID was returned
        $this->assertNotEmpty($newSubscriberId);
        $this->assertTrue(is_numeric($newSubscriberId));

        // Pass on to the next test
        return [ 'id' => $newSubscriberId, 'email' => $emailCopy, 'encPass' => $scrEntity->encPass ];
    }

    /**
     * @depends testAdd
     */
    public function testGetSubscriberById(array $vars)
    {
        $scrEntity = $this->subscriberManager->getSubscriberById($vars['id']);

        // Check that the correct entity was returned
        $this->assertInstanceOf('\phpList\Entity\SubscriberEntity', $scrEntity);
        // Check that the saved password isn't in plain text
        $this->assertNotEquals($this->plainPass, $scrEntity->encPass);
        // Check that retrieved email matches what was set
        $this->assertEquals($vars['email'], $scrEntity->emailAddress);

        return $scrEntity;
    }

    /**
     * @depends testGetSubscriberById
     */
    public function testUpdatePass($scrEntity)
    {
        // Set a new password for testing
        $newPlainPass = 'newEasyPassword';
        // Update the password
        $this->subscriberManager->updatePass($newPlainPass, $scrEntity);
        // Get a fresh copy of the subscriber from db to check updated details
        $updatedScrEntity = $this->subscriberManager->getSubscriberById($scrEntity->id);

        // Check that the passwords are not the same; that it was updated
        $this->assertNotEquals($scrEntity->encPass, $updatedScrEntity->encPass);
    }

    /**
     * @depends testGetSubscriberById
     */
    public function testDelete($scrEntity)
    {
        // Delete the testing subscribers
        // NOTE: These entities are used in other tests and must be deleted in
        // whatever method users them last
        $result = $this->subscriberManager->delete($scrEntity->id);

        // Check that delete was successful
        $this->assertTrue($result);
    }
}
