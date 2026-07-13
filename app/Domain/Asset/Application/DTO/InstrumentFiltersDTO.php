<?php

namespace App\Domain\Asset\Application\DTO;

use Illuminate\Http\Request;

class InstrumentFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?int $baseAssetId = null,
        public ?int $quoteAssetId = null,
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->filled('search') ? (string) $request->string('search') : null,
            status: $request->filled('status') ? (string) $request->string('status') : null,
            baseAssetId: $request->filled('base_asset_id') ? $request->integer('base_asset_id') : null,
            quoteAssetId: $request->filled('quote_asset_id') ? $request->integer('quote_asset_id') : null,
            perPage: min(max($request->integer('per_page', 15), 1), 100),
        );
    }
}
