<?php

namespace App\Domain\Market\Application\DTO;

class AggregatedQuoteDTO
{
    /**
     * @param  QuoteDTO[]  $providers
     * @param  ComparisonProviderQuoteDTO[]  $comparisonProviders
     */
    public function __construct(
        public string $instrument,
        public ?QuoteDTO $bestBid,
        public ?QuoteDTO $bestAsk,
        public array $providers,
        public int $timestamp, // epoch_ms
        public array $comparisonProviders = [],
    ) {}

    public function toArray(): array
    {
        return [
            'instrument' => $this->instrument,
            'best_bid' => $this->bestBid?->toArray(),
            'best_ask' => $this->bestAsk?->toArray(),
            'providers' => array_map(
                fn (QuoteDTO $q) => [
                    ...$q->toArray(),
                    'is_best_bid' => $this->isSameQuote($q, $this->bestBid),
                    'is_best_ask' => $this->isSameQuote($q, $this->bestAsk),
                ],
                $this->providers,
            ),
            'comparison_providers' => array_map(
                fn (ComparisonProviderQuoteDTO $q) => [
                    ...$q->toArray(),
                    'is_best_bid' => $this->isSameProviderMarket($q, $this->bestBid),
                    'is_best_ask' => $this->isSameProviderMarket($q, $this->bestAsk),
                ],
                $this->comparisonProviders,
            ),
            'timestamp' => $this->timestamp,
        ];
    }

    private function isSameQuote(QuoteDTO $candidate, ?QuoteDTO $best): bool
    {
        if ($best === null || $candidate->provider !== $best->provider) {
            return false;
        }

        return $candidate->providerMarketId === $best->providerMarketId;
    }

    private function isSameProviderMarket(
        ComparisonProviderQuoteDTO $candidate,
        ?QuoteDTO $best,
    ): bool {
        return $best !== null
            && $candidate->provider === $best->provider
            && $candidate->providerMarketId === $best->providerMarketId;
    }
}
