<?php

namespace App\Domain\Market\Infrastructure\Providers\OkEx;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Support\Utility\ProviderQuoteFactory;

class OkExMapper
{
    public function __construct(
        private readonly ProviderQuoteFactory $quotes = new ProviderQuoteFactory,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $tickers
     * @param  array<string, array<string, mixed>>  $orderBooks
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $tickers, array $orderBooks, array $subscriptions, string $provider): array
    {
        $quotes = [];

        foreach ($tickers as $ticker) {
            $symbol = (string) ($ticker['symbol'] ?? '');

            if ($symbol === '' || ! isset($subscriptions[$symbol])) {
                continue;
            }

            $book = $orderBooks[$symbol] ?? [];
            $last = isset($ticker['lastPrice']) ? (float) $ticker['lastPrice'] : null;
            $bestBid = isset($book['bids'][0][0]) ? (float) $book['bids'][0][0] : 0.0;
            $bestAsk = isset($book['asks'][0][0]) ? (float) $book['asks'][0][0] : 0.0;

            $quotes[] = $this->quotes->make(
                subscription: $subscriptions[$symbol],
                bid: $bestBid > 0.0 ? $bestBid : ($last ?? 0.0),
                ask: $bestAsk > 0.0 ? $bestAsk : ($last ?? 0.0),
                last: $last,
                provider: $provider,
                volume: isset($ticker['volume']) ? (float) $ticker['volume'] : null,
                timestamp: isset($ticker['updateTime'])
                    ? (int) $ticker['updateTime']
                    : (int) round(microtime(true) * 1000),
            );
        }

        return $quotes;
    }
}
