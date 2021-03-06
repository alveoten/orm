#!/usr/bin/php
<?php

use Tabusoft\ORM\Configurator\CreateConfig;

define("APP_DIR", realpath( getcwd() ) );

chdir(APP_DIR);

// Composer autoloading
require 'vendor/autoload.php';

$config = new CreateConfig();

$config->main();
