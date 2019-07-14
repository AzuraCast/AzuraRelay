<?php
namespace App\Controller\Frontend;

use Azura\Http\Request;
use Azura\Http\Response;
use GuzzleHttp\Psr7\Uri;

class IndexController
{
    public function __invoke(Request $request, Response $response, ?string $path = null): Response
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
