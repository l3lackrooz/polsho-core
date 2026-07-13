<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Application\DTO\InstrumentDTO;
use App\Domain\Asset\Models\Instrument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInstrument
{
    public function execute(Instrument $instrument, InstrumentDTO $data): Instrument
    {
        // The DTO is hydrated from current state + overrides, so this also
        // catches an update that changes only one side of the pair.
        if ($data->baseAssetId === $data->quoteAssetId) {
            throw ValidationException::withMessages([
                'quote_asset_id' => 'Base and quote asset must be different.',
            ]);
        }

        $instrument = DB::transaction(function () use ($instrument, $data): Instrument {
            $instrument->update($data->toArray());

            return $instrument->refresh();
        });

        return $instrument->load(['baseAsset', 'quoteAsset']);
    }
}
