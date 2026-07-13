<?php

namespace App\Domain\Market\Application\DTO;

use Illuminate\Http\Request;

class ProviderMarketFiltersDTO
{
    public function __construct(
        public ?int $providerId = null,
        public ?int $instrumentId = null,
        public ?string $status = null,
        public ?string $search = null,
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            providerId: $request->filled('provider_id') ? $request->integer('provider_id') : null,
            instrumentId: $request->filled('instrument_id') ? $request->integer('instrument_id') : null,
            status: $request->filled('status') ? (string) $request->string('status') : null,
            search: $request->filled('search') ? (string) $request->string('search') : null,
            perPage: min(max($request->integer('per_page', 15), 1), 100),
        );
    }
}
