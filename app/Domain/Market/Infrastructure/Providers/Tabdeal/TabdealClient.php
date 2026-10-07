<?php

namespace App\Domain\Market\Infrastructure\Providers\Tabdeal;

use Illuminate\Support\Facades\Http;
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
            ->get('/r/plots/currencies/dynamic-info/');

        if ($response->failed()) {
            throw new RuntimeException('Tabdeal REST request failed: '.$response->body());
        }

        $currencies = $response->json('currencies');

        if (! is_array($currencies)) {
            throw new RuntimeException('Tabdeal REST response is missing the currencies snapshot.');
        }

        return $currencies;
    }

    public function fetchTicker(string $symbol): array
    {
        return $this->fetchTickers([$symbol]);
    }
}
