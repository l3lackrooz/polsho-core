<?php

namespace App\Domain\Market\Infrastructure\Providers\Ramzinex;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RamzinexClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    public function fetchPairs(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/exchange/api/v1.0/exchange/pairs');

        if ($response->failed()) {
            throw new RuntimeException(
                'Ramzinex pairs request failed: ' . $response->body()
            );
        }

        return $response->json('data', []);
    }
}
