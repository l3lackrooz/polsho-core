<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\Jobs\AggregateInstrumentJob;
use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Providers\Tala\TalaDriver;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncProviderQuotesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_provider_response_reaggregates_markets_without_failing(): void
    {
        Bus::fake();
        Http::fake(['https://www.tala.ir/banner' => Http::response(['banner' => []])]);

        $provider = MarketProvider::query()->create([
            'name' => 'Tala.ir',
            'slug' => 'tala',
            'driver' => TalaDriver::class,
            'base_url' => 'https://www.tala.ir',
        ]);
        $base = Asset::query()->create(['symbol' => 'GERAM18', 'name' => '18K Gold']);
        $quote = Asset::query()->create(['symbol' => 'IRR', 'name' => 'Iranian Rial']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'GERAM18-IRR',
        ]);
        ProviderMarket::query()->create([
            'provider_id' => $provider->id,
            'instrument_id' => $instrument->id,
            'remote_symbol' => 'geram18',
        ]);

        (new SyncProviderQuotesJob($provider->id))->handle(app(ProviderFactory::class));

        Bus::assertDispatched(AggregateInstrumentJob::class);
    }
}
