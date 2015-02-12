<?php

// FIXME: Autoloading failing for helper classes. These requires are a workaround
require_once( dirname( __FILE__ ) . '/../helper/Logger.php' );
require_once( dirname( __FILE__ ) . '/../helper/Database.php' );

use phpList\Admin;
use phpList\Config;
use phpList\helper;
use phpList\MailingList;

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
        //first need to create an admin
        try{
            $admin = new Admin("UnitTester");
            $admin->save();
        }
        catch(\Exception $e){
            $admin = Admin::getAdmins("UnitTester")[0];
        }

        $list = new MailingList('DevList', 'List for unit testing', 1, $admin->id, 1, 'Unit test category', '');
        $list->save();
        $this->assertFalse(($list->id == 0));

        $updated_list_name = 'UpdatedList';
        $list->name = $updated_list_name;
        $list->update();
        $refetchedList = MailingList::getListById($list->id);
        $this->assertTrue(($refetchedList->name === $updated_list_name));

    }

    public function testGetAllLists()
    {
        $result = $this->mailingList->getAllLists();
    }
}
