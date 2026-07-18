<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Market\Infrastructure\Persistence\Seeders\TalaProviderSeeder;
use App\Domain\Market\Infrastructure\Persistence\Seeders\TgjuProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TalaProviderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_tala_gold_market_mappings(): void
    {
        Asset::query()->create(['symbol' => 'IRR', 'name' => 'Iranian Rial']);
        $this->seed(TgjuProviderSeeder::class);
        $this->seed(TalaProviderSeeder::class);

        $providerId = DB::table('market_providers')->where('slug', 'tala')->value('id');

        foreach (['MESGHAL-IRR' => 'bazartehran', 'GERAM18-IRR' => 'geram18'] as $instrument => $remoteSymbol) {
            $instrumentId = DB::table('instruments')->where('symbol', $instrument)->value('id');

            $this->assertDatabaseHas('provider_markets', [
                'provider_id' => $providerId,
                'instrument_id' => $instrumentId,
                'remote_symbol' => $remoteSymbol,
                'status' => 'active',
            ]);
        }
    }
}
