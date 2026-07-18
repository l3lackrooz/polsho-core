<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncMarketProviderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_a_provider_sync_from_backoffice(): void
    {
        Bus::fake();
        $provider = $this->providerWithMarket();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/market/providers/{$provider->id}/sync")
            ->assertOk()
            ->assertJsonPath('message', 'Provider sync queued.');

        Bus::assertDispatched(
            SyncProviderQuotesJob::class,
            fn (SyncProviderQuotesJob $job): bool => true,
        );
    }

    public function test_sync_rejects_inactive_providers(): void
    {
        $provider = $this->providerWithMarket(['status' => 'inactive']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/market/providers/{$provider->id}/sync")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only active providers can be synced.');
    }

    /** @param array<string, mixed> $overrides */
    private function providerWithMarket(array $overrides = []): MarketProvider
    {
        $provider = MarketProvider::query()->create(array_merge([
            'name' => 'Tala.ir',
            'slug' => 'tala',
            'driver' => 'Tests\\FakeTalaDriver',
            'base_url' => 'https://www.tala.ir',
            'status' => 'active',
        ], $overrides));
        $base = Asset::query()->create(['symbol' => 'GERAM18', 'name' => '18K Gold']);
        $quote = Asset::query()->create(['symbol' => 'IRR', 'name' => 'Iranian Rial']);
        $instrument = Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'GERAM18-IRR',
        ]);
        ProviderMarket::query()->create([
            'provider_id' => $provider->id,
            'instrument_id' => $instrument->id,
            'remote_symbol' => 'geram18',
            'status' => 'active',
        ]);

        return $provider;
    }
}
