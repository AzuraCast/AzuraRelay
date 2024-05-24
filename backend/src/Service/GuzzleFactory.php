<?php

namespace App\Service;

use GuzzleHttp\Client;

final class GuzzleFactory
{
    public function __construct(
        protected array $defaultConfig = []
    ) {
    }

    public function withConfig(array $defaultConfig): self
    {
        return new self($defaultConfig);
    }

    public function withAddedConfig(array $config): self
    {
        return new self(array_merge($this->defaultConfig, $config));
    }

    public function getDefaultConfig(): array
    {
        return $this->defaultConfig;
    }

    public function buildClient(array $config = []): Client
    {
        return new Client(array_merge($this->defaultConfig, $config));
    }
}
