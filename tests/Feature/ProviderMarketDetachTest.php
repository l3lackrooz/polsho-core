<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Actions\DeleteProviderMarket;
use App\Domain\Market\Actions\UpdateProviderMarket;
use App\Domain\Market\Application\DTO\ProviderMarketDTO;
use App\Domain\Market\Application\Jobs\AggregateInstrumentJob;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProviderMarketDetachTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_provider_market_removes_its_quote_and_reaggregates_the_instrument(): void
    {
        Bus::fake();
        $market = $this->createProviderMarket();

        $quotes = $this->mock(LatestQuoteStore::class);
        $quotes->shouldReceive('removeProvider')
            ->once()
            ->with('USDT-IRT', 'nobitex');

        app(DeleteProviderMarket::class)->execute($market);

        $this->assertModelMissing($market);
        Bus::assertDispatched(AggregateInstrumentJob::class);
    }

    public function test_disabling_a_provider_market_removes_its_quote_and_reaggregates_the_instrument(): void
    {
        Bus::fake();
        $market = $this->createProviderMarket();

        $quotes = $this->mock(LatestQuoteStore::class);
        $quotes->shouldReceive('removeProvider')
            ->once()
            ->with('USDT-IRT', 'nobitex');

        app(UpdateProviderMarket::class)->execute(
            $market,
            ProviderMarketDTO::forUpdate($market, ['status' => 'inactive']),
        );

        $this->assertSame('inactive', $market->fresh()->status);
        Bus::assertDispatched(AggregateInstrumentJob::class);
    }

    private function createProviderMarket(): ProviderMarket
    {
        $provider = MarketProvider::query()->create([
            'name' => 'Nobitex',
            'slug' => 'nobitex',
            'driver' => 'Tests\\FakeNobitexDriver',
            'base_url' => 'https://example.test',
        ]);
        $base = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether']);
        $quote = Asset::query()->create(['symbol' => 'IRT', 'name' => 'Iranian Toman']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'USDT-IRT',
        ]);

        return ProviderMarket::query()->create([
            'provider_id' => $provider->id,
            'instrument_id' => $instrument->id,
            'remote_symbol' => 'usdt-irt',
            'status' => 'active',
        ]);
    }
}
