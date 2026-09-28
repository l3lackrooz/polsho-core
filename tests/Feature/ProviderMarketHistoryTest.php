<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProviderMarketHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function market(): ProviderMarket
    {
        $provider = MarketProvider::create(['name' => 'History exchange', 'slug' => 'history', 'base_url' => 'https://example.test', 'driver' => 'Tests\\FakeDriver', 'status' => 'active']);
        $base = Asset::create(['symbol' => 'USDT', 'name' => 'Tether']);
        $quote = Asset::create(['symbol' => 'IRR', 'name' => 'Rial']);
        $instrument = Instrument::create(['base_asset_id' => $base->id, 'quote_asset_id' => $quote->id, 'symbol' => 'USDT-IRR']);

        return ProviderMarket::create(['provider_id' => $provider->id, 'instrument_id' => $instrument->id, 'remote_symbol' => 'USDTIRT', 'status' => 'active']);
    }

    private function snapshot(ProviderMarket $market, $time, $price = 1000): void
    {
        DB::table('market_snapshots')->insert(['provider_market_id' => $market->id, 'captured_at' => $time, 'bid' => $price, 'ask' => $price + 10, 'last_price' => $price, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_ranges_exclude_old_and_future_points_and_keep_actual_sample_times(): void
    {
        $this->travelTo(now()->setTime(12, 15, 30));
        $market = $this->market();
        foreach ([40 * 24, 10 * 24, 3 * 24, 12, 2] as $hours) {
            $this->snapshot($market, now()->subHours($hours));
        }
        $latest = now()->subMinutes(3);
        $this->snapshot($market, $latest);
        $this->snapshot($market, now()->addHour());
        foreach (['1h' => 1, '24h' => 3, '7d' => 4, '30d' => 5] as $range => $count) {
            $response = $this->getJson("/api/pub/provider-markets/{$market->id}/history?range=$range");
            $response->assertOk()->assertJsonPath('data.range', $range)->assertJsonCount($count, 'data.points')
                ->assertJsonPath('data.latest_timestamp', $latest->getTimestampMs())
                ->assertJsonPath('data.window_end', now()->getTimestampMs());
        }
    }

    public function test_last_sample_per_bucket_retains_buy_sell_and_real_timestamp(): void
    {
        $this->travelTo(now()->setTime(12, 15, 30));
        $market = $this->market();
        $this->snapshot($market, now()->subSeconds(15), 1000);
        $this->snapshot($market, now()->subSeconds(5), 1100);
        $this->getJson("/api/pub/provider-markets/{$market->id}/history?range=1h")
            ->assertOk()->assertJsonCount(1, 'data.points')
            ->assertJsonPath('data.points.0.timestamp', now()->subSeconds(5)->getTimestampMs())
            ->assertJsonPath('data.points.0.buy', 1110)
            ->assertJsonPath('data.points.0.sell', 1100);
    }

    public function test_cached_points_age_out_of_the_selected_window(): void
    {
        $this->travelTo(now()->setTime(12, 15, 30));
        $market = $this->market();
        $this->snapshot($market, now()->subHour()->addSeconds(10));
        $url = "/api/pub/provider-markets/{$market->id}/history?range=1h";
        $this->getJson($url)->assertJsonCount(1, 'data.points');
        $this->travel(20)->seconds();
        $this->getJson($url)->assertJsonCount(0, 'data.points')->assertJsonPath('data.latest_timestamp', null);
    }

    public function test_bucket_uses_latest_valid_quote_even_when_insert_order_differs(): void
    {
        $this->travelTo(now()->setTime(12, 15, 30));
        $market = $this->market();
        $this->snapshot($market, now()->subSeconds(10), 1200);
        // An older quote can be ingested later; ID alone is not recency.
        $this->snapshot($market, now()->subSeconds(20), 1000);
        DB::table('market_snapshots')->insert([
            'provider_market_id' => $market->id, 'captured_at' => now()->subSeconds(5),
            'bid' => 0, 'ask' => -1, 'last_price' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson("/api/pub/provider-markets/{$market->id}/history?range=1h")
            ->assertOk()->assertJsonCount(1, 'data.points')
            ->assertJsonPath('data.points.0.price', 1200)
            ->assertJsonPath('data.points.0.timestamp', now()->subSeconds(10)->getTimestampMs());
    }

    public function test_invalid_range_is_rejected(): void
    {
        $market = $this->market();
        $this->getJson("/api/pub/provider-markets/{$market->id}/history?range=1H")->assertUnprocessable();
    }
}
