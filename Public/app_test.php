<?php
declare(strict_types=1);

use PhpList\PhpList4\Core\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::getInstance()
    ->preventProductionEnvironment()
    ->setApplicationContext(Bootstrap::APPLICATION_CONTEXT_TESTING)
    ->configure()
    ->dispatch();
