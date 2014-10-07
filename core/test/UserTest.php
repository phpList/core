<?php
namespace phpList\test;

// include __DIR__ . '\..\phpList.php';


use phpList\Subscriber;
use phpList\helper\Util;

class SubscriberTest extends \PHPUnit_Framework_TestCase {

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSubscriberAddInvalidArgument()
    {
        Subscriber::addSubscriber('tester@', 'testpass');
    }

    public function testSubscriberAddEditSave()
    {
        $email = 'unittest@example.com';
        if(Util::emailExists($email)){
            Subscriber::getSubscriberByEmailAddress($email)->delete();
        }
        $subscriber = new Subscriber($email);
        $subscriber->setPassword('IHAVEANEASYPASSWORD');
        $subscriber->save();

        $second_subscriber = Subscriber::getSubscriber($subscriber->id);
        $this->assertEquals($subscriber->getPassword(), $second_subscriber->getPassword());

        $second_subscriber->delete();
    }




}
 