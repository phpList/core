<?php
namespace phpList;
include('core\phpList.php');




//Handle some dynamicly generated include files
if (isset($_SERVER['ConfigFile']) && is_file($_SERVER['ConfigFile'])) {
    $configfile = $_SERVER['ConfigFile'];
} elseif (isset($cline['c']) && is_file($cline['c'])) {
    $configfile = $cline['c'];
} else {
    $configfile = __DIR__ . '/UserConfig.php';
}

phpList::initialise($configfile);