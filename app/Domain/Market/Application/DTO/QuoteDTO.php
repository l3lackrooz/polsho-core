<?php

namespace App\Domain\Market\Application\DTO;

class QuoteDTO
{
    public function __construct(
        public string $instrument,   // Unified symbol: BTC-USDT
        public float $bid,
        public float $ask,
        public ?float $last,
        public string $provider,     // nobitex, binance, kucoin...
        public ?float $volume,
        public int $timestamp,       // epoch_ms
        public ?int $providerMarketId = null,
        public bool $isReference = false,
        public ?string $providerName = null,
        public ?array $providerTranslations = null,
        public ?string $providerHomepageUrl = null,
    ) {}

    public function spread(): ?float
    {
        if ($this->bid <= 0 || $this->ask <= 0) {
            return null;
        }

        return $this->ask - $this->bid;
    }

    public function mid(): ?float
    {
        if ($this->bid <= 0 || $this->ask <= 0) {
            return null;
        }

        return ($this->bid + $this->ask) / 2;
    }

    public function toArray(): array
    {
        return [
            'instrument' => $this->instrument,
            'provider' => $this->provider,
            'provider_name' => $this->providerName,
            'provider_translations' => $this->providerTranslations,
            'provider_homepage_url' => $this->providerHomepageUrl,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'last' => $this->last,
            'volume' => $this->volume,
            'mid' => $this->mid(),
            'spread' => $this->spread(),
            'timestamp' => $this->timestamp,
            'provider_market_id' => $this->providerMarketId,
            'is_reference' => $this->isReference,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            instrument: $data['instrument'],
            bid: (float) $data['bid'],
            ask: (float) $data['ask'],
            last: $data['last'] ?? null,
            provider: $data['provider'],
            volume: $data['volume'] ?? null,
            timestamp: (int) $data['timestamp'],
            providerMarketId: $data['provider_market_id'] ?? null,
            isReference: (bool) ($data['is_reference'] ?? false),
            providerName: $data['provider_name'] ?? null,
            providerTranslations: $data['provider_translations'] ?? null,
            providerHomepageUrl: $data['provider_homepage_url'] ?? null,
        );
    }
}
