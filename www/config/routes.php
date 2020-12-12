<?php
use App\Controller;

return function(Slim\App $app)
{
    $app->get('/[{path:.*}]', Controller\Frontend\IndexController::class)
        ->setName('home');
};
