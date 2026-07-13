<?php

namespace App\Domain\Market\Infrastructure\Providers\Nobitex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;

class NobitexMapper
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, MarketSubscriptionDTO> $subscriptions
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];
        foreach ($rows as $symbol => $row) {

            $symbol = (string) $symbol;

            if ($symbol === '' || !isset($subscriptions[$symbol])) {
                continue;
            }

            $quotes[] = new QuoteDTO(
                instrument: $subscriptions[$symbol]->instrument,
                bid: isset($row['bestBuy']) ? (float) $row['bestBuy'] : 0.0,
                ask: isset($row['bestSell']) ? (float) $row['bestSell'] : 0.0,
                last: isset($row['latest']) ? (float) $row['latest'] : null,
                provider: $provider,
                volume: isset($row['volumeSrc']) ? (float) $row['volumeSrc'] : null,
                timestamp: (int) round(microtime(true) * 1000),
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
