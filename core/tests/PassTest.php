<?php
namespace phpList\test;

use phpList\Pass;

// Symfony namespaces
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class PassTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        // Create Symfony DI service container object for use by other classes
        $this->container = new ContainerBuilder();
        // Create new Symfony file loader to handle the YAML service config file
        $loader = new YamlFileLoader( $this->container, new FileLocator(__DIR__) );
        // Load the service config file, which is in YAML format
        $loader->load( '../services.yml' );
        // Get objects from container
        $this->pass = $this->container->get( 'Pass' );
        // Set default pwd for testing
        $this->plainPass = 'easypassword';
    }

    public function testEncrypt()
    {
        $encPass = $this->pass->encrypt( $this->plainPass );
        // Check the result is of hash length
        $this->assertGreaterThan( 30, strlen( $encPass ) );
        // Check result is changed from original
        $this->assertNotEquals( $this->plainPass, $encPass );
    }
}
