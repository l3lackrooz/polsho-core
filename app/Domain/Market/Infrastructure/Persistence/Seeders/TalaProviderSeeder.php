<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Market\Infrastructure\Providers\Tala\TalaDriver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TalaProviderSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('market_providers')->updateOrInsert(
            ['slug' => 'tala'],
            [
                'name' => 'Tala.ir',
                'translations' => json_encode(['fa' => 'سایت طلا', 'de' => 'Tala.ir']),
                'driver' => TalaDriver::class,
                'base_url' => 'https://www.tala.ir',
                'homepage_url' => 'https://www.tala.ir',
                'description' => 'Tala.ir reference prices for Iranian gold markets.',
                'status' => 'active',
                'is_default' => false,
                'priority' => 11,
                'config' => json_encode([
                    'is_reference' => true,
                    'max_quote_age_seconds' => 1_800,
                    'rest' => ['timeout' => 10],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $providerId = DB::table('market_providers')->where('slug', 'tala')->value('id');

        foreach ([
            'MESGHAL-IRR' => 'bazartehran',
            'GERAM18-IRR' => 'geram18',
        ] as $instrumentSymbol => $remoteSymbol) {
            $instrumentId = DB::table('instruments')->where('symbol', $instrumentSymbol)->value('id');

            if ($instrumentId === null) {
                $this->command?->warn("Instrument [{$instrumentSymbol}] not found; skipping Tala market {$remoteSymbol}.");
                continue;
            }

            DB::table('provider_markets')->updateOrInsert(
                ['provider_id' => $providerId, 'instrument_id' => $instrumentId],
                [
                    'remote_symbol' => $remoteSymbol,
                    'status' => 'active',
                    'metadata' => json_encode((object) []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
