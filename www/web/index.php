<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 1);

$autoloader = require dirname(__DIR__).'/vendor/autoload.php';

$app = \Azura\AppFactory::create($autoloader, [
    \Azura\Settings::BASE_DIR => dirname(__DIR__),
    \Azura\Settings::IS_DOCKER => true,
    \Azura\Settings::ENABLE_DATABASE => false,
    \Azura\Settings::ENABLE_REDIS => false,
]);

$app->run();
