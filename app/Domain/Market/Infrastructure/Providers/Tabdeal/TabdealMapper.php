<?php

namespace App\Domain\Market\Infrastructure\Providers\Tabdeal;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Support\Utility\ProviderQuoteFactory;

class TabdealMapper
{
    public function __construct(
        private readonly ProviderQuoteFactory $quotes = new ProviderQuoteFactory,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];
        foreach ($rows as $row) {
            $symbol = $row['symbol'];
            if (! isset($subscriptions[$symbol])) {
                continue;
            }

            $quotes[] = $this->quotes->make(
                subscription: $subscriptions[$symbol],
                bid: isset($row['bid']) ? (float) $row['bid'] : 0.0,
                ask: isset($row['ask']) ? (float) $row['ask'] : 0.0,
                last: isset($row['last']) ? (float) $row['last'] : null,
                provider: $provider,
                volume: isset($row['volume']) ? (float) $row['volume'] : null,
                timestamp: now()->getTimestampMs(),
            );
        }

        return $quotes;
    }
}
