<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Models\Instrument;
use Illuminate\Support\Facades\DB;

class DeleteInstrument
{
    public function execute(Instrument $instrument): void
    {
        DB::transaction(fn () => $instrument->delete());
    }
}
