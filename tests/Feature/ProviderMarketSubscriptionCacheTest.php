<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderMarketSubscriptionCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_provider_market_is_available_without_waiting_for_the_mapping_cache_to_expire(): void
    {
        $provider = MarketProvider::query()->create([
            'name' => 'TGJU',
            'slug' => 'tgju',
            'driver' => 'Tests\\FakeTgjuDriver',
            'base_url' => 'https://call4.tgju.org',
        ]);
        $usdt = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether']);
        $irr = Asset::query()->create(['symbol' => 'IRR', 'name' => 'Iranian Rial']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $usdt->id,
            'quote_asset_id' => $irr->id,
            'symbol' => 'USDT-IRR',
        ]);
        $subscriptions = app(MarketSubscriptionFactory::class);

        try {
            $subscriptions->forProvider('USDT-IRR', 'tgju');
            $this->fail('The empty mapping should be cached before the market is added.');
        } catch (\InvalidArgumentException) {
            // The factory has now cached the empty TGJU mapping set.
        }

        ProviderMarket::query()->create([
            'provider_id' => $provider->id,
            'instrument_id' => $instrument->id,
            'remote_symbol' => 'crypto-tether-irr',
        ]);

        $subscription = $subscriptions->forProvider('USDT-IRR', 'tgju');

        $this->assertSame('USDT-IRR', $subscription->instrument);
        $this->assertSame('crypto-tether-irr', $subscription->remoteSymbol);
    }
}
