<?php
use App\Controller;

return function(\App\App $app)
{
    $app->get('/[{path:.*}]', Controller\Frontend\IndexController::class)
        ->setName('home');
};
