<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_receive_the_mobile_alert_read_model(): void
    {
        $user = User::factory()->create();
        $base = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether', 'type' => 'crypto']);
        $quote = Asset::query()->create(['symbol' => 'IRT', 'name' => 'Iranian Toman', 'type' => 'fiat']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'USDT-IRT',
        ]);
        $provider = MarketProvider::query()->create([
            'name' => 'Nobitex',
            'driver' => 'nobitex',
            'slug' => 'nobitex',
            'base_url' => 'https://example.test',
        ]);
        $providerMarket = ProviderMarket::query()->create([
            'provider_id' => $provider->id,
            'instrument_id' => $instrument->id,
            'remote_symbol' => 'USDTIRT',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/market/price-alerts', [
            'instrument_id' => $instrument->id,
            'provider_market_id' => $providerMarket->id,
            'scope' => 'specific_exchange',
            'condition' => 'goes_above',
            'target_price' => 92000,
            'repeat' => 'recurring',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.instrument.symbol', 'USDT-IRT')
            ->assertJsonPath('data.instrument.base_asset.symbol', 'USDT')
            ->assertJsonPath('data.instrument.quote_asset.symbol', 'IRT')
            ->assertJsonPath('data.provider_market.provider.slug', 'nobitex')
            ->assertJsonPath('data.scope', 'specific_exchange')
            ->assertJsonPath('data.current_price', null)
            ->assertJsonPath('data.events.0.type', 'created');
    }
}
