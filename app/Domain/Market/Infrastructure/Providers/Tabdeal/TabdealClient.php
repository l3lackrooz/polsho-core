<?php

namespace App\Domain\Market\Infrastructure\Providers\Tabdeal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TabdealClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    public function fetchTickers(array $symbols): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/exchangeInfo');

        if ($response->failed()) {
            throw new RuntimeException('Tabdeal REST request failed: '.$response->body());
        }
        return $response->json();
    }

    public function fetchTicker(string $symbol): array
    {
        return $this->fetchTickers([$symbol]);
    }
}
