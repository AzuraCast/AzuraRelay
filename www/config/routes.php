<?php

use App\Http\Response;
use App\Http\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;

return function (Slim\App $app) {
    $app->get(
        '/[{path:.*}]',
        function (ServerRequest $request, Response $response, ?string $path = null): ResponseInterface {
            $baseUrl = getenv('AZURACAST_BASE_URL');

            if (!empty($baseUrl)) {
                $uri = new Uri($baseUrl);
                $uri = $uri->withPath('/' . $path);

                return $response->withRedirect((string)$uri);
            }

            return $response->withJson(
                [
                    'success' => 'Hello, world!',
                ]
            );
        }
    )->setName('home');
};
