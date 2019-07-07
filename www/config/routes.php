<?php
use App\Controller;
use App\Middleware;

return function(\Azura\App $app)
{
    $app->get('/', Controller\Frontend\IndexController::class)
        ->setName('home');
};
