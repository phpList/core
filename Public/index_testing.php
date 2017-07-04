<?php
declare(strict_types=1);

use PhpList\PhpList4\Core\Bootstrap;

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::getInstance()
    ->preventProductionEnvironment()
    ->setApplicationContext(Bootstrap::APPLICATION_CONTEXT_TESTING)
    ->configure()
    ->dispatch();
