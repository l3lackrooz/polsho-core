<?php

namespace App\Domain\Market\Infrastructure\Services;

use App\Domain\Market\Events\MarketDataUpdated;
use App\Domain\Market\Infrastructure\Aggregation\LatestQuoteAggregator;
use App\Domain\Market\Infrastructure\Stores\AggregateStore;

class MarketDataService
{
    public function __construct(
        private LatestQuoteAggregator $aggregator,
        private AggregateStore $store
    ) {}

    public function aggregate(string $instrument): void
    {
        $dto = $this->aggregator->aggregateInstrument($instrument);

        if (!$dto) {
            return;
        }

        $changed = $this->store->put(
            $instrument,
            $dto
        );

        if ($changed) {
            event(new MarketDataUpdated($dto));
        }
    }
}

