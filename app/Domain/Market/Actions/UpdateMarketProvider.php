<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\DTO\MarketProviderDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Support\Facades\DB;

class UpdateMarketProvider
{
    public function execute(MarketProvider $provider, MarketProviderDTO $data): MarketProvider
    {
        return DB::transaction(function () use ($provider, $data): MarketProvider {
            $provider->update($data->toArray());

            return $provider->refresh();
        });
    }
}
