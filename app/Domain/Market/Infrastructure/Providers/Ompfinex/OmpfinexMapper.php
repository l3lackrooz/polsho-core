<?php

namespace App\Domain\Market\Infrastructure\Providers\Ompfinex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Support\Utility\ProviderQuoteFactory;

class OmpfinexMapper
{
    public function __construct(
        private readonly ProviderQuoteFactory $quotes = new ProviderQuoteFactory,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  /v1/market data, with numeric IDs
     * @param  array<string, array<string, mixed>>  $orderBooks  /v1/orderbook data, keyed by BTCIRR etc.
     * @param  array<string, MarketSubscriptionDTO>  $subscriptions  keyed by numeric remote market ID
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $rows, array $orderBooks, array $subscriptions, string $provider): array
    {
        $quotes = [];
        $timestamp = now()->getTimestampMs();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $marketId = (string) ($row['id'] ?? '');
            if (! isset($subscriptions[$marketId])) {
                continue;
            }

            $subscription = $subscriptions[$marketId];
            $base = strtoupper((string) data_get($row, 'base_currency.id', ''));
            $quote = strtoupper((string) data_get($row, 'quote_currency.id', ''));
            // Reject a market ID accidentally assigned to a different pair.
            $sameQuote = $quote === $subscription->quote
                || (in_array($quote, ['IRR', 'IRT'], true) && in_array($subscription->quote, ['IRR', 'IRT'], true));
            if ($base !== $subscription->base || ! $sameQuote || ($row['is_visible'] ?? true) === false) {
                continue;
            }

            $book = $orderBooks[$base.$quote] ?? [];
            // OMPFinex explicitly defines asks as BUY orders and bids as SELL
            // orders, opposite the usual names: https://docs.ompfinex.com/.
            $bid = $this->bestPrice($book['asks'] ?? [], true);
            $ask = $this->bestPrice($book['bids'] ?? [], false);
            if ($bid === null || $ask === null || $ask <= $bid) {
                continue;
            }

            // Numeric remote IDs do not encode a currency. The API's IRR
            // amounts are rials, even though its display name says Toman.
            $pricedSubscription = clone $subscription;
            $pricedSubscription->metadata = [
                ...$subscription->metadata,
                'source_base' => $base,
                'source_quote' => $quote,
            ];
            $last = $this->positiveNumber($row['last_price'] ?? null);
            $quotes[] = $this->quotes->make(
                subscription: $pricedSubscription,
                bid: $bid,
                ask: $ask,
                last: $last,
                provider: $provider,
                // last_volume/24h_volume are quote turnover, not base volume.
                volume: null,
                timestamp: $timestamp,
            );
        }

        return $quotes;
    }

    private function bestPrice(mixed $orders, bool $buy): ?float
    {
        if (! is_array($orders)) {
            return null;
        }
        $prices = [];
        foreach ($orders as $order) {
            if (! is_array($order)) {
                continue;
            }
            $price = $this->positiveNumber($order['price'] ?? null);
            if ($price !== null && $this->positiveNumber($order['amount'] ?? null) !== null) {
                $prices[] = $price;
            }
        }

        return $prices === [] ? null : ($buy ? max($prices) : min($prices));
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0
            ? (float) $value
            : null;
    }
}
