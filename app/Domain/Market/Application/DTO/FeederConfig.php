<?php

namespace App\Domain\Market\Application\DTO;

use Illuminate\Support\Arr;

class FeederConfig
{
    public function __construct(
        public string $url,
        public array $config = [],
    ) {}

    public static function generate(string $url, array $config = []): self
    {
        return new self($url, $config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function has(string $key): bool
    {
        return Arr::has($this->config, $key);
    }

    public function toArray(): array
    {
        return [
            'url'    => $this->url,
            'config' => $this->config,
        ];
    }
}
