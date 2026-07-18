<?php

namespace Tests\Feature;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingApiTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): MarketProvider
    {
        return MarketProvider::query()->create([
            'name' => 'Nobitex',
            'driver' => 'nobitex-driver',
            'slug' => 'nobitex',
            'base_url' => 'https://example.test',
        ]);
    }

    private function asset(): Asset
    {
        return Asset::query()->create(['symbol' => 'BTC', 'name' => 'Bitcoin', 'type' => 'crypto']);
    }

    public function test_an_admin_uploads_a_content_addressed_provider_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $provider = $this->provider();

        $response = $this->actingAs($admin, 'sanctum')->post(
            "/api/market/providers/{$provider->id}/logo",
            ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)],
        );

        $response->assertOk()->assertJsonPath('success', true);

        $path = $provider->refresh()->logo_path;
        $this->assertMatchesRegularExpression('/^branding\/nobitex\.[0-9a-f]{8}\.png$/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, $response->json('data.logo_url'));
    }

    public function test_replacing_a_logo_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $provider = $this->provider();

        $this->actingAs($admin, 'sanctum')->post(
            "/api/market/providers/{$provider->id}/logo",
            ['logo' => UploadedFile::fake()->image('one.png', 64, 64)],
        )->assertOk();
        $firstPath = $provider->refresh()->logo_path;

        $this->actingAs($admin, 'sanctum')->post(
            "/api/market/providers/{$provider->id}/logo",
            ['logo' => UploadedFile::fake()->image('two.png', 32, 32)],
        )->assertOk();

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($provider->refresh()->logo_path);
    }

    public function test_logo_upload_is_admin_only(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['is_admin' => false]);
        $provider = $this->provider();

        $this->actingAs($user, 'sanctum')->post(
            "/api/market/providers/{$provider->id}/logo",
            ['logo' => UploadedFile::fake()->image('logo.png')],
        )->assertForbidden();
    }

    public function test_the_public_manifest_lists_logos_and_supports_etag_revalidation(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $provider = $this->provider();
        $asset = $this->asset();

        $this->actingAs($admin, 'sanctum')->post(
            "/api/market/providers/{$provider->id}/logo",
            ['logo' => UploadedFile::fake()->image('logo.png', 64, 64)],
        )->assertOk();
        $this->actingAs($admin, 'sanctum')->post(
            "/api/assets/{$asset->id}/logo",
            ['logo' => UploadedFile::fake()->image('btc.png', 64, 64)],
        )->assertOk();

        $first = $this->getJson('/api/pub/branding');
        $first->assertOk()->assertJsonPath('success', true);

        $version = $first->json('data.version');
        $this->assertNotEmpty($version);
        $this->assertSame('"'.$version.'"', $first->headers->get('ETag'));
        $this->assertStringContainsString('branding/nobitex.', $first->json('data.exchanges.nobitex.logo'));
        $this->assertStringContainsString('branding/btc.', $first->json('data.assets.BTC.icon'));

        // Unchanged manifest revalidates as a 304 with an empty body.
        $this->getJson('/api/pub/branding', ['If-None-Match' => '"'.$version.'"'])
            ->assertStatus(304);

        // Removing a logo changes the version, so the old ETag misses.
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/market/providers/{$provider->id}/logo")
            ->assertOk();

        $second = $this->getJson('/api/pub/branding', ['If-None-Match' => '"'.$version.'"']);
        $second->assertOk();
        $this->assertNotSame($version, $second->json('data.version'));
        $this->assertNull($second->json('data.exchanges.nobitex'));
    }

    public function test_app_status_carries_the_branding_version(): void
    {
        $response = $this->getJson('/api/pub/app-status?platform=ios&version=1.0.0&build=1');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.branding_version'));
    }
}
