<?php
namespace App\Controller\Frontend;

use Azura\Http\Request;
use Azura\Http\Response;

class IndexController
{
    public function __invoke(Request $request, Response $response): Response
    {
        return $response->withJson([
            'success' => 'Hello, world!',
        ]);
    }
}