<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Application\DTO\InstrumentFiltersDTO;
use App\Domain\Asset\Models\Instrument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListInstruments
{
    public function execute(InstrumentFiltersDTO $filters): LengthAwarePaginator
    {
        return Instrument::query()
            ->with(['baseAsset', 'quoteAsset'])
            ->when($filters->search, fn ($query, $search) => $query->where('symbol', 'like', "%{$search}%"))
            ->when($filters->status, fn ($query, $status) => $query->where('status', $status))
            ->when($filters->baseAssetId, fn ($query, $id) => $query->where('base_asset_id', $id))
            ->when($filters->quoteAssetId, fn ($query, $id) => $query->where('quote_asset_id', $id))
            ->orderBy('symbol')
            ->paginate($filters->perPage);
    }
}
