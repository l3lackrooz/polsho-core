<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\Jobs\AggregateInstrumentJob;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use Illuminate\Support\Facades\DB;

class DeleteProviderMarket
{
    public function __construct(
        private readonly LatestQuoteStore $quotes,
    ) {}

    public function execute(ProviderMarket $providerMarket): void
    {
        $providerMarket->loadMissing(['provider:id,slug', 'instrument:id,symbol']);

        $provider = $providerMarket->provider->slug;
        $instrument = $providerMarket->instrument->symbol;

        DB::transaction(fn () => $providerMarket->delete());

        $this->quotes->removeProvider($instrument, $provider);
        AggregateInstrumentJob::dispatch($instrument);
    }
}
