<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\DTO\ProviderMarketDTO;
use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use Illuminate\Support\Facades\DB;

class UpdateProviderMarket
{
    public function execute(ProviderMarket $providerMarket, ProviderMarketDTO $data): ProviderMarket
    {
        $providerMarket = DB::transaction(function () use ($providerMarket, $data): ProviderMarket {
            $providerMarket->update($data->toArray());

            return $providerMarket->refresh();
        });

        if ($providerMarket->status === 'active') {
            SyncProviderQuotesJob::dispatch($providerMarket->provider_id);
        }

        return $providerMarket->load(['provider', 'instrument.baseAsset', 'instrument.quoteAsset']);
    }
}
