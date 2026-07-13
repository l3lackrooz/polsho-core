<?php

namespace App\Domain\Market\Infrastructure\Providers\Ompfinex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;

class OmpfinexMapper
{
    /**
     * The exact OMPFinex field names are unverified (see OmpfinexClient),
     * so each value is resolved from a list of likely keys, first hit wins.
     */
    private const FIELD_CANDIDATES = [
        'bid' => ['best_buy', 'best_buy_price', 'bid', 'bid_price'],
        'ask' => ['best_sell', 'best_sell_price', 'ask', 'ask_price'],
        'last' => ['last_price', 'latest_price', 'price', 'last'],
        'volume' => ['base_volume', 'volume_24h', 'volume'],
    ];

    /**
     * Markets are matched by OMPFinex numeric market id, which you store as
     * the remote_symbol in provider_markets (e.g. "11" for BTC/IRT).
     *
     * @param array<int, array<string, mixed>> $rows from /v1/market data[]
     * @param array<string, MarketSubscriptionDTO> $subscriptions keyed by remote symbol (market id as string)
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];

        foreach ($rows as $row) {
            $marketId = (string) ($row['id'] ?? '');

            if ($marketId === '' || !isset($subscriptions[$marketId])) {
                continue;
            }

            $last = $this->firstNumeric($row, self::FIELD_CANDIDATES['last']);
            $bid = $this->firstNumeric($row, self::FIELD_CANDIDATES['bid']) ?? $last;
            $ask = $this->firstNumeric($row, self::FIELD_CANDIDATES['ask']) ?? $last;

            if ($bid === null && $ask === null && $last === null) {
                continue;
            }

            $quotes[] = new QuoteDTO(
                instrument: $subscriptions[$marketId]->instrument,
                bid: $bid ?? 0.0,
                ask: $ask ?? 0.0,
                last: $last,
                provider: $provider,
                volume: $this->firstNumeric($row, self::FIELD_CANDIDATES['volume']),
                timestamp: (int) round(microtime(true) * 1000),
                providerMarketId: $subscriptions[$marketId]->providerMarketId,
            );
        }

        return $quotes;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $keys
     */
    private function firstNumeric(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
