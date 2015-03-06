<?php
namespace phpList\test;

// use phpList\EmailUtil;
// use phpList\entities\SubscriberEntity;
// use phpList\Pass;
// use phpList\helper\Util;
//
// // Symfony namespaces
// use Symfony\Component\DependencyInjection\ContainerBuilder;
// use Symfony\Component\DependencyInjection\Reference;
// use Symfony\Component\Config\FileLocator;
// use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

use phpList\Model\SubscriberModel;
use phpList\Entity\SubscriberEntity;
use phpList\Subscriber;
use phpList\Config;
use phpList\helper\Database;
use phpList\phpList;

class SubscriberModelTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Create a randomised email addy to register with
        $this->emailAddress = 'unittest-' . rand( 0, 999999 ) . '@example.com';
        $this->plainPass = 'easypassword';

        // Instantiate config object
        $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        $this->config = new Config( $this->configFile );

        // Instantiate remaining classes
        $this->db = new Database( $this->config );
        $this->subscriberModel = new SubscriberModel( $this->config, $this->db );
    }

    public function testSave()
    {
        $subscriberId = $this->subscriberModel->save( $this->emailAddress, $this->plainPass );

        return $subscriberId;
    }

    /**
     * @depends testSave
     */
    public function testUpdate( $subscriberId )
    {
        $this->updatedEmailAddress = 'updated-' . rand( 0, 999999 ) . '@example.com';
        $result = $this->subscriberModel->update( 1, 1, $this->updatedEmailAddress, 1, 1, $subscriberId, 1 );
    }
}
