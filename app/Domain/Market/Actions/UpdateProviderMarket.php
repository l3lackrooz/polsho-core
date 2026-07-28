<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\DTO\ProviderMarketDTO;
use App\Domain\Market\Application\Jobs\AggregateInstrumentJob;
use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use Illuminate\Support\Facades\DB;

class UpdateProviderMarket
{
    public function __construct(
        private readonly LatestQuoteStore $quotes,
    ) {}

    public function execute(ProviderMarket $providerMarket, ProviderMarketDTO $data): ProviderMarket
    {
        $providerMarket->loadMissing(['provider:id,slug', 'instrument:id,symbol']);

        $wasActive = $providerMarket->status === 'active';
        $provider = $providerMarket->provider->slug;
        $instrument = $providerMarket->instrument->symbol;

        $providerMarket = DB::transaction(function () use ($providerMarket, $data): ProviderMarket {
            $providerMarket->update($data->toArray());

            return $providerMarket->refresh();
        });

        if ($providerMarket->status === 'active') {
            SyncProviderQuotesJob::dispatch($providerMarket->provider_id);
        } elseif ($wasActive) {
            $this->quotes->removeProvider($instrument, $provider);
            AggregateInstrumentJob::dispatch($instrument);
        }

        return $providerMarket->load(['provider', 'instrument.baseAsset', 'instrument.quoteAsset']);
    }
}
