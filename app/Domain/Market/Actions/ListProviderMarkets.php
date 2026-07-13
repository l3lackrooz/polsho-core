<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\DTO\ProviderMarketFiltersDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProviderMarkets
{
    public function execute(ProviderMarketFiltersDTO $filters): LengthAwarePaginator
    {
        return ProviderMarket::query()
            ->with(['provider', 'instrument.baseAsset', 'instrument.quoteAsset'])
            ->when($filters->providerId, fn ($query, $id) => $query->where('provider_id', $id))
            ->when($filters->instrumentId, fn ($query, $id) => $query->where('instrument_id', $id))
            ->when($filters->status, fn ($query, $status) => $query->where('status', $status))
            ->when($filters->search, fn ($query, $search) => $query->where('remote_symbol', 'like', "%{$search}%"))
            ->orderBy('provider_id')
            ->orderBy('remote_symbol')
            ->paginate($filters->perPage);
    }
}
