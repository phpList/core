<?php
/**
 * User: SaWey
 * Date: 23/12/13
 */

namespace phpList\test;

// include __DIR__ . '\..\phpList.php';


use phpList\User;
use phpList\helper\Util;

class UserTest extends \PHPUnit_Framework_TestCase {

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUserAddInvalidArgument()
    {
        User::addUser('tester@', 'testpass');
    }

    public function testUserAddEditSave()
    {
        $email = 'unittest@example.com';
        if(Util::emailExists($email)){
            User::getUserByEmail($email)->delete();
        }
        $user = new User($email);
        $user->setPassword('IHAVEANEASYPASSWORD');
        $user->save();

        $second_user = User::getUser($user->id);
        $this->assertEquals($user->getPassword(), $second_user->getPassword());

        $second_user->delete();
    }




}
 