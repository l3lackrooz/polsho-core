<?php

namespace App\Domain\Market\Infrastructure\Broadcasting;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Events\MarketQuoteAggregated;

class MarketBroadcaster
{
    public function publishQuote(QuoteDTO $quote): void
    {
        $aggregated = new AggregatedQuoteDTO(
            instrument: $quote->instrument,
            bestBid: $quote,
            bestAsk: $quote,
            providers: [$quote],
            timestamp: $quote->timestamp,
        );

        broadcast(new MarketQuoteAggregated($aggregated));
    }

    public function publishAggregated(AggregatedQuoteDTO $aggregated): void
    {
        broadcast(new MarketQuoteAggregated($aggregated));
    }
}
