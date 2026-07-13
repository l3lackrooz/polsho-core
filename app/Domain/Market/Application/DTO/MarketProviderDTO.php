<?php

namespace App\Domain\Market\Application\DTO;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Support\Str;

class MarketProviderDTO
{
    public function __construct(
        public string $name,
        public string $driver,
        public string $slug,
        public string $baseUrl,
        public ?string $description = null,
        public string $status = 'active',
        public bool $isDefault = false,
        public int $priority = 0,
        public ?array $config = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $name = trim($data['name']);

        return new self(
            name: $name,
            driver: trim($data['driver']),
            slug: strtolower(trim($data['slug'] ?? '') ?: Str::slug($name, '_')),
            baseUrl: rtrim(trim($data['base_url']), '/'),
            description: $data['description'] ?? null,
            status: $data['status'] ?? 'active',
            isDefault: (bool) ($data['is_default'] ?? false),
            priority: (int) ($data['priority'] ?? 0),
            config: $data['config'] ?? null,
        );
    }

    /**
     * Build a complete DTO for a partial update: current model state
     * overridden by whatever the request actually sent.
     */
    public static function forUpdate(MarketProvider $provider, array $overrides): self
    {
        return self::fromArray(array_merge(
            [
                'name' => $provider->name,
                'driver' => $provider->driver,
                'slug' => $provider->slug,
                'base_url' => $provider->base_url,
                'description' => $provider->description,
                'status' => $provider->status,
                'is_default' => $provider->is_default,
                'priority' => $provider->priority,
                'config' => $provider->config,
            ],
            $overrides,
        ));
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'driver' => $this->driver,
            'slug' => $this->slug,
            'base_url' => $this->baseUrl,
            'description' => $this->description,
            'status' => $this->status,
            'is_default' => $this->isDefault,
            'priority' => $this->priority,
            'config' => $this->config,
        ];
    }

    /** Attribute shape expected by the existing CreateMarketProvider action. */
    public function toCreateAttributes(): array
    {
        return [
            'name' => $this->name,
            'class_name' => $this->driver,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'is_default' => $this->isDefault,
            'priority' => $this->priority,
            'config' => array_merge($this->config ?? [], ['base_url' => $this->baseUrl]),
        ];
    }
}
