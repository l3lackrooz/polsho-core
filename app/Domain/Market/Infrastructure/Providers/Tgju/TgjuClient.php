<?php

namespace App\Domain\Market\Infrastructure\Providers\Tgju;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TgjuClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $rev = '',
        private readonly int $timeout = 10,
    ) {}

    /**
     * GET /ajax.json?rev={rev}
     *
     * TGJU publishes one snapshot document for every indicator it tracks.
     * The `current` key maps remote symbols (price_dollar_rl, mesghal, ...)
     * to {p: price, h: high, l: low, d: delta, dp: delta %, ts: datetime}.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetchCurrent(): array
    {
        $query = $this->rev === '' ? [] : ['rev' => $this->rev];

        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/ajax.json', $query);

        if ($response->failed()) {
            throw new RuntimeException('TGJU snapshot request failed: '.$response->body());
        }

        $current = $response->json('current', []);

        return is_array($current) ? $current : [];
    }
}
