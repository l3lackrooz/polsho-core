<?php

namespace App\Domain\Asset\Application\DTO;

use App\Domain\Asset\Models\Instrument;

class InstrumentDTO
{
    public function __construct(
        public int $baseAssetId,
        public int $quoteAssetId,
        public string $symbol,
        public string $status = 'active',
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            baseAssetId: (int) $data['base_asset_id'],
            quoteAssetId: (int) $data['quote_asset_id'],
            symbol: trim($data['symbol']),
            status: $data['status'] ?? 'active',
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Build a complete DTO for a partial update: current model state
     * overridden by whatever the request actually sent.
     */
    public static function forUpdate(Instrument $instrument, array $overrides): self
    {
        return self::fromArray(array_merge(
            [
                'base_asset_id' => $instrument->base_asset_id,
                'quote_asset_id' => $instrument->quote_asset_id,
                'symbol' => $instrument->symbol,
                'status' => $instrument->status,
                'metadata' => $instrument->metadata,
            ],
            $overrides,
        ));
    }

    public function toArray(): array
    {
        return [
            'base_asset_id' => $this->baseAssetId,
            'quote_asset_id' => $this->quoteAssetId,
            'symbol' => $this->symbol,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
