<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Market\Infrastructure\Providers\Bitpin\BitpinDriver;
use App\Domain\Market\Infrastructure\Providers\OkEx\OkExDriver;
use App\Domain\Market\Infrastructure\Providers\Ompfinex\OmpfinexDriver;
use App\Domain\Market\Infrastructure\Providers\Wallex\WallexDriver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Wallex, Bitpin and OMPFinex providers plus their provider_markets
 * rows for the existing instruments (btc-irt, btc-usdt, usdt-irt).
 *
 * OMPFinex market IDs and order books were verified against the public API.
 */
class NewProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Wallex',
                'translations' => ['fa' => 'والکس', 'de' => 'Wallex'],
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
                'translations' => ['fa' => 'بیت پین', 'de' => 'Bitpin'],
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
                'name' => 'OK-EX',
                'translations' => ['fa' => 'اوکی اکس', 'de' => 'OK-EX'],
                'slug' => 'ok-ex',
                'driver' => OkExDriver::class,
                'base_url' => 'https://sapi.ok-ex.io',
                'status' => 'active',
                'priority' => 5,
                // OK-EX spot uses hyphenated, USDT-quoted symbols.
                'markets' => [
                    'btc-usdt' => 'BTC-USDT',
                ],
            ],
            [
                'name' => 'OMPFinex',
                'translations' => ['fa' => 'او ام پی فینکس', 'de' => 'OMPFinex'],
                'slug' => 'ompfinex',
                'driver' => OmpfinexDriver::class,
                'base_url' => 'https://api.ompfinex.com',
                'status' => 'active',
                'priority' => 4,
                // /v1/market IDs; the mapper converts IRR prices to IRT.
                'markets' => [
                    'btc-irt' => '1',
                    'btc-usdt' => '14',
                    'usdt-irt' => '9',
                ],
            ],
        ];

        foreach ($providers as $definition) {
            $providerId = DB::table('market_providers')->updateOrInsert(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'translations' => json_encode($definition['translations']),
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
