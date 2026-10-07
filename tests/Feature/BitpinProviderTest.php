<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Aggregation\LatestQuoteAggregator;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Providers\Bitpin\BitpinDriver;
use App\Domain\Market\Infrastructure\Providers\Bitpin\BitpinMapper;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BitpinProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_order_books_remain_eligible_even_when_the_last_trade_is_old(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $provider = $this->provider();
        $tickers = [];
        $responses = [];
        foreach (['BTC_IRT' => 22000000000, 'BTC_USDT' => 83000, 'USDT_IRT' => 265000] as $symbol => $price) {
            $tickers[] = ['symbol' => $symbol, 'price' => (string) $price, 'timestamp' => now()->subHours(2)->timestamp];
            $responses['https://api.bitpin.ir/api/v1/mth/orderbook/'.$symbol.'/'] = Http::response([
                'bids' => [[(string) ($price - 1), '10']],
                'asks' => [[(string) ($price + 1), '20']],
            ]);
        }
        $responses['https://api.bitpin.ir/api/v1/mkt/tickers/'] = Http::response($tickers);
        Http::fake($responses);

        $quotes = app(ProviderFactory::class)->make($provider)->fetchPrices($provider->markets()->with('instrument')->get());

        $this->assertCount(3, $quotes);
        Http::assertSentCount(4);
        $store = $this->mock(LatestQuoteStore::class);
        foreach ($quotes as $quote) {
            $this->assertSame(now()->getTimestampMs(), $quote->timestamp);
            $this->assertSame($quote->last - 1, $quote->bid);
            $this->assertSame($quote->last + 1, $quote->ask);
            $this->assertNotNull($quote->providerMarketId);
            $store->shouldReceive('getAll')->with($quote->instrument)->andReturn(['bitpin' => $quote->toArray()]);
            $aggregate = app(LatestQuoteAggregator::class)->aggregateInstrument($quote->instrument);
            $this->assertSame('bitpin', $aggregate->bestBid?->provider);
            $this->assertSame('bitpin', $aggregate->bestAsk?->provider);
        }
    }

    public function test_failed_order_book_is_skipped_while_other_markets_still_update(): void
    {
        Http::preventStrayRequests();
        $provider = $this->provider();
        Http::fake([
            'https://api.bitpin.ir/api/v1/mkt/tickers/' => Http::response([
                ['symbol' => 'BTC_IRT', 'price' => '22000000000'],
                ['symbol' => 'USDT_IRT', 'price' => '265000'],
            ]),
            'https://api.bitpin.ir/api/v1/mth/orderbook/BTC_IRT/' => Http::response([], 503),
            'https://api.bitpin.ir/api/v1/mth/orderbook/BTC_USDT/' => Http::response([], 503),
            'https://api.bitpin.ir/api/v1/mth/orderbook/USDT_IRT/' => Http::response([
                'bids' => [['264999', '10']], 'asks' => [['265001', '20']],
            ]),
        ]);

        $quotes = app(ProviderFactory::class)->make($provider)->fetchPrices($provider->markets()->with('instrument')->get());

        $this->assertCount(1, $quotes);
        $this->assertSame('USDT-IRT', $quotes[0]->instrument);
        $this->assertSame(264999.0, $quotes[0]->bid);
        $this->assertSame(265001.0, $quotes[0]->ask);
    }

    public function test_invalid_books_never_fall_back_to_the_last_trade(): void
    {
        $subscription = new MarketSubscriptionDTO('USDT-IRT', 'USDT_IRT', 'USDT', 'IRT', 6);
        $mapper = new BitpinMapper;
        foreach ([[], ['asks' => [['265001', '1']]], ['bids' => [['264999', '1']]],
            ['bids' => [['0', '1']], 'asks' => [['265001', '1']]],
            ['bids' => [['invalid', '1']], 'asks' => [['265001', '1']]],
            ['bids' => [['265001', '1']], 'asks' => [['264999', '1']]],
            ['bids' => [['265000', '1']], 'asks' => [['265000', '1']]],
        ] as $book) {
            $this->assertSame([], $mapper->mapSnapshot(
                [['symbol' => 'USDT_IRT', 'price' => '265000']],
                ['USDT_IRT' => $book],
                ['USDT_IRT' => $subscription],
                'bitpin',
            ));
        }
    }

    private function provider(): MarketProvider
    {
        $provider = MarketProvider::query()->create([
            'name' => 'Bitpin', 'slug' => 'bitpin', 'status' => 'active',
            'driver' => BitpinDriver::class, 'base_url' => 'https://api.bitpin.ir',
        ]);
        $assets = [];
        foreach (['BTC', 'USDT', 'IRT'] as $symbol) {
            $assets[$symbol] = Asset::query()->create(['symbol' => $symbol, 'name' => $symbol]);
        }
        foreach ([['BTC', 'IRT'], ['BTC', 'USDT'], ['USDT', 'IRT']] as [$base, $quote]) {
            $instrument = Instrument::query()->create([
                'base_asset_id' => $assets[$base]->id, 'quote_asset_id' => $assets[$quote]->id,
                'symbol' => $base.'-'.$quote, 'status' => 'active',
            ]);
            $provider->markets()->create([
                'instrument_id' => $instrument->id, 'remote_symbol' => $base.'_'.$quote, 'status' => 'active',
            ]);
        }

        return $provider;
    }
}
