<?php

namespace App\Domain\Market\Application\DTO;

use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;

class ProviderMarketDTO
{
    public function __construct(
        public int $providerId,
        public int $instrumentId,
        public string $remoteSymbol,
        public string $status = 'active',
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            providerId: (int) $data['provider_id'],
            instrumentId: (int) $data['instrument_id'],
            remoteSymbol: trim($data['remote_symbol']),
            status: $data['status'] ?? 'active',
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Build a complete DTO for a partial update: current model state
     * overridden by whatever the request actually sent.
     */
    public static function forUpdate(ProviderMarket $providerMarket, array $overrides): self
    {
        return self::fromArray(array_merge(
            [
                'provider_id' => $providerMarket->provider_id,
                'instrument_id' => $providerMarket->instrument_id,
                'remote_symbol' => $providerMarket->remote_symbol,
                'status' => $providerMarket->status,
                'metadata' => $providerMarket->metadata,
            ],
            $overrides,
        ));
    }

    public function toArray(): array
    {
        return [
            'provider_id' => $this->providerId,
            'instrument_id' => $this->instrumentId,
            'remote_symbol' => $this->remoteSymbol,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
