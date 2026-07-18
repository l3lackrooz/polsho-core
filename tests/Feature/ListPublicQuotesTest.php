<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Stores\AggregateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPublicQuotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_a_canonical_aggregate_for_a_legacy_lowercase_instrument_symbol(): void
    {
        $base = Asset::query()->create(['symbol' => 'USD', 'name' => 'US Dollar']);
        $quote = Asset::query()->create(['symbol' => 'IRR', 'name' => 'Iranian Rial']);

        Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'usd-irr',
            'status' => 'active',
        ]);

        $store = new class extends AggregateStore
        {
            /** @var string[] */
            public array $requestedInstruments = [];

            public function get(string $instrument): ?array
            {
                $this->requestedInstruments[] = $instrument;

                return [
                    'instrument' => $instrument,
                    'best_bid' => null,
                    'best_ask' => null,
                    'providers' => [],
                    'timestamp' => 1_784_048_000_000,
                ];
            }
        };

        $this->app->instance(AggregateStore::class, $store);

        $response = $this->getJson('/api/pub/quotes?instruments=USD-IRR');

        $response
            ->assertOk()
            ->assertJsonPath('meta.missing', [])
            ->assertJsonPath('data.0.instrument', 'USD-IRR');

        $this->assertSame(['USD-IRR'], $store->requestedInstruments);
    }

    public function test_attaches_current_provider_translations_to_a_cached_quote(): void
    {
        $base = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether']);
        $quote = Asset::query()->create(['symbol' => 'IRT', 'name' => 'Iran Toman']);
        Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'USDT-IRT',
            'status' => 'active',
        ]);
        MarketProvider::query()->create([
            'name' => 'Nobitex',
            'slug' => 'nobitex',
            'driver' => 'Tests\\NobitexDriver',
            'base_url' => 'https://example.test',
            'homepage_url' => 'https://nobitex.ir',
            'translations' => ['fa' => 'نوبیتکس'],
        ]);

        $store = new class extends AggregateStore
        {
            public function get(string $instrument): ?array
            {
                return [
                    'instrument' => $instrument,
                    'best_bid' => null,
                    'best_ask' => null,
                    'providers' => [],
                    'comparison_providers' => [[
                        'provider' => 'nobitex',
                        'is_reference' => false,
                        'bid' => null,
                        'ask' => null,
                        'timestamp' => null,
                    ]],
                    'timestamp' => 1_784_048_000_000,
                ];
            }
        };
        $this->app->instance(AggregateStore::class, $store);

        $this->getJson('/api/pub/quotes?instruments=USDT-IRT')
            ->assertOk()
            ->assertJsonPath('data.0.comparison_providers.0.provider_name', 'Nobitex')
            ->assertJsonPath(
                'data.0.comparison_providers.0.provider_homepage_url',
                'https://nobitex.ir',
            )
            ->assertJsonPath(
                'data.0.comparison_providers.0.provider_translations.fa',
                'نوبیتکس',
            );
    }
}
