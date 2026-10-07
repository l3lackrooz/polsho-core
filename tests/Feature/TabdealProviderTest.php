<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Seeders\TabdealProviderSeeder;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealClient;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealDriver;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealMapper;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TabdealProviderTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://api-web.tabdeal.org/r/plots/currencies/dynamic-info/';

    public function test_seeded_provider_fetches_all_mapped_markets_in_one_request(): void
    {
        Http::preventStrayRequests();
        $this->freezeTime();
        $btc = Asset::query()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
        $usdt = Asset::query()->create(['symbol' => 'USDT', 'name' => 'Tether']);
        $irt = Asset::query()->create(['symbol' => 'IRT', 'name' => 'Toman']);
        foreach ([[$btc, $irt], [$btc, $usdt], [$usdt, $irt]] as [$base, $quote]) {
            Instrument::query()->create([
                'base_asset_id' => $base->id,
                'quote_asset_id' => $quote->id,
                'symbol' => strtolower($base->symbol.'-'.$quote->symbol),
                'status' => 'active',
            ]);
        }

        // Seeding must invalidate even an already-cached empty mapping.
        \Illuminate\Support\Facades\Cache::put('market.subscription.v2.provider.tabdeal', [
            'by_instrument' => [], 'by_remote_symbol' => [],
        ]);
        $this->seed(TabdealProviderSeeder::class);
        $this->seed(TabdealProviderSeeder::class);
        $this->assertDatabaseCount('market_providers', 1);
        $this->assertDatabaseCount('provider_markets', 3);
        $provider = MarketProvider::query()->where('slug', 'tabdeal')->firstOrFail();
        $this->assertSame('active', $provider->status);
        $this->assertSame('تبدیل', $provider->translations['fa']);
        $this->assertSame(TabdealDriver::class, $provider->driver);
        Http::fake([self::URL => Http::response(['currencies' => [
            'BTC' => [
                'IRT' => ['price' => '21982777473', 'high_24' => '23421321000'],
                'USDT' => ['price' => '83815.57'],
            ],
            'USDT' => ['IRT' => ['price' => '262749']],
            'ETH' => ['IRT' => ['price' => '677480032']],
        ]])]);

        $driver = app(ProviderFactory::class)->make($provider);
        $quotes = $driver->fetchPrices(collect(['btc-irt', 'btc-usdt', 'usdt-irt']));

        $this->assertCount(3, $quotes);
        foreach ($quotes as $quote) {
            $this->assertSame('tabdeal', $quote->provider);
            $this->assertSame($quote->last, $quote->bid);
            $this->assertSame($quote->last, $quote->ask);
            $this->assertNull($quote->volume);
            $this->assertSame(now()->getTimestampMs(), $quote->timestamp);
            $this->assertDatabaseHas('provider_markets', [
                'id' => $quote->providerMarketId,
                'provider_id' => $provider->id,
                'remote_symbol' => str_replace('-', '', $quote->instrument),
            ]);
        }
        $this->assertSame(21982777473.0, $quotes[0]->last);
        $this->assertSame(83815.57, $quotes[1]->last);
        $this->assertSame(262749.0, $quotes[2]->last);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === self::URL && $request->method() === 'GET');
    }

    public function test_empty_subscriptions_do_not_fetch_the_api(): void
    {
        Http::preventStrayRequests();
        $this->assertSame([], $this->driver()->fetchPrices(collect()));
        Http::assertNothingSent();
    }

    #[DataProvider('invalidResponses')]
    public function test_client_rejects_failed_or_malformed_responses(mixed $body, int $status): void
    {
        Http::fake([self::URL => Http::response($body, $status)]);
        $this->expectException(RuntimeException::class);
        (new TabdealClient('https://api-web.tabdeal.org'))->fetchTickers(['BTCIRT']);
    }

    public static function invalidResponses(): array
    {
        return [
            'http failure' => [[], 503],
            'missing currencies' => [['detail' => 'unavailable'], 200],
            'invalid currencies' => [['currencies' => 'unavailable'], 200],
            'invalid json' => ['<html>unavailable</html>', 200],
        ];
    }

    public function test_health_check_requires_a_usable_price(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(['currencies' => ['BTC' => ['IRT' => ['price' => '21982777473']]]])
            ->push(['currencies' => []])
            ->push([], 503)]);
        $driver = $this->driver();
        $this->assertTrue($driver->healthCheck());
        $this->assertFalse($driver->healthCheck());
        $this->assertFalse($driver->healthCheck());
    }

    private function driver(): TabdealDriver
    {
        return new TabdealDriver(
            new TabdealClient('https://api-web.tabdeal.org'),
            new TabdealMapper,
            new MarketSubscriptionFactory,
        );
    }
}
