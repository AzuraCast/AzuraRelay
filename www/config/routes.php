<?php
use App\Controller;
use App\Middleware;

return function(\Azura\App $app)
{
    $app->get('/[{path:.*}]', Controller\Frontend\IndexController::class)
        ->setName('home');
};
