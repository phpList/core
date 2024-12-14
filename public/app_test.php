<?php

declare(strict_types=1);

use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;

require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::getInstance()
    ->unsetHeaders()
    ->ensureDevelopmentOrTestingEnvironment()
    ->setEnvironment(Environment::TESTING)
    ->configure()
    ->dispatch();
