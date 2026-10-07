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
     * @param  array<string, array<string, array<string, mixed>>>  $rows
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $subscriptions, string $provider): array
    {
        $quotes = [];
        $normalizedSubscriptions = [];
        foreach ($subscriptions as $symbol => $subscription) {
            $normalizedSymbol = str_replace(['/', '-', '_', ':'], '', strtoupper(trim($symbol)));
            $normalizedSubscriptions[$normalizedSymbol] = $subscription;
        }

        foreach ($rows as $base => $markets) {
            if (! is_array($markets)) {
                continue;
            }

            foreach ($markets as $quote => $row) {
                $symbol = $base.$quote;
                if (! isset($normalizedSubscriptions[$symbol]) || ! is_array($row)) {
                    continue;
                }

                $price = $row['price'] ?? null;
                if (! is_numeric($price) || ! is_finite((float) $price) || (float) $price <= 0.0) {
                    continue;
                }

                // This feed supplies last prices only, without order-book
                // bid/ask, volume or exchange timestamps. IRT is in toman.
                $quotes[] = $this->quotes->make(
                    subscription: $normalizedSubscriptions[$symbol],
                    bid: (float) $price,
                    ask: (float) $price,
                    last: (float) $price,
                    provider: $provider,
                    volume: null,
                    priceType: 'last_trade',
                    timestamp: now()->getTimestampMs(),
                );
            }
        }

        return $quotes;
    }
}
