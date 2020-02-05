<?php
namespace App\Http;

use App\Exception;
use App\Settings;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;

class Router implements RouterInterface
{
    /** @var RouteParserInterface */
    protected $route_parser;

    /** @var Settings */
    protected $settings;

    /** @var ServerRequestInterface */
    protected $current_request;

    /**
     * @param Settings $settings
     * @param RouteParserInterface $route_parser
     */
    public function __construct(Settings $settings, RouteParserInterface $route_parser)
    {
        $this->settings = $settings;
        $this->route_parser = $route_parser;
    }

    /**
     * Compose a URL, returning an absolute URL (including base URL) if the current settings or this function's parameters
     * indicate an absolute URL is necessary
     *
     * @param UriInterface $base
     * @param UriInterface|string $rel
     * @param bool $absolute
     *
     * @return UriInterface
     */
    public static function resolveUri(UriInterface $base, $rel, bool $absolute = false): UriInterface
    {
        if (!$rel instanceof UriInterface) {
            $rel = new Uri($rel);
        }

        if (!$absolute) {
            return $rel;
        }

        // URI has an authority solely because of its port.
        if ($rel->getAuthority() !== '' && $rel->getHost() === '' && $rel->getPort()) {
            // Strip the authority from the URI, then reapply the port after the merge.
            $original_port = $rel->getPort();

            $new_uri = UriResolver::resolve($base, $rel->withScheme('')->withHost('')->withPort(null));
            return $new_uri->withPort($original_port);
        }

        return UriResolver::resolve($base, $rel);
    }

    /**
     * @return ServerRequestInterface
     */
    public function getCurrentRequest(): ServerRequestInterface
    {
        return $this->current_request;
    }

    /**
     * @param ServerRequestInterface $current_request
     */
    public function setCurrentRequest(ServerRequestInterface $current_request): void
    {
        $this->current_request = $current_request;
    }

    /**
     * Same as $this->fromHere(), but merging the current GET query parameters into the request as well.
     *
     * @param null $route_name
     * @param array $route_params
     * @param array $query_params
     * @param bool $absolute
     *
     * @return string
     */
    public function fromHereWithQuery(
        $route_name = null,
        array $route_params = [],
        array $query_params = [],
        $absolute = false
    ): string {
        if ($this->current_request instanceof ServerRequestInterface) {
            $query_params = array_merge($this->current_request->getQueryParams(), $query_params);
        }

        return $this->fromHere($route_name, $route_params, $query_params, $absolute);
    }

    /**
     * Return a named route based on the current page and its route arguments.
     *
     * @param null $route_name
     * @param array $route_params
     * @param array $query_params
     * @param bool $absolute
     *
     * @return string
     */
    public function fromHere(
        $route_name = null,
        array $route_params = [],
        array $query_params = [],
        $absolute = false
    ): string {
        if ($this->current_request instanceof ServerRequestInterface) {
            $routeContext = RouteContext::fromRequest($this->current_request);
            $route = $routeContext->getRoute();
        } else {
            $route = null;
        }

        if ($route_name === null) {
            if ($route instanceof RouteInterface) {
                $route_name = $route->getName();
            } else {
                throw new InvalidArgumentException('Cannot specify a null route name if no existing route is configured.');
            }
        }

        if ($route instanceof RouteInterface) {
            $route_params = array_merge($route->getArguments(), $route_params);
        }

        return $this->named($route_name, $route_params, $query_params, $absolute);
    }

    /**
     * Simpler format for calling "named" routes with parameters.
     *
     * @param string $route_name
     * @param array $route_params
     * @param array $query_params
     * @param boolean $absolute Whether to include the full URL.
     *
     * @return UriInterface
     */
    public function named($route_name, $route_params = [], array $query_params = [], $absolute = false): UriInterface
    {
        return self::resolveUri($this->getBaseUrl(),
            $this->route_parser->relativeUrlFor($route_name, $route_params, $query_params), $absolute);
    }

    /**
     * Dynamically calculate the base URL the first time it's called, if it is at all in the request.
     *
     * @param bool $use_request Use the current request for the base URI, if available.
     *
     * @return UriInterface
     */
    public function getBaseUrl(bool $use_request = true): UriInterface
    {
        static $base_url;

        // Check the settings for a hard-coded base URI.
        if (!$base_url && !empty($this->settings[Settings::BASE_URL])) {
            $base_url = new Uri($this->settings[Settings::BASE_URL]);
        }

        // Use the current request's URI if applicable.
        if ($use_request && $this->current_request instanceof ServerRequestInterface) {
            $current_uri = $this->current_request->getUri();

            $ignored_hosts = ['nginx', 'localhost'];
            if (!in_array($current_uri->getHost(), $ignored_hosts)) {
                return (new Uri())
                    ->withScheme($current_uri->getScheme())
                    ->withHost($current_uri->getHost())
                    ->withPort($current_uri->getPort());
            }
        }

        if (!$base_url instanceof Uri) {
            throw new Exception('Base URL could not be determined.');
        }

        return $base_url;
    }
}
