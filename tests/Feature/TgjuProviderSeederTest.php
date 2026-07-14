<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Market\Infrastructure\Persistence\Seeders\TgjuProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TgjuProviderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_active_eur_and_try_reference_market_mappings(): void
    {
        Asset::query()->create(['symbol' => 'IRR', 'name' => 'Iranian Rial']);

        $this->seed(TgjuProviderSeeder::class);

        $providerId = DB::table('market_providers')->where('slug', 'tgju')->value('id');

        foreach ([
            'EUR-IRR' => 'price_eur',
            'TRY-IRR' => 'price_try',
        ] as $instrumentSymbol => $remoteSymbol) {
            $instrumentId = DB::table('instruments')
                ->where('symbol', $instrumentSymbol)
                ->value('id');

            $this->assertNotNull($instrumentId);
            $this->assertDatabaseHas('provider_markets', [
                'provider_id' => $providerId,
                'instrument_id' => $instrumentId,
                'remote_symbol' => $remoteSymbol,
                'status' => 'active',
            ]);
        }
    }
}
