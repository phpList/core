<?php

use phpList\Admin;
use phpList\Config;
use phpList\helper;
use phpList\ListManager;
use phpList\Entity\ListEntity;

// Symfony namespaces
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @Note: Assumes that the database contains an admin and multiple mailing lists
 */
class ListManagerTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        // Create Symfony DI service container object for use by other classes
        $this->container = new ContainerBuilder();
        // Create new Symfony file loader to handle the YAML service config file
        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
        // Load the service config file, which is in YAML format
        $loader->load('../services.yml');
        // Set necessary config class parameter
        $this->container->setParameter('config.configfile', __DIR__ . '/../../config.ini.dist');
        // Get objects from container
        $this->scrEntity = $this->container->get('SubscriberEntity');
        $this->listManager = $this->container->get('ListManager');
    }

    public function testAddSubscriber()
    {
        // Choose a random list and subscriber id
        $scrId = 22;
        $this->scrEntity->id = $scrId;
        $listId = 1;

        // Add the subscriber
        $result = $this->listManager->addSubscriber($this->scrEntity, $listId);

        // Check that the subscriber was added without error
        $this->assertTrue(false !== $result);

        // Return the IDs used for the next test to clean up
        return array( 'listId' => $listId, 'scrId' => $scrId );
    }

    /**
     * @depends testAddSubscriber
     */
    public function testRemoveSubscriber($vars)
    {
        $this->scrEntity->id = $vars['scrId'];
        $result = $this->listManager->removeSubscriber($vars['listId'], $this->scrEntity);

        // Check that the subscriber was deleted without error
        $this->assertTrue(false !== $result);
    }
}
