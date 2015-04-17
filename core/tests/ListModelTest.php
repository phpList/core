<?php
namespace phpList\test;

// use phpList\EmailUtil;
// use phpList\entities\ListEntity;
// use phpList\Pass;
// use phpList\helper\Util;
//
// // Symfony namespaces
// use Symfony\Component\DependencyInjection\ContainerBuilder;
// use Symfony\Component\DependencyInjection\Reference;
// use Symfony\Component\Config\FileLocator;
// use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

use phpList\Model\ListModel;
use phpList\Entity\ListEntity;
use phpList\List;
use phpList\Config;
use phpList\helper\Database;
use phpList\phpList;

class ListModelTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Instantiate config object
        $this->configFile = dirname( __FILE__ ) . '/../../config.ini';
        $this->config = new Config( $this->configFile );

        // Instantiate remaining classes
        $this->db = new Database( $this->config );
        $this->ListModel = new ListModel( $this->config, $this->db );
    }

    public function testAdd()
    {
        $ListId = $this->ListModel->save( $this->emailAddress, $this->plainPass );

        return $ListId;
    }

    // /**
    //  * @depends testSave
    //  */
    // public function testUpdate( $ListId )
    // {
    //     $this->updatedEmailAddress = 'updated-' . rand( 0, 999999 ) . '@example.com';
    //     $result = $this->ListModel->update( 1, 1, $this->updatedEmailAddress, 1, 1, $ListId, 1 );
    // }
}
