<?php
use App\Controller;

return function(\Azura\App $app)
{
    $app->get('/[{path:.*}]', Controller\Frontend\IndexController::class)
        ->setName('home');
};
