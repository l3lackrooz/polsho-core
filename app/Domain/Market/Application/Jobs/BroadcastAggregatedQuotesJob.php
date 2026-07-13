<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Infrastructure\Aggregation\LatestQuoteAggregator;
use App\Domain\Market\Infrastructure\Broadcasting\MarketBroadcaster;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BroadcastAggregatedQuotesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param array<int, string> $instruments
     */
    public function __construct(
        private readonly array $instruments,
    ) {
        $this->onQueue(config('queue.queues.market'));
    }

    public function handle(
        LatestQuoteAggregator $aggregator,
        LatestQuoteStore $store,
        MarketBroadcaster $broadcaster,
    ): void {
        foreach (array_values(array_unique($this->instruments)) as $instrument) {
            $aggregated = $aggregator->aggregateInstrument($instrument);

            if ($aggregated === null) {
                continue;
            }

            $store->putAggregate($aggregated);
            $broadcaster->publishAggregated($aggregated);
            SendBalePriceNotificationJob::dispatch($aggregated->toArray());
        }
    }
}
