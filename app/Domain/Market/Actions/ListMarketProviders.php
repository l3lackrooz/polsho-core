<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\DTO\MarketProviderFiltersDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMarketProviders
{
    public function execute(MarketProviderFiltersDTO $filters): LengthAwarePaginator
    {
        return MarketProvider::query()
            ->withCount('markets')
            ->when($filters->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($filters->status, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate($filters->perPage);
    }
}
