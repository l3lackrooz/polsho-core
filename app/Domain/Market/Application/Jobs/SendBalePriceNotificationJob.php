<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Infrastructure\Notifications\BalePriceAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBalePriceNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param array<string, mixed> $aggregated
     */
    public function __construct(
        private readonly array $aggregated,
    ) {}

    public function handle(BalePriceAlertService $alerts): void
    {
        $alerts->sendFor($this->toDto());
    }

    private function toDto(): AggregatedQuoteDTO
    {
        return new AggregatedQuoteDTO(
            instrument: $this->aggregated['instrument'],
            bestBid: isset($this->aggregated['best_bid']) && is_array($this->aggregated['best_bid'])
                ? \App\Domain\Market\Application\DTO\QuoteDTO::fromArray($this->aggregated['best_bid'])
                : null,
            bestAsk: isset($this->aggregated['best_ask']) && is_array($this->aggregated['best_ask'])
                ? \App\Domain\Market\Application\DTO\QuoteDTO::fromArray($this->aggregated['best_ask'])
                : null,
            providers: array_map(
                static fn (array $provider): \App\Domain\Market\Application\DTO\QuoteDTO => \App\Domain\Market\Application\DTO\QuoteDTO::fromArray($provider),
                $this->aggregated['providers'] ?? [],
            ),
            timestamp: (int) $this->aggregated['timestamp'],
        );
    }
}
