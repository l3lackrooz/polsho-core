<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealDriver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparisonProviderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_only_includes_opted_in_active_non_reference_providers_for_the_requested_market(): void
    {
        $this->provider('enabled', ['comparison_enabled' => true, 'demo_enabled' => true]);
        $this->provider('comparison-only', ['comparison_enabled' => true]);
        $this->provider('opted-out');
        $this->provider('inactive', ['comparison_enabled' => true, 'status' => 'inactive']);
        $this->provider('reference', ['comparison_enabled' => true, 'config' => ['is_reference' => true]]);
        $this->provider('other-market', ['comparison_enabled' => true], 'BTC-IRT');
        $disabledMarket = $this->provider('disabled-market', ['comparison_enabled' => true]);
        $disabledMarket->markets()->update(['status' => 'inactive']);

        $response = $this->getJson('/api/pub/comparison-providers?instrument=usdt-irt')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.instrument', 'USDT-IRT')->assertJsonCount(2, 'data.providers');
        $directory = collect($response->json('data.providers'))->keyBy('slug');
        $this->assertTrue($directory['enabled']['demo_enabled']);
        $this->assertFalse($directory['comparison-only']['demo_enabled']);
        $this->assertSame(['fa' => 'صرافی نمونه'], $directory['enabled']['translations']);
        $this->assertArrayNotHasKey('config', $directory['enabled']);
        $this->assertArrayNotHasKey('driver', $directory['enabled']);
        $this->assertArrayNotHasKey('base_url', $directory['enabled']);

        Instrument::query()->where('symbol', 'USDT-IRT')->update(['status' => 'inactive']);
        $this->getJson('/api/pub/comparison-providers')->assertOk()->assertJsonCount(0, 'data.providers');
    }

    public function test_toman_catalog_includes_rial_markets_without_changing_their_source_mapping(): void
    {
        $rial = $this->provider('rial-exchange', ['comparison_enabled' => true], 'USDT-IRR');
        $this->provider('toman-exchange', ['comparison_enabled' => true]);
        $this->provider('foreign-exchange', ['comparison_enabled' => true], 'USDT-USD');
        $this->getJson('/api/pub/comparison-providers?instrument=USDT-IRT')
            ->assertOk()->assertJsonPath('data.instrument', 'USDT-IRT')->assertJsonCount(2, 'data.providers');
        $this->assertSame('USDT-IRR', $rial->markets->first()->instrument->symbol);
        $rial->markets()->update(['status' => 'inactive']);
        $this->getJson('/api/pub/comparison-providers?instrument=USDT-IRT')->assertJsonCount(1, 'data.providers');
        $this->getJson('/api/pub/comparison-providers?instrument=USDT-USD')->assertJsonCount(1, 'data.providers');
    }

    public function test_admin_can_toggle_comparison_and_demo_independently_without_changing_provider_status(): void
    {
        $provider = $this->provider('tabdeal');
        $admin = User::factory()->create(['is_admin' => true]);
        $url = "/api/market/providers/{$provider->id}";
        $this->actingAs($admin, 'sanctum')->putJson($url, ['comparison_enabled' => true])
            ->assertOk()->assertJsonPath('data.comparison_enabled', true)
            ->assertJsonPath('data.demo_enabled', false)->assertJsonPath('data.status', 'active');
        $this->getJson('/api/pub/comparison-providers')->assertJsonCount(1, 'data.providers');
        $this->putJson($url, ['demo_enabled' => true])->assertOk()
            ->assertJsonPath('data.comparison_enabled', true)->assertJsonPath('data.demo_enabled', true);
        $this->putJson($url, ['priority' => 42])->assertOk()
            ->assertJsonPath('data.comparison_enabled', true)->assertJsonPath('data.demo_enabled', true);
        $this->putJson($url, ['comparison_enabled' => false])->assertOk()
            ->assertJsonPath('data.demo_enabled', true)->assertJsonPath('data.status', 'active');
        $this->getJson('/api/pub/comparison-providers')->assertJsonCount(0, 'data.providers');
        $this->assertSame(['private_token' => 'not-public'], $provider->refresh()->config);
    }

    public function test_creation_saves_flags_and_omitted_flags_default_to_false(): void
    {
        $this->assertFalse($this->provider('default')->refresh()->comparison_enabled);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')
            ->postJson('/api/market/providers', [
                'name' => 'New exchange', 'slug' => 'new-exchange', 'driver' => TabdealDriver::class,
                'base_url' => 'https://example.test', 'comparison_enabled' => true, 'demo_enabled' => true,
            ])->assertCreated()->assertJsonPath('data.comparison_enabled', true)->assertJsonPath('data.demo_enabled', true);
    }

    public function test_flags_are_admin_only_and_validated(): void
    {
        $provider = $this->provider('protected');
        $url = "/api/market/providers/{$provider->id}";
        $this->putJson($url, ['comparison_enabled' => true])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]), 'sanctum')
            ->putJson($url, ['demo_enabled' => true])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')
            ->putJson($url, ['comparison_enabled' => 'maybe', 'demo_enabled' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['comparison_enabled', 'demo_enabled']);
        $this->getJson('/api/pub/comparison-providers?instrument=invalid')->assertUnprocessable();
    }

    private function provider(string $slug, array $attributes = [], string $symbol = 'USDT-IRT'): MarketProvider
    {
        $provider = MarketProvider::query()->create(array_merge([
            'name' => $slug, 'slug' => $slug, 'translations' => ['fa' => 'صرافی نمونه'],
            'driver' => "Tests\\{$slug}Driver", 'base_url' => 'https://example.test',
            'status' => 'active', 'config' => ['private_token' => 'not-public'],
        ], $attributes));
        [$baseSymbol, $quoteSymbol] = explode('-', $symbol);
        $base = Asset::query()->firstOrCreate(['symbol' => $baseSymbol], ['name' => $baseSymbol]);
        $quote = Asset::query()->firstOrCreate(['symbol' => $quoteSymbol], ['name' => $quoteSymbol]);
        $instrument = Instrument::query()->firstOrCreate(['symbol' => $symbol], [
            'base_asset_id' => $base->id, 'quote_asset_id' => $quote->id, 'status' => 'active',
        ]);
        ProviderMarket::query()->create([
            'provider_id' => $provider->id, 'instrument_id' => $instrument->id,
            'remote_symbol' => str_replace('-', '', $symbol), 'status' => 'active',
        ]);

        return $provider;
    }
}
