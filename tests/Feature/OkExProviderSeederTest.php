<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Market\Infrastructure\Persistence\Seeders\NewProvidersSeeder;
use App\Domain\Asset\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OkExProviderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_active_btc_usdt_ok_ex_market(): void
    {
        $btc = Asset::query()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
        $usdt = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $btc->id,
            'quote_asset_id' => $usdt->id,
            'symbol' => 'btc-usdt',
            'status' => 'active',
        ]);

        $this->seed(NewProvidersSeeder::class);

        $providerId = DB::table('market_providers')->where('slug', 'ok-ex')->value('id');

        $this->assertNotNull($providerId);
        $this->assertDatabaseHas('market_providers', [
            'id' => $providerId,
            'name' => 'OK-EX',
            'base_url' => 'https://sapi.ok-ex.io',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('provider_markets', [
            'provider_id' => $providerId,
            'instrument_id' => $instrument->id,
            'remote_symbol' => 'BTC-USDT',
            'status' => 'active',
        ]);
    }
}
