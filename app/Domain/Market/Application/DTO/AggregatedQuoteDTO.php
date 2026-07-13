<?php

namespace App\Domain\Market\Application\DTO;

class AggregatedQuoteDTO
{
    /**
     * @param QuoteDTO[] $providers
     */
    public function __construct(
        public string $instrument,
        public ?QuoteDTO $bestBid,
        public ?QuoteDTO $bestAsk,
        public array $providers,
        public int $timestamp, // epoch_ms
    ) {}

    public function toArray(): array
    {
        return [
            'instrument' => $this->instrument,
            'best_bid'   => $this->bestBid?->toArray(),
            'best_ask'   => $this->bestAsk?->toArray(),
            'providers'  => array_map(
                fn (QuoteDTO $q) => [
                    ...$q->toArray(),
                    'is_best_bid' => $this->isSameQuote($q, $this->bestBid),
                    'is_best_ask' => $this->isSameQuote($q, $this->bestAsk),
                ],
                $this->providers,
            ),
            'timestamp'  => $this->timestamp,
        ];
    }

    private function isSameQuote(QuoteDTO $candidate, ?QuoteDTO $best): bool
    {
        if ($best === null || $candidate->provider !== $best->provider) {
            return false;
        }

        return $candidate->providerMarketId === $best->providerMarketId;
    }
}
