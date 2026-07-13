<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use Illuminate\Support\Facades\DB;

class DeleteProviderMarket
{
    public function execute(ProviderMarket $providerMarket): void
    {
        DB::transaction(fn () => $providerMarket->delete());
    }
}
