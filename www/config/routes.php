<?php

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;

return function (Slim\App $app) {
    $app->get(
        '/[{path:.*}]',
        function (
            Slim\Http\ServerRequest $request,
            Slim\Http\Response $response,
            ?string $path = null
        ): ResponseInterface {
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
