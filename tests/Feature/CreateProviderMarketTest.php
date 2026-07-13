<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Actions\CreateProviderMarket;
use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CreateProviderMarketTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_an_initial_sync_for_an_active_provider_market(): void
    {
        Bus::fake();

        $provider = MarketProvider::query()->create([
            'name' => 'TGJU',
            'slug' => 'tgju',
            'driver' => 'Tests\\FakeTgjuDriver',
            'base_url' => 'https://call4.tgju.org',
        ]);
        $base = Asset::query()->create(['symbol' => 'MESGHAL', 'name' => 'Gold (Mithqal)']);
        $quote = Asset::query()->create(['symbol' => 'IRR', 'name' => 'Iranian Rial']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'MESGHAL-IRR',
        ]);

        app(CreateProviderMarket::class)->execute([
            'provider_id' => $provider->id,
            'instrument_id' => $instrument->id,
            'remote_symbol' => 'mesghal',
            'status' => 'active',
        ]);

        Bus::assertDispatched(SyncProviderQuotesJob::class);
    }
}
