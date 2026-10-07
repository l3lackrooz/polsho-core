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

    /** @return array<int, array<string, mixed>> */
    public function fetchMarkets(): array
    {
        return $this->fetch('/v1/market', true);
    }

    /** @return array<string, array<string, mixed>> */
    public function fetchOrderBooks(): array
    {
        return $this->fetch('/v1/orderbook', false);
    }

    private function fetch(string $path, bool $list): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get($path);

        if ($response->failed()) {
            throw new RuntimeException('OMPFinex request failed for '.$path.': HTTP '.$response->status());
        }

        $data = $response->json('data');
        if ($response->json('status') !== 'OK' || ! is_array($data)
            || ($data !== [] && array_is_list($data) !== $list)) {
            throw new RuntimeException('OMPFinex returned an invalid response for '.$path);
        }

        return $data;
    }
}
