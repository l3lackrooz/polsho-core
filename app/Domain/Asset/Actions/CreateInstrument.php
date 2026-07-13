<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Application\DTO\InstrumentDTO;
use App\Domain\Asset\Models\Instrument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInstrument
{
    public function execute(InstrumentDTO $data): Instrument
    {
        $this->assertDistinctPair($data);

        $instrument = DB::transaction(
            fn (): Instrument => Instrument::query()->create($data->toArray())
        );

        return $instrument->load(['baseAsset', 'quoteAsset']);
    }

    private function assertDistinctPair(InstrumentDTO $data): void
    {
        if ($data->baseAssetId === $data->quoteAssetId) {
            throw ValidationException::withMessages([
                'quote_asset_id' => 'Base and quote asset must be different.',
            ]);
        }
    }
}
