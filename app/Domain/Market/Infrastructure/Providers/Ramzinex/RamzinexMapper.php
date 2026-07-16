<?php

namespace App\Domain\Market\Infrastructure\Providers\Ramzinex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;

class RamzinexMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];
        foreach ($rows as $row) {

            $remoteSymbol = strtolower(
                (string) ($row['tv_symbol']['ramzinex'] ?? '')
            );
            if ($remoteSymbol === '' || ! isset($subscriptions[$remoteSymbol])) {
                continue;
            }

            $financial = $row['financial']['last24h'] ?? [];

            $quotes[] = new QuoteDTO(
                instrument: $subscriptions[$remoteSymbol]->instrument,
                bid: isset($row['buy']) ? (float) $row['buy'] : 0.0,
                ask: isset($row['sell']) ? (float) $row['sell'] : 0.0,
                last: isset($financial['close']) ? (float) $financial['close'] : null,
                provider: $provider,
                volume: isset($financial['base_volume']) ? (float) $financial['base_volume'] : null,
                timestamp: (int) round(microtime(true) * 1000),
                // pair_id belongs to Ramzinex. Aggregation needs our internal
                // provider_markets.id so the quote can be joined to its row.
                providerMarketId: $subscriptions[$remoteSymbol]->providerMarketId,
            );
        }

        return $quotes;
    }
}
