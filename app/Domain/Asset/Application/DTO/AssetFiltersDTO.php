<?php

namespace App\Domain\Asset\Application\DTO;

use Illuminate\Http\Request;

class AssetFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $type = null,
        public int $perPage = 15,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: $request->filled('search') ? (string) $request->string('search') : null,
            status: $request->filled('status') ? (string) $request->string('status') : null,
            type: $request->filled('type') ? (string) $request->string('type') : null,
            perPage: min(max($request->integer('per_page', 15), 1), 100),
        );
    }
}
