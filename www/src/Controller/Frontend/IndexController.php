<?php
namespace App\Controller\Frontend;

use Azura\Http\Response;
use Azura\Http\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;

class IndexController
{
    public function __invoke(ServerRequest $request, Response $response, ?string $path = null): ResponseInterface
    {
        $baseUrl = getenv('AZURACAST_BASE_URL');

        if (!empty($baseUrl)) {
            $uri = new Uri($baseUrl);
            $uri = $uri->withPath('/'.$path);

            return $response->withRedirect((string)$uri);
        }

        return $response->withJson([
            'success' => 'Hello, world!',
        ]);
    }
}
