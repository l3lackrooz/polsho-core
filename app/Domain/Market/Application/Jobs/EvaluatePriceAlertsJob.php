<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\ComparisonProviderQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Application\Services\PriceAlertTriggerEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluatePriceAlertsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param array<string, mixed> $aggregated */
    public function __construct(
        private readonly array $aggregated,
    ) {
        $this->onQueue(config('queue.queues.market'));
    }

    public function handle(PriceAlertTriggerEvaluator $evaluator): void
    {
        $evaluator->evaluate($this->toDto());
    }

    private function toDto(): AggregatedQuoteDTO
    {
        return new AggregatedQuoteDTO(
            instrument: $this->aggregated['instrument'],
            bestBid: isset($this->aggregated['best_bid']) && is_array($this->aggregated['best_bid'])
                ? QuoteDTO::fromArray($this->aggregated['best_bid'])
                : null,
            bestAsk: isset($this->aggregated['best_ask']) && is_array($this->aggregated['best_ask'])
                ? QuoteDTO::fromArray($this->aggregated['best_ask'])
                : null,
            providers: array_map(
                static fn (array $provider): QuoteDTO => QuoteDTO::fromArray($provider),
                $this->aggregated['providers'] ?? [],
            ),
            timestamp: (int) $this->aggregated['timestamp'],
            comparisonProviders: array_map(
                static fn (array $provider): ComparisonProviderQuoteDTO => new ComparisonProviderQuoteDTO(
                    provider: $provider['provider'],
                    providerMarketId: (int) $provider['provider_market_id'],
                    isReference: (bool) ($provider['is_reference'] ?? false),
                    bid: isset($provider['bid']) ? (float) $provider['bid'] : null,
                    ask: isset($provider['ask']) ? (float) $provider['ask'] : null,
                    last: isset($provider['last']) ? (float) $provider['last'] : null,
                    volume: isset($provider['volume']) ? (float) $provider['volume'] : null,
                    timestamp: isset($provider['timestamp']) ? (int) $provider['timestamp'] : null,
                ),
                $this->aggregated['comparison_providers'] ?? [],
            ),
        );
    }
}
