<?php

declare(strict_types=1);

use PhpList\Core\Core\Environment;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->loadEnv(dirname(__DIR__) . '/.env', 'APP_ENV', Environment::TESTING);
