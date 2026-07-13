<?php

namespace App\Domain\Market\Infrastructure\Providers\Ompfinex;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OmpfinexClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    /**
     * GET /v1/market
     *
     * Public market list: {"status":"OK","data":[{ id, base_currency, quote_currency, ... , price fields }]}
     *
     * NOTE: OMPFinex only documents user endpoints publicly and the API is not
     * reachable from outside Iran, so the exact stats field names could not be
     * verified. Run `php artisan market:sync ompfinex --now` once and adjust
     * OmpfinexMapper::FIELD_CANDIDATES if needed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchMarkets(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/v1/market');

        if ($response->failed()) {
            throw new RuntimeException('OMPFinex market request failed: '.$response->body());
        }

        return $response->json('data', []);
    }
}
