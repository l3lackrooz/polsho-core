<?php

namespace App\Domain\Market\Infrastructure\Providers\Wallex;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WallexClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    /**
     * GET /v1/markets
     *
     * Returns a map keyed by remote symbol (BTCUSDT, USDTTMN, ...):
     * result.symbols.{SYMBOL}.stats.{bidPrice, askPrice, lastPrice, 24h_volume}
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetchMarkets(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/v1/markets');

        if ($response->failed()) {
            throw new RuntimeException('Wallex markets request failed: '.$response->body());
        }

        return $response->json('result.symbols', []);
    }
}
