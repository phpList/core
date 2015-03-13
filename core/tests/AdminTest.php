<?php
namespace phpList\test;

use phpList\Config;
use phpList\EmailUtil;
use phpList\helper\Database;
use phpList\Pass;
use phpList\phpList;
use phpList\SubscriberManager;
use phpList\helper\Util;
use phpList\Model\SubscriberModel;
use phpList\Entity\SubscriberEntity;

// Symfony namespaces
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AdminTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Create Symfony DI service container object for use by other classes
        $this->container = new ContainerBuilder();
        // Create new Symfony file loader to handle the YAML service config file
        $loader = new YamlFileLoader( $this->container, new FileLocator(__DIR__) );
        // Load the service config file, which is in YAML format
        $loader->load( '../services.yml' );
        // Set necessary config class parameter
        $this->container->setParameter( 'config.configfile', '/var/www/pl4/config.ini' );
        // Get objects from container
        $this->config = $this->container->get( 'Config' );

        $this->admin = $this->container->get( 'Admin' );
    }

    public function testValidateLogin()
    {
        // Set correct login vars for this installation, from phpunit.xml
        $username = $GLOBALS['admin_username'];
        $plainPass = $GLOBALS['admin_password'];

        // Perform the validation
        $result = $this->admin->validateLogin( $plainPass, $username );

        // Test valiation was successful
        $this->assertTrue( $result['result'] );
        // Check details are accurate
        $this->assertEquals( $username, $result['admin']->username );
        // Check that retrieved pass is hashed
        $this->assertNotEquals( $plainPass, $result['admin']->encPass );
    }
}
