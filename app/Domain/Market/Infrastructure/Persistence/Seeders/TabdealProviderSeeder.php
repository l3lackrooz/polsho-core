<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealDriver;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Database\Seeder;

class TabdealProviderSeeder extends Seeder
{
    public function run(): void
    {
        $provider = MarketProvider::query()->updateOrCreate(
            ['slug' => 'tabdeal'],
            [
                'name' => 'Tabdeal',
                'translations' => ['fa' => 'تبدیل', 'de' => 'Tabdeal'],
                'driver' => TabdealDriver::class,
                'base_url' => 'https://api-web.tabdeal.org',
                'homepage_url' => 'https://tabdeal.org',
                'status' => 'active',
                'is_default' => false,
                'priority' => 6,
                // The dynamic-info feed supplies last prices without an order book.
                'config' => ['rest' => ['timeout' => 10], 'allow_zero_spread' => true],
            ],
        );

        foreach ([
            'btc-irt' => 'BTCIRT',
            'btc-usdt' => 'BTCUSDT',
            'usdt-irt' => 'USDTIRT',
        ] as $instrumentSymbol => $remoteSymbol) {
            $instrument = Instrument::query()
                ->whereRaw('LOWER(symbol) = ?', [$instrumentSymbol])
                ->first();

            if ($instrument === null) {
                $this->command?->warn("Instrument [{$instrumentSymbol}] not found; skipping Tabdeal market {$remoteSymbol}.");

                continue;
            }

            ProviderMarket::query()->updateOrCreate(
                ['provider_id' => $provider->id, 'instrument_id' => $instrument->id],
                [
                    'remote_symbol' => $remoteSymbol,
                    'status' => 'active',
                    'metadata' => [],
                ],
            );
        }

        MarketSubscriptionFactory::forgetProviderMappings('tabdeal');
    }
}
