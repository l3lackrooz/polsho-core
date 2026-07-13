<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Support\Facades\DB;

class DeleteMarketProvider
{
    public function execute(MarketProvider $provider): void
    {
        // Provider markets referencing this provider are removed by the FK cascade.
        DB::transaction(fn () => $provider->delete());
    }
}
