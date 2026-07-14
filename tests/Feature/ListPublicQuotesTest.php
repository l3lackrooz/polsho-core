<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
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
}
