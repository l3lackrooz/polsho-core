<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Asset\Models\Instrument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetBaseCurrencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_manage_an_asset_as_a_display_base_currency(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/assets', [
            'symbol' => 'USDT',
            'name' => 'Tether',
            'precision' => 6,
            'type' => 'crypto',
            'is_base_currency' => true,
            'translations' => ['fa' => 'تتر', 'de' => 'Tether'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.symbol', 'USDT')
            ->assertJsonPath('data.is_base_currency', true)
            ->assertJsonPath('data.translations.fa', 'تتر');

        $asset = Asset::query()->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/assets/{$asset->id}", ['is_base_currency' => false])
            ->assertOk()
            ->assertJsonPath('data.is_base_currency', false);

        $this->assertFalse($asset->fresh()->is_base_currency);
    }

    public function test_public_instruments_expose_the_base_currency_flag_for_their_assets(): void
    {
        $base = Asset::query()->create([
            'symbol' => 'USDT',
            'name' => 'Tether',
            'type' => 'crypto',
            'translations' => ['fa' => 'تتر'],
        ]);
        $quote = Asset::query()->create([
            'symbol' => 'IRT',
            'name' => 'Iran Toman',
            'type' => 'fiat',
            'is_base_currency' => true,
        ]);
        Instrument::query()->create([
            'base_asset_id' => $base->id,
            'quote_asset_id' => $quote->id,
            'symbol' => 'USDT-IRT',
        ]);

        $this->getJson('/api/pub/instruments?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.base_asset.is_base_currency', false)
            ->assertJsonPath('data.0.base_asset.translations.fa', 'تتر')
            ->assertJsonPath('data.0.quote_asset.is_base_currency', true);
    }
}
