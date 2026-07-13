<?php

namespace App\Domain\Market\Infrastructure\Persistence;

use App\Domain\Market\Application\DTO\QuoteDTO;
use Illuminate\Support\Facades\DB;

class MarketSnapshotWriter
{
    /**
     * @param array<int, QuoteDTO> $quotes
     */
    public function insertMany(array $quotes): void
    {
        $rows = [];
        $now = now();

        foreach ($quotes as $quote) {
            if ($quote->providerMarketId === null) {
                continue;
            }

            $capturedAt = $now->copy()->setTimestamp((int) floor($quote->timestamp / 1000));
            $rows[] = [
                'provider_market_id' => $quote->providerMarketId,
                'dedupe_hash' => hash('sha256', implode('|', [
                    $quote->providerMarketId,
                    $quote->timestamp,
                    $quote->bid,
                    $quote->ask,
                    $quote->last ?? 'null',
                    $quote->volume ?? 'null',
                ])),
                'bid' => $quote->bid,
                'ask' => $quote->ask,
                'last_price' => $quote->last,
                'volume_24h' => $quote->volume,
                'high_24h' => null,
                'low_24h' => null,
                'captured_at' => $capturedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        DB::table('market_snapshots')->insertOrIgnore($rows);
    }
}
