<?php

namespace App\Domain\Market\Application\DTO;

class MarketSubscriptionDTO
{
    public function __construct(
        public string $instrument,
        public string $remoteSymbol,
        public string $base,
        public string $quote,
        public ?int $providerMarketId = null,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'instrument' => $this->instrument,
            'remote_symbol' => $this->remoteSymbol,
            'base' => $this->base,
            'quote' => $this->quote,
            'provider_market_id' => $this->providerMarketId,
            'metadata' => $this->metadata,
        ];
    }
}
