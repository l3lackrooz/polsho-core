<?php

namespace App\Domain\Market\Infrastructure\Providers\Tala;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TalaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    /**
     * GET /ajax/price
     *
     * Tala.ir publishes its live board as one snapshot document, grouped by
     * category (`gold`, `sekke`, `arz`, ...). Every price row is an object:
     *
     *   "gold": { "gold_18k": { "m": 1784371725, "v": "18,779,000",
     *             "v_fa": "۱۸,۷۷۹,۰۰۰", "c": "طلاي 18 عيار", ... } }
     *
     * (`/banner` on the same host is the *advertising* endpoint — it serves
     * Yektanet ad slots and never contains prices.)
     *
     * The groups are flattened here so the mapper sees one map keyed by the
     * feed's own row ids (gold_18k, gold_bazartehran, sekke-jad, ...).
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetchPrices(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->get('/ajax/price');

        if ($response->failed()) {
            throw new RuntimeException('Tala price-board request failed: '.$response->body());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        $rows = [];

        foreach ($payload as $group) {
            if (! is_array($group)) {
                continue;
            }

            foreach ($group as $key => $row) {
                // Only price rows carry a `v` value; this skips the `news`
                // and `calendar` groups that share the same document.
                if (is_string($key) && is_array($row) && array_key_exists('v', $row)) {
                    $rows[$key] = $row;
                }
            }
        }

        return $rows;
    }
}
