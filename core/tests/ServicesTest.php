<?php
namespace phpList\test;

use phpList\Config;
use phpList\EmailAddress;
use phpList\entities\SubscriberEntity;
use phpList\helper\Database;
use phpList\Password;
use phpList\phpList;
use phpList\Subscriber;
use phpList\helper\Util;

// Symfony namespaces
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ServicesTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Create Symfony DI service container object for use by other classes
        $this->container = new ContainerBuilder();
        // Create new Symfony file loader to handle the YAML service config file
        $loader = new YamlFileLoader( $this->container, new FileLocator(__DIR__) );
        // Load the service config file, which is in YAML format
        $loader->load( '../services.yml' );
    }

    public function testConfigService()
    {
        // Set service parameters
        $this->container->setParameter( 'config.configfile', '/var/www/pl4/config.ini' );

        $config = $this->container->get( 'Config' );
    }
    public function testPhplistService()
    {
        // Set service parameters
        $this->container->setParameter( 'config.configfile', '/var/www/pl4/config.ini' );
        
        $config = $this->container->get( 'phpList' );
    }
}
