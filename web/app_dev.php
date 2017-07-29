<?php
declare(strict_types=1);

use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;

require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::getInstance()
    ->preventProductionEnvironment()
    ->setEnvironment(Environment::DEVELOPMENT)
    ->configure()
    ->dispatch();
