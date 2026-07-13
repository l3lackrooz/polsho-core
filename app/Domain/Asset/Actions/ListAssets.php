<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Application\DTO\AssetFiltersDTO;
use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAssets
{
    public function execute(AssetFiltersDTO $filters): LengthAwarePaginator
    {
        return Asset::query()
            ->when($filters->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('symbol', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters->status, fn ($query, $status) => $query->where('status', $status))
            ->when($filters->type, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('symbol')
            ->paginate($filters->perPage);
    }
}
