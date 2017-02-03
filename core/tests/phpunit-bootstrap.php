<?php

// Include Composer autoloader
require_once('../vendor/autoload.php');

// Set timezone to avoid warnings
// FIXME: Remove this and handle elsewhere, reflecting the server's locale
date_default_timezone_set('UTC');

// Include config file as it includes necessary config vars
if (isset($_SERVER['ConfigFile'])
    && is_file($_SERVER['ConfigFile'])
) {
    require_once($_SERVER['ConfigFile']);
}
