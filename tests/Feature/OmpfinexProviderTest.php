<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Aggregation\LatestQuoteAggregator;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Seeders\NewProvidersSeeder;
use App\Domain\Market\Infrastructure\Providers\Ompfinex\OmpfinexClient;
use App\Domain\Market\Infrastructure\Providers\Ompfinex\OmpfinexMapper;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OmpfinexProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_markets_use_real_books_and_convert_rials_to_tomans(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $assets = [];
        foreach (['BTC', 'USDT', 'IRT'] as $symbol) {
            $assets[$symbol] = Asset::query()->create(['symbol' => $symbol, 'name' => $symbol]);
        }
        foreach ([['BTC', 'IRT'], ['BTC', 'USDT'], ['USDT', 'IRT']] as [$base, $quote]) {
            Instrument::query()->create([
                'base_asset_id' => $assets[$base]->id, 'quote_asset_id' => $assets[$quote]->id,
                'symbol' => strtolower($base.'-'.$quote), 'status' => 'active',
            ]);
        }
        $this->seed(NewProvidersSeeder::class);
        $provider = MarketProvider::where('slug', 'ompfinex')->firstOrFail();
        $this->assertSame('active', $provider->status);
        $this->assertEqualsCanonicalizing(['1', '14', '9'], $provider->markets()->pluck('remote_symbol')->all());

        $markets = [$this->market(1, 'BTC', 'IRR', '220000000000'), $this->market(14, 'BTC', 'USDT', '83000'), $this->market(9, 'USDT', 'IRR', '2650000')];
        Http::fake([
            'https://api.ompfinex.com/v1/market' => Http::response(['status' => 'OK', 'data' => $markets]),
            'https://api.ompfinex.com/v1/orderbook' => Http::response(['status' => 'OK', 'data' => [
                'BTCIRR' => $this->book('219999999990', '220000000010'),
                'BTCUSDT' => $this->book('82999', '83001'),
                'USDTIRR' => $this->book('2649990', '2650010'),
            ]]),
        ]);

        $quotes = app(ProviderFactory::class)->make($provider)->fetchPrices($provider->markets()->with('instrument')->get());

        Http::assertSentCount(2);
        $this->assertCount(3, $quotes);
        $store = $this->mock(LatestQuoteStore::class);
        $expected = ['BTC-IRT' => 22000000000.0, 'BTC-USDT' => 83000.0, 'USDT-IRT' => 265000.0];
        foreach ($quotes as $quote) {
            $this->assertSame($expected[$quote->instrument], $quote->last);
            $this->assertSame($quote->last - 1, $quote->bid);
            $this->assertSame($quote->last + 1, $quote->ask);
            $this->assertSame(now()->getTimestampMs(), $quote->timestamp);
            $this->assertNull($quote->volume);
            $this->assertDatabaseHas('provider_markets', ['id' => $quote->providerMarketId, 'provider_id' => $provider->id]);
            $store->shouldReceive('getAll')->with($quote->instrument)->andReturn(['ompfinex' => $quote->toArray()]);
            $aggregate = app(LatestQuoteAggregator::class)->aggregateInstrument($quote->instrument);
            $this->assertSame('ompfinex', $aggregate->bestBid?->provider);
            $this->assertSame('ompfinex', $aggregate->bestAsk?->provider);
        }
    }

    public function test_book_sides_are_selected_by_price_and_rial_instruments_keep_rial_units(): void
    {
        $subscription = new MarketSubscriptionDTO('USDT-IRR', '9', 'USDT', 'IRR', 42);
        $book = [
            'asks' => [['price' => '2649000', 'amount' => '1'], ['price' => '2649900', 'amount' => '2'], ['price' => '9999999', 'amount' => '0']],
            'bids' => [['price' => '2651000', 'amount' => '1'], ['price' => '2650100', 'amount' => '2']],
        ];
        $quotes = (new OmpfinexMapper)->mapSnapshot([$this->market(9, 'USDT', 'IRR', '2650000')], ['USDTIRR' => $book], ['9' => $subscription], 'ompfinex');
        $this->assertSame(2649900.0, $quotes[0]->bid);
        $this->assertSame(2650100.0, $quotes[0]->ask);
        $this->assertSame(2650000.0, $quotes[0]->last);
        $this->assertSame(42, $quotes[0]->providerMarketId);
        $this->assertSame([], $subscription->metadata);
    }

    public function test_missing_invalid_or_crossed_books_never_use_last_trade_as_a_quote(): void
    {
        $subscription = new MarketSubscriptionDTO('USDT-IRT', '9', 'USDT', 'IRT', 42);
        foreach ([[], ['asks' => [['price' => '2650000', 'amount' => '1']]],
            $this->book('2650100', '2649900'), $this->book('2650000', '2650000'),
            $this->book('invalid', '2650100'), $this->book('0', '2650100'),
        ] as $book) {
            $this->assertSame([], (new OmpfinexMapper)->mapSnapshot(
                [$this->market(9, 'USDT', 'IRR', '2650000')], ['USDTIRR' => $book], ['9' => $subscription], 'ompfinex',
            ));
        }
        $this->assertSame([], (new OmpfinexMapper)->mapSnapshot(
            [$this->market(9, 'BTC', 'IRR', '220000000000')], ['BTCIRR' => $this->book('219999999990', '220000000010')], ['9' => $subscription], 'ompfinex',
        ));
    }

    #[DataProvider('invalidResponses')]
    public function test_client_rejects_failed_and_malformed_responses(string $method, mixed $body, int $status): void
    {
        Http::fake(['api.ompfinex.com/*' => Http::response($body, $status)]);
        $this->expectException(RuntimeException::class);
        (new OmpfinexClient('https://api.ompfinex.com'))->$method();
    }

    public static function invalidResponses(): array
    {
        return [
            ['fetchMarkets', [], 503],
            ['fetchMarkets', ['status' => 'ERROR', 'data' => []], 200],
            ['fetchMarkets', ['status' => 'OK'], 200],
            ['fetchMarkets', ['status' => 'OK', 'data' => ['id' => 1]], 200],
            ['fetchOrderBooks', '<html>unavailable</html>', 200],
            ['fetchOrderBooks', ['status' => 'OK', 'data' => 'invalid'], 200],
            ['fetchOrderBooks', ['status' => 'OK', 'data' => [['asks' => []]]], 200],
        ];
    }

    private function market(int $id, string $base, string $quote, string $price): array
    {
        return ['id' => $id, 'base_currency' => ['id' => $base], 'quote_currency' => ['id' => $quote], 'last_price' => $price, 'is_visible' => true];
    }

    private function book(string $buy, string $sell): array
    {
        return ['asks' => [['price' => $buy, 'amount' => '1']], 'bids' => [['price' => $sell, 'amount' => '1']], '24h_volume' => '1000000000'];
    }
}
