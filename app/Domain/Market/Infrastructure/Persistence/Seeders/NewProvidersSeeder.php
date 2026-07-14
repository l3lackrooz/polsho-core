<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Market\Infrastructure\Providers\Bitpin\BitpinDriver;
use App\Domain\Market\Infrastructure\Providers\Ompfinex\OmpfinexDriver;
use App\Domain\Market\Infrastructure\Providers\Wallex\WallexDriver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Wallex, Bitpin and OMPFinex providers plus their provider_markets
 * rows for the existing instruments (btc-irt, btc-usdt, usdt-irt).
 *
 * OMPFinex is seeded as INACTIVE because its market ids could not be verified
 * from outside Iran. Verify with `php artisan market:sync ompfinex --now`,
 * fill in the real market ids below (or via provider:market:add), then set
 * the provider status to active.
 */
class NewProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Wallex',
                'slug' => 'wallex',
                'driver' => WallexDriver::class,
                'base_url' => 'https://api.wallex.ir',
                'status' => 'active',
                'priority' => 2,
                // Wallex quotes toman markets with the TMN suffix.
                'markets' => [
                    'btc-irt' => 'BTCTMN',
                    'btc-usdt' => 'BTCUSDT',
                    'usdt-irt' => 'USDTTMN',
                ],
            ],
            [
                'name' => 'Bitpin',
                'slug' => 'bitpin',
                'driver' => BitpinDriver::class,
                'base_url' => 'https://api.bitpin.ir',
                'status' => 'active',
                'priority' => 3,
                // Bitpin uses underscore symbols (BTC_IRT).
                'markets' => [
                    'btc-irt' => 'BTC_IRT',
                    'btc-usdt' => 'BTC_USDT',
                    'usdt-irt' => 'USDT_IRT',
                ],
            ],
            [
                'name' => 'OMPFinex',
                'slug' => 'ompfinex',
                'driver' => OmpfinexDriver::class,
                'base_url' => 'https://api.ompfinex.com',
                'status' => 'inactive', // enable after verifying market ids from Iran
                'priority' => 4,
                // OMPFinex matches by numeric market id — fill in the real ids.
                'markets' => [
                    // 'btc-irt' => '<market_id>',
                    // 'usdt-irt' => '<market_id>',
                ],
            ],
        ];

        foreach ($providers as $definition) {
            $providerId = DB::table('market_providers')->updateOrInsert(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'driver' => $definition['driver'],
                    'base_url' => $definition['base_url'],
                    'status' => $definition['status'],
                    'is_default' => false,
                    'priority' => $definition['priority'],
                    'config' => json_encode(['rest' => ['timeout' => 10]]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $providerId = DB::table('market_providers')
                ->where('slug', $definition['slug'])
                ->value('id');

            foreach ($definition['markets'] as $instrumentSymbol => $remoteSymbol) {
                $instrumentId = DB::table('instruments')
                    ->where('symbol', $instrumentSymbol)
                    ->value('id');

                if ($instrumentId === null) {
                    $this->command?->warn(sprintf(
                        'Instrument [%s] not found; skipping %s market %s.',
                        $instrumentSymbol,
                        $definition['slug'],
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
                        'metadata' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
