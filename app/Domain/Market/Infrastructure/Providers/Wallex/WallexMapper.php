<?php

namespace App\Domain\Market\Infrastructure\Providers\Wallex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Support\Utility\ProviderQuoteFactory;

class WallexMapper
{
    public function __construct(
        private readonly ProviderQuoteFactory $quotes = new ProviderQuoteFactory,
    ) {}

    /**
     * Wallex semantics: bidPrice = highest buy order, askPrice = lowest sell order.
     *
     * @param  array<string, array<string, mixed>>  $rows  keyed by remote symbol
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions  keyed by remote symbol
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];

        foreach ($rows as $symbol => $row) {
            $symbol = (string) $symbol;

            if ($symbol === '' || ! isset($subscriptions[$symbol])) {
                continue;
            }

            $stats = is_array($row['stats'] ?? null) ? $row['stats'] : [];

            $quotes[] = $this->quotes->make(
                subscription: $subscriptions[$symbol],
                bid: isset($stats['bidPrice']) ? (float) $stats['bidPrice'] : 0.0,
                ask: isset($stats['askPrice']) ? (float) $stats['askPrice'] : 0.0,
                last: isset($stats['lastPrice']) ? (float) $stats['lastPrice'] : null,
                provider: $provider,
                volume: isset($stats['24h_volume']) ? (float) $stats['24h_volume'] : null,
                timestamp: (int) round(microtime(true) * 1000),
            );
        }

        return $quotes;
    }
}
