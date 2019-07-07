<?php
namespace App\Provider;

use App\Controller\Frontend;
use Doctrine\ORM\EntityManager;
use Pimple\ServiceProviderInterface;
use Pimple\Container;

class FrontendProvider implements ServiceProviderInterface
{
    public function register(Container $di)
    {
        $di[Frontend\IndexController::class] = function($di) {
            return new Frontend\IndexController();
        };
    }
}
