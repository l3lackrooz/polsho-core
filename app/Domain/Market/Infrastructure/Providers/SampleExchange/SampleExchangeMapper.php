<?php

namespace App\Domain\Market\Infrastructure\Providers\SampleExchange;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;

class SampleExchangeMapper
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, MarketSubscriptionDTO> $subscriptions
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];

        foreach ($rows as $row) {
            $symbol = (string) ($row['symbol'] ?? '');
            if ($symbol === '' || !isset($subscriptions[$symbol])) {
                continue;
            }

            $quotes[] = new QuoteDTO(
                instrument: $subscriptions[$symbol]->instrument,
                bid: (float) ($row['bid'] ?? 0),
                ask: (float) ($row['ask'] ?? 0),
                last: isset($row['last']) ? (float) $row['last'] : null,
                provider: $provider,
                volume: isset($row['volume']) ? (float) $row['volume'] : null,
                timestamp: (int) (($row['timestamp'] ?? round(microtime(true) * 1000))),
                providerMarketId: $subscriptions[$symbol]->providerMarketId,
            );
        }

        return $quotes;
    }

    public function mapStream(array $payload, MarketSubscriptionDTO $subscription, string $provider): QuoteDTO
    {
        return new QuoteDTO(
            instrument: $subscription->instrument,
            bid: (float) ($payload['bid'] ?? 0),
            ask: (float) ($payload['ask'] ?? 0),
            last: isset($payload['last']) ? (float) $payload['last'] : null,
            provider: $provider,
            volume: isset($payload['volume']) ? (float) $payload['volume'] : null,
            timestamp: (int) (($payload['timestamp'] ?? round(microtime(true) * 1000))),
            providerMarketId: $subscription->providerMarketId,
        );
    }
}
