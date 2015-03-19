<?php

// Include Symfony autoloader
require_once( '../vendor/autoload.php' );

// TODO: Include setup file for dummy globals etc.
$GLOBALS['usertable_prefix'] = 'phplist_';
$GLOBALS['database_host'] = 'localhost';
$GLOBALS['database_user'] = 'pl';
$GLOBALS['database_password'] = 'pl';
$GLOBALS['database_name'] = 'pl';


// Include config file as it includes necessary config vars
if (
    isset( $_SERVER['ConfigFile'] )
    && is_file( $_SERVER['ConfigFile'] )
) {
    require_once( $_SERVER['ConfigFile'] );
}
