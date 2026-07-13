<?php

namespace App\Domain\Market\Infrastructure\Providers\Bitpin;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitpinClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    /**
     * GET /api/v1/mkt/tickers/
     *
     * Returns a list of tickers: [{symbol: "BTC_IRT", price, daily_change_price, low, high, timestamp}, ...]
     * Note: Bitpin tickers carry only a last price — bid/ask come from the orderbook endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchTickers(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/api/v1/mkt/tickers/');

        if ($response->failed()) {
            throw new RuntimeException('Bitpin tickers request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * GET /api/v1/mkt/orderbook/{symbol}/
     *
     * Returns {asks: [[price, qty], ...], bids: [[price, qty], ...]}.
     *
     * @return array{asks?: array<int, array<int, string>>, bids?: array<int, array<int, string>>}
     */
    public function fetchOrderBook(string $symbol): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get(sprintf('/api/v1/mkt/orderbook/%s/', $symbol));

        if ($response->failed()) {
            throw new RuntimeException('Bitpin orderbook request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
