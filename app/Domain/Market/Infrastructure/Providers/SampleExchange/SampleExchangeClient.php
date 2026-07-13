<?php

namespace App\Domain\Market\Infrastructure\Providers\SampleExchange;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SampleExchangeClient
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
            ->get('/ticker', [
                'symbols' => implode(',', $symbols),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Sample exchange REST request failed: '.$response->body());
        }

        return $response->json('data', []);
    }
}
