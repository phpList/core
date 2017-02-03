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

class AdminTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        // Create Symfony DI service container object for use by other classes
        $this->container = new ContainerBuilder();
        // Create new Symfony file loader to handle the YAML service config file
        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
        // Load the service config file, which is in YAML format
        $loader->load('../services.yml');
        // Get objects from container
        $this->container->setParameter('config.configfile', __DIR__ . '/../../config.ini.dist');
        $this->config = $this->container->get('Config');

        $this->admin = $this->container->get('Admin');
    }

    /**
     * Check that we our credentials are validated, as they should be
     */
    public function testValidLogin()
    {
        // Set correct login vars for this installation, from phpunit.xml
        $username = $GLOBALS['admin_username'];
        $plainPass = $GLOBALS['admin_password'];

        // Perform the validation
        $result = $this->admin->validateLogin($plainPass, $username);

        // Test valiation was successful
        $this->assertTrue($result['result']);

        // Return vars for dependent tests
        return array(
            'plainPass' => $plainPass
            , 'encPass' => $result['admin']->encPass
            , 'username' => $username
            , 'retrievedUsername' => $result['admin']->username
        );
    }

    /**
    * Check that incorrect credentials do not validate
    */
    public function testInvalidLogin()
    {
        // Set correct login vars for this installation, from phpunit.xml
        $username = 'foo';
        $plainPass = 'bar';

        // Perform the validation
        $result = $this->admin->validateLogin($plainPass, $username);

        // Test valiation failed
        $this->assertFalse($result['result']);
    }

    /**
     * Test that the username we provided is the username actually used to
     * validate the login
     * @depends testValidLogin
     */
    public function testAdminUsernameValidation($vars)
    {
        // Check the retreived details match those submitted
        $this->assertEquals($vars['username'], $vars['retrievedUsername']);
    }

    /**
     * Test that the password retreived was not in plantext
     * @depends testValidLogin
     */
    public function testAdminPasswordHashed($vars)
    {
        // Check that retrieved pass is hashed
        $this->assertNotEquals($vars['plainPass'], $vars['encPass']);
    }
}
