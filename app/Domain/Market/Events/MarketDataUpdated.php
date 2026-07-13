<?php

namespace App\Domain\Market\Events;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AggregatedQuoteDTO $aggregated,
    ) {}

    /**
     * Firehose channel plus a per-instrument channel so Flutter
     * clients can subscribe to only the symbols they display.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('market.quotes'),
            new Channel('market.quotes.'.$this->aggregated->instrument),
        ];
    }

    public function broadcastAs(): string
    {
        return 'market.quote.updated';
    }

    public function broadcastWith(): array
    {
        return $this->aggregated->toArray();
    }
}
