<?php

declare(strict_types=1);

use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;

require dirname(__DIR__) . '/vendor/autoload.php';

$environment = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: Environment::PRODUCTION;

$bootstrap = Bootstrap::getInstance();
if ($environment !== Environment::PRODUCTION) {
    $bootstrap->ensureDevelopmentOrTestingEnvironment();
}

$bootstrap
    ->setEnvironment($environment)
    ->configure()
    ->dispatch();
