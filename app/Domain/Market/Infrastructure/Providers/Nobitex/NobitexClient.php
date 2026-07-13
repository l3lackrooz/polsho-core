<?php

namespace App\Domain\Market\Infrastructure\Providers\Nobitex;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NobitexClient
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
            ->get('/market/stats');

        if ($response->failed()) {
            throw new RuntimeException('Sample exchange REST request failed: '.$response->body());
        }

        return $response->json('stats', []);


    }
}
