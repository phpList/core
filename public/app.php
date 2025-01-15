<?php

declare(strict_types=1);

use PhpList\Core\Core\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::getInstance()
    ->configure()
    ->dispatch();
