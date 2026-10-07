<?php

namespace App\Domain\Market\Infrastructure\Providers\Bitpin;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Support\Utility\ProviderQuoteFactory;

class BitpinMapper
{
    public function __construct(
        private readonly ProviderQuoteFactory $quotes = new ProviderQuoteFactory,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $tickers  list from /mkt/tickers/
     * @param  array<string, array<string, mixed>>  $orderBooks  keyed by remote symbol, from /mth/orderbook/{symbol}/
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions  keyed by remote symbol (BTC_IRT, ...)
     * @param  array<string, int>  $orderBookTimestamps  local fetch times in milliseconds
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $tickers, array $orderBooks, array $subscriptions, string $provider, array $orderBookTimestamps = []): array
    {
        $quotes = [];

        foreach ($tickers as $row) {
            $symbol = (string) ($row['symbol'] ?? '');

            if ($symbol === '' || ! isset($subscriptions[$symbol])) {
                continue;
            }

            $book = $orderBooks[$symbol] ?? [];
            // Orderbook rows are [price, quantity] with best price first.
            $bestBid = is_numeric($book['bids'][0][0] ?? null) ? (float) $book['bids'][0][0] : 0.0;
            $bestAsk = is_numeric($book['asks'][0][0] ?? null) ? (float) $book['asks'][0][0] : 0.0;

            // A failed or empty book must not turn a last trade into a live
            // two-sided quote. Other subscribed markets can still be updated.
            if (! is_finite($bestBid) || ! is_finite($bestAsk) || $bestBid <= 0 || $bestAsk <= $bestBid) {
                continue;
            }

            $last = is_numeric($row['price'] ?? null) ? (float) $row['price'] : null;
            if ($last !== null && (! is_finite($last) || $last <= 0)) {
                $last = null;
            }

            $quotes[] = $this->quotes->make(
                subscription: $subscriptions[$symbol],
                bid: $bestBid,
                ask: $bestAsk,
                last: $last,
                provider: $provider,
                volume: null,
                // The ticker timestamp is the last trade time, which can be
                // old even when the freshly fetched order book is current.
                timestamp: $orderBookTimestamps[$symbol] ?? now()->getTimestampMs(),
            );
        }

        return $quotes;
    }
}
