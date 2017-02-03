<?php

namespace phpList\test;

use phpList\Config;
use phpList\helper\Database;
use phpList\phpList;
use phpList\Entity\ListEntity;
use phpList\Model\ListModel;

// Symfony namespaces
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ListModelTest extends \PHPUnit_Framework_TestCase
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
        $this->container->setParameter('config.configfile', '/var/www/pl4/config.ini');
        // Get objects from container
        $this->listModel = $this->container->get('ListModel');
    }

    public function testAddSubscriber()
    {
        // Choose a random list and subscriber id
        $scrId = 22;
        $listId = 1;

        // Add the subscriber
        $result = $this->listModel->addSubscriber($scrId, $listId);

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
        $result = $this->listModel->removeSubscriber($vars['listId'], $vars['scrId']);

        // Check that the subscriber was deleted without error
        $this->assertTrue(false !== $result);
    }

    // public function testAdd()
    // {
    //     $ListId = $this->ListModel->save( $this->emailAddress, $this->plainPass );
    //
    //     return $ListId;
    // }

    // /**
    //  * @depends testSave
    //  */
    // public function testUpdate( $ListId )
    // {
    //     $this->updatedEmailAddress = 'updated-' . rand( 0, 999999 ) . '@example.com';
    //     $result = $this->ListModel->update( 1, 1, $this->updatedEmailAddress, 1, 1, $ListId, 1 );
    // }
}
