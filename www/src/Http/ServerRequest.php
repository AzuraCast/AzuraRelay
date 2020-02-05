<?php
namespace App\Http;

use App\Exception;

class ServerRequest extends \Slim\Http\ServerRequest
{
    public const ATTR_ROUTER = 'app_router';

    /**
     * @return RouterInterface
     * @throws Exception
     */
    public function getRouter(): RouterInterface
    {
        return $this->getAttributeOfClass(self::ATTR_ROUTER, RouterInterface::class);
    }

    /**
     * Get the remote user's IP address as indicated by HTTP headers.
     * @return string|null
     */
    public function getIp(): ?string
    {
        $params = $this->serverRequest->getServerParams();

        return $params['HTTP_CLIENT_IP']
            ?? $params['HTTP_X_FORWARDED_FOR']
            ?? $params['HTTP_X_FORWARDED']
            ?? $params['HTTP_FORWARDED_FOR']
            ?? $params['HTTP_FORWARDED']
            ?? $params['REMOTE_ADDR']
            ?? null;
    }

    /**
     * @param string $attr
     * @param string $class_name
     *
     * @return mixed
     * @throws Exception
     */
    protected function getAttributeOfClass($attr, $class_name)
    {
        $object = $this->serverRequest->getAttribute($attr);
        if ($object instanceof $class_name) {
            return $object;
        }

        throw new Exception(sprintf('Attribute %s must be of type %s.', $attr, $class_name));
    }
}
