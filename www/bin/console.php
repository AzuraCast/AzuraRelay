<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', 1);

$autoloader = require dirname(__DIR__).'/vendor/autoload.php';

$app = \Azura\App::create([
    'autoloader' => $autoloader,
    'settings' => [
        \Azura\Settings::BASE_DIR => dirname(__DIR__),
        \Azura\Settings::IS_DOCKER => true,
        \Azura\Settings::ENABLE_DATABASE => false,
        \Azura\Settings::ENABLE_REDIS => false,
    ],
]);

$di = $app->getContainer();

/** @var \Azura\Settings $settings */
$settings = $di[\Azura\Settings::class];

$helperSet = new \Symfony\Component\Console\Helper\HelperSet();
$helperSet->set(new \Symfony\Component\Console\Helper\QuestionHelper, 'dialog');

$cli = new \Azura\Console\Application('AzuraRelay CLI ('.$settings[\Azura\Settings::APP_ENV].')');
$cli->setContainer($di);
$cli->setHelperSet($helperSet);

$cli->run();
