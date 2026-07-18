<?php

namespace App\Domain\Market\Application\DTO;

class ComparisonProviderQuoteDTO
{
    public function __construct(
        public string $provider,
        public int $providerMarketId,
        public bool $isReference,
        public ?float $bid,
        public ?float $ask,
        public ?float $last,
        public ?float $volume,
        public ?int $timestamp,
        public ?string $providerName = null,
        public ?array $providerTranslations = null,
        public ?string $providerHomepageUrl = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_name' => $this->providerName,
            'provider_translations' => $this->providerTranslations,
            'provider_homepage_url' => $this->providerHomepageUrl,
            'provider_market_id' => $this->providerMarketId,
            'is_reference' => $this->isReference,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'last' => $this->last,
            'volume' => $this->volume,
            'spread' => $this->spread(),
            'timestamp' => $this->timestamp,
        ];
    }

    private function spread(): ?float
    {
        if ($this->bid === null || $this->ask === null) {
            return null;
        }

        return $this->ask - $this->bid;
    }
}
