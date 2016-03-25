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
class ListManagerTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Create Symfony DI service container object for use by other classes
        $this->container = new ContainerBuilder();
        // Create new Symfony file loader to handle the YAML service config file
        $loader = new YamlFileLoader( $this->container, new FileLocator(__DIR__) );
        // Load the service config file, which is in YAML format
        $loader->load( '../services.yml' );
        // Get objects from container
        $this->scrEntity = $this->container->get( 'SubscriberEntity' );
        $this->listManager = $this->container->get( 'ListManager' );
    }

    public function testAddSubscriber()
    {
        // Choose a random list and subscriber id
        $scrId = 22;
        $this->scrEntity->id = $scrId;
        $listId = 1;

        // Add the subscriber
        $result = $this->listManager->addSubscriber( $this->scrEntity, $listId );

        // Check that the subscriber was added without error
        $this->assertTrue( false !== $result );

        // Return the IDs used for the next test to clean up
        return array( 'listId' => $listId, 'scrId' => $scrId );
    }

    /**
     * @depends testAddSubscriber
     */
    public function testRemoveSubscriber( $vars )
    {
        $this->scrEntity->id = $vars['scrId'];
        $result = $this->listManager->removeSubscriber( $vars['listId'], $this->scrEntity );

        // Check that the subscriber was deleted without error
        $this->assertTrue( false !== $result );
    }

    // /**
    //  * Helper method to check all correct list properties exist
    //  * @Note Values / typing is not checked
    //  * @param ListEntity $list Populated object
    //  */
    // public function checkListAttributesExist( ListEntity $list )
    // {
    //     // Set list of attributes to check
    //     $keys = array(
    //         'id'
    //         , 'name'
    //         , 'description'
    //         , 'entered'
    //         , 'listorder'
    //         , 'prefix'
    //         , 'rssfeed'
    //         , 'modified'
    //         , 'active'
    //         , 'owner'
    //         , 'category'
    //     );
    //     // Check each attribute exists
    //     foreach ( $keys as $key ) {
    //         $this->assertTrue( array_key_exists( $key, $list ) );
    //     }
    // }
    //
    // /**
    //  * Test creation of a new mailing list
    //  */
    // public function testCreateList()
    // {
    //     $list = new ListEntity(
    //         'DevList'
    //         , 'List for unit testing'
    //         , 1
    //         , 1 // NOTE: Admin ID is hardcoded to 1 for now
    //         , 1
    //         , 'Unit test category'
    //         , ''
    //     );
    //
    //     // Save ML to DB
    //     $this->mailingList->save( $list );
    //     // Check that the ID still correct
    //     // NOTE: Is this a useful test?
    //     $this->assertFalse( ( $list->id == 0 ) );
    //     // Check all the attributes exist
    //     $this->checkListAttributesExist( $list );
    //     // Pass the list onto the next test to avoid repetition
    //     return $list;
    // }
    //
    // /**
    //  * Test updating an existing mailinglist
    //  * @depends testCreateList
    //  */
    // public function testUpdateList( $list )
    // {
    //     $updated_list_name = 'UpdatedList';
    //     // Set the new list name
    //     $list->name = $updated_list_name;
    //     // Save the updated name
    //     $this->mailingList->update( $list );
    //     // Fetch the list again to check its attributes
    //     $refetchedList = $this->mailingList->getListById( $list->id );
    //     // Check that the name was updated
    //     $this->assertTrue( ( $refetchedList->name === $updated_list_name ) );
    // }
    //
    // /**
    //  * Test getching all mailinglists
    //  */
    // public function testGetAllLists()
    // {
    //     // Fetch all lists
    //     $result = $this->mailingList->getAllLists();
    //     // Check that some lists were found
    //     $this->assertTrue( count( $result ) >= 2 );
    //     // Check each returned list
    //     foreach ( $result as $list ) {
    //         $this->checkListAttributesExist( $list );
    //     }
    // }
}
