<?php

namespace App\Domain\Asset\Actions;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use Illuminate\Support\Facades\DB;

class DeleteAsset
{
    public function execute(Asset $asset): void
    {
        // Instruments referencing this asset are removed by the FK cascade.
        DB::transaction(fn () => $asset->delete());
    }
}
