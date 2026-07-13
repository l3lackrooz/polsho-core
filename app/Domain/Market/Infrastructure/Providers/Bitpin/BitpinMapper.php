<?php

namespace App\Domain\Market\Infrastructure\Providers\Bitpin;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;

class BitpinMapper
{
    /**
     * @param array<int, array<string, mixed>> $tickers list from /mkt/tickers/
     * @param array<string, array<string, mixed>> $orderBooks keyed by remote symbol, from /mkt/orderbook/{symbol}/
     * @param array<string, MarketSubscriptionDTO> $subscriptions keyed by remote symbol (BTC_IRT, ...)
     * @return array<int, QuoteDTO>
     */
    public function mapSnapshot(array $tickers, array $orderBooks, array $subscriptions, string $provider): array
    {
        $quotes = [];

        foreach ($tickers as $row) {
            $symbol = (string) ($row['symbol'] ?? '');

            if ($symbol === '' || !isset($subscriptions[$symbol])) {
                continue;
            }

            $book = $orderBooks[$symbol] ?? [];
            // Orderbook rows are [price, quantity] with best price first.
            $bestBid = isset($book['bids'][0][0]) ? (float) $book['bids'][0][0] : 0.0;
            $bestAsk = isset($book['asks'][0][0]) ? (float) $book['asks'][0][0] : 0.0;
            $last = isset($row['price']) ? (float) $row['price'] : null;

            $quotes[] = new QuoteDTO(
                instrument: $subscriptions[$symbol]->instrument,
                bid: $bestBid > 0.0 ? $bestBid : ($last ?? 0.0),
                ask: $bestAsk > 0.0 ? $bestAsk : ($last ?? 0.0),
                last: $last,
                provider: $provider,
                volume: null,
                timestamp: isset($row['timestamp'])
                    ? (int) round(((float) $row['timestamp']) * 1000)
                    : (int) round(microtime(true) * 1000),
                providerMarketId: $subscriptions[$symbol]->providerMarketId,
            );
        }

        return $quotes;
    }
}
