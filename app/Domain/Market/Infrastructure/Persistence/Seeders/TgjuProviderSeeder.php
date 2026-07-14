<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Market\Infrastructure\Providers\Tgju\TgjuDriver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the TGJU reference-rate provider plus the fiat/gold assets,
 * IRR instruments, and remote symbol mappings it serves.
 *
 * TGJU is not an exchange: it publishes one reference price per indicator
 * (no bid/ask spread, no order book), so it is seeded with a low priority
 * and acts as the rate source for fiat and gold instruments the crypto
 * exchanges do not cover.
 */
class TgjuProviderSeeder extends Seeder
{
    private const REV = 'eVNPDCLc01kFsBWorZ2eA3RLKk5npoquG2svGMMV1dQ0lADHsdZepSGQjKYd';

    public function run(): void
    {
        $this->seedAssets();
        $this->seedInstruments();
        $this->seedProvider();
    }

    private function seedAssets(): void
    {
        $assets = [
            ['symbol' => 'USD', 'name' => 'US Dollar', 'precision' => 0, 'type' => 'fiat'],
            ['symbol' => 'EUR', 'name' => 'Euro', 'precision' => 0, 'type' => 'fiat'],
            ['symbol' => 'AED', 'name' => 'UAE Dirham', 'precision' => 0, 'type' => 'fiat'],
            ['symbol' => 'TRY', 'name' => 'Turkish Lira', 'precision' => 0, 'type' => 'fiat'],
            ['symbol' => 'MESGHAL', 'name' => 'Gold (Mithqal)', 'precision' => 0, 'type' => 'metal'],
            ['symbol' => 'GERAM18', 'name' => '18K Gold (Gram)', 'precision' => 0, 'type' => 'metal'],
        ];

        foreach ($assets as $asset) {
            DB::table('assets')->updateOrInsert(
                ['symbol' => $asset['symbol']],
                [
                    'name' => $asset['name'],
                    'precision' => $asset['precision'],
                    'type' => $asset['type'],
                    'status' => 'active',
                    'metadata' => json_encode((object) []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedInstruments(): void
    {
        $irrId = DB::table('assets')->where('symbol', 'IRR')->value('id');

        if ($irrId === null) {
            $this->command?->warn('Asset [IRR] not found; skipping TGJU instruments.');

            return;
        }

        foreach (['USD', 'EUR', 'AED', 'TRY', 'MESGHAL', 'GERAM18'] as $base) {
            $baseId = DB::table('assets')->where('symbol', $base)->value('id');

            if ($baseId === null) {
                continue;
            }

            DB::table('instruments')->updateOrInsert(
                ['symbol' => strtolower($base).'-irr'],
                [
                    'base_asset_id' => $baseId,
                    'quote_asset_id' => $irrId,
                    'status' => 'active',
                    'metadata' => json_encode((object) []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedProvider(): void
    {
        DB::table('market_providers')->updateOrInsert(
            ['slug' => 'tgju'],
            [
                'name' => 'TGJU',
                'driver' => TgjuDriver::class,
                'base_url' => 'https://call4.tgju.org',
                'description' => 'TGJU reference rates for fiat currencies and gold (IRR).',
                'status' => 'active',
                'is_default' => false,
                'priority' => 10,
                'config' => json_encode([
                    'is_reference' => true,
                    // Reference indicators can remain unchanged between market updates.
                    'max_quote_age_seconds' => 1_800,
                    'rest' => [
                        'timeout' => 10,
                        'rev' => self::REV,
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ], 
        );

        $providerId = DB::table('market_providers')->where('slug', 'tgju')->value('id');

        // TGJU remote symbols, all quoted in rial.
        $markets = [
            'usd-irr' => 'price_dollar_rl',
            'eur-irr' => 'price_eur',
            'aed-irr' => 'price_aed',
            'try-irr' => 'price_try',
            'mesghal-irr' => 'mesghal',
            'geram18-irr' => 'geram18',
        ];

        foreach ($markets as $instrumentSymbol => $remoteSymbol) {
            $instrumentId = DB::table('instruments')
                ->where('symbol', $instrumentSymbol)
                ->value('id');

            if ($instrumentId === null) {
                $this->command?->warn(sprintf(
                    'Instrument [%s] not found; skipping tgju market %s.',
                    $instrumentSymbol,
                    $remoteSymbol,
                ));

                continue;
            }

            DB::table('provider_markets')->updateOrInsert(
                [
                    'provider_id' => $providerId,
                    'instrument_id' => $instrumentId,
                ],
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
