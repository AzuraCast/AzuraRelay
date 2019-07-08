<?php
namespace App\Service;

use GuzzleHttp\Client;
use Monolog\Logger;

class AzuraCast
{
    public const ENV_BASE_URL = 'AZURACAST_BASE_URL';
    public const ENV_API_KEY = 'AZURACAST_API_KEY';
    public const ENV_STATIONS = 'AZURACAST_STATIONS';

    /** @var Client */
    protected $http_client;

    /** @var Logger */
    protected $logger;

    /**
     * @param Client $http_client
     * @param Logger $logger
     */
    public function __construct(Client $http_client, Logger $logger)
    {
        $this->http_client = $http_client;
        $this->logger = $logger;
    }

    public function testConnection(
        $base_url = null
    ): bool {

    }

    public function getNowPlaying(
        $base_url = null,
        $api_key = null,
        $stations = null
    ): array {



    }

}
