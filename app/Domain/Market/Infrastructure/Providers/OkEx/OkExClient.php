<?php

namespace App\Domain\Market\Infrastructure\Providers\OkEx;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OkExClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchTickers(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/api/v1/spot/public/tickers');

        if ($response->failed()) {
            throw new RuntimeException('OK-EX tickers request failed: '.$response->body());
        }

        return $response->json('tickers', []);
    }

    /**
     * @return array{asks?: array<int, array<int, float|int|string>>, bids?: array<int, array<int, float|int|string>>}
     */
    public function fetchOrderBook(string $symbol): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/api/v1/spot/public/books', [
                'symbol' => $symbol,
                'limit' => 1,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OK-EX order book request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
