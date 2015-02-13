<?php

// FIXME: Autoloading failing for helper classes. These requires are a workaround
require_once( dirname( __FILE__ ) . '/../helper/Logger.php' );
require_once( dirname( __FILE__ ) . '/../helper/Database.php' );

use phpList\Admin;
use phpList\Config;
use phpList\helper;
use phpList\MailingList;
use phpList\entities\MailingListEntity;

class MailingListTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        $this->logger = new phplist\helper\Logger();
        $this->config = new Config( $this->configFile );
        $this->database = new phplist\helper\Database( $this->config, $this->logger );
        $this->mailingList = new MailingList( $this->config, $this->database );
    }

    public function testCreateList()
    {
        $list = new MailingListEntity(
            'DevList'
            , 'List for unit testing'
            , 1
            , 1 // NOTE: Admin ID is hardcoded to 1 for now
            , 1
            , 'Unit test category'
            , ''
        );

        // Save ML to DB
        $this->mailingList->save( $list );
        // Check that the ID still correct
        // NOTE: Is this a useful test?
        $this->assertFalse( ( $list->id == 0 ) );

        // Pass the list onto the next test to avoid repetition
        return $list;
    }

    /**
     * @depends testCreateList
     */
    public function testUpdateList( $list )
    {
        $updated_list_name = 'UpdatedList';
        // Set the new list name
        $list->name = $updated_list_name;
        // Save the updated name
        $this->mailingList->update( $list );
        // Fetch the list again to check its attributes
        $refetchedList = $this->mailingList->getListById( $list->id );
        // Check that the name was updated
        $this->assertTrue( ( $refetchedList->name === $updated_list_name ) );
    }

    public function testGetAllLists()
    {
        $result = $this->mailingList->getAllLists();
    }
}
