<?php
namespace phpList\test;

use phpList\Admin;
use phpList\Config;
use phpList\MailingList;

class MailingListTest extends \PHPUnit_Framework_TestCase {
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
}
 