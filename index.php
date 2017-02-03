<?php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

require_once 'vendor/autoload.php';

// Create Symfony DI service container object for use by other classes
$container = new ContainerBuilder();
// Create new Symfony file loader to handle the YAML service config file
$loader = new YamlFileLoader($container, new FileLocator(__DIR__));
// Load the service config file, which is in YAML format
$loader->load('core/services.yml');

//Handle some dynamicly generated include files
if (isset($_SERVER['ConfigFile']) && is_file($_SERVER['ConfigFile'])) {
    $configfile = $_SERVER['ConfigFile'];
} elseif (isset($cline['c']) && is_file($cline['c'])) {
    $configfile = $cline['c'];
} else {
    $configfile = __DIR__ . '/config.ini';
}

// Set service parameters for the configuration file
// These service parameters will be used as constructor arguments for config{}
$container->setParameter('config.configfile', $configfile);

//load phpList core
$phpList = $container->get('phpList');
