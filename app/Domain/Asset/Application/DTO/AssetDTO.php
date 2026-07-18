<?php

namespace App\Domain\Asset\Application\DTO;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Shared\Enums\CurrencyType;

class AssetDTO
{
    public function __construct(
        public string $symbol,
        public string $name,
        public int $precision = 8,
        public string $status = 'active',
        public CurrencyType $type = CurrencyType::CRYPTO,
        public bool $isBaseCurrency = false,
        public ?array $translations = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            symbol: strtoupper(trim($data['symbol'])),
            name: trim($data['name']),
            precision: (int) ($data['precision'] ?? 8),
            status: $data['status'] ?? 'active',
            type: CurrencyType::from($data['type'] ?? CurrencyType::CRYPTO->value),
            isBaseCurrency: (bool) ($data['is_base_currency'] ?? false),
            translations: self::normalizeTranslations($data['translations'] ?? null),
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * Build a complete DTO for a partial update: current model state
     * overridden by whatever the request actually sent.
     */
    public static function forUpdate(Asset $asset, array $overrides): self
    {
        return self::fromArray(array_merge(
            [
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'precision' => $asset->precision,
                'status' => $asset->status,
                'type' => $asset->type->value,
                'is_base_currency' => $asset->is_base_currency,
                'translations' => $asset->translations,
                'metadata' => $asset->metadata,
            ],
            $overrides,
        ));
    }

    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'name' => $this->name,
            'precision' => $this->precision,
            'status' => $this->status,
            'type' => $this->type->value,
            'is_base_currency' => $this->isBaseCurrency,
            'translations' => $this->translations,
            'metadata' => $this->metadata,
        ];
    }

    private static function normalizeTranslations(?array $translations): ?array
    {
        if ($translations === null) {
            return null;
        }

        $normalized = [];
        foreach ($translations as $locale => $name) {
            $locale = strtolower(trim((string) $locale));
            $name = trim((string) $name);
            if ($locale !== '' && $name !== '') {
                $normalized[$locale] = $name;
            }
        }

        return $normalized;
    }
}
