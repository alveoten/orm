#!/usr/bin/php
<?php

use Tabusoft\ORM\Configurator\Configurator;

define("APP_DIR", realpath( getcwd() ) );

chdir(APP_DIR);

// Composer autoloading
require 'vendor/autoload.php';

$configurator = new Configurator();
$configurator->main();

exit;
