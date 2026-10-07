<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Seeders\RamzinexProviderProfileSeeder;
use App\Domain\Market\Infrastructure\Providers\Ramzinex\RamzinexDriver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RamzinexProviderProfileSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_payload_can_be_saved_and_served_in_every_supported_locale(): void
    {
        $provider = $this->provider();
        $this->seed(RamzinexProviderProfileSeeder::class);
        $this->getJson('/api/pub/providers/ramzinex')->assertNotFound();

        $payload = json_decode(file_get_contents(database_path('data/provider-profiles/ramzinex.json')), true, flags: JSON_THROW_ON_ERROR);
        $payload['publication_status'] = 'published';

        $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')
            ->putJson("/api/market/providers/{$provider->id}/profile", $payload)
            ->assertOk();

        foreach (config('content_locales.supported') as $locale) {
            $response = $this->getJson('/api/pub/providers/ramzinex?locale='.$locale)
                ->assertOk()
                ->assertJsonPath('data.summary', $payload['summary'][$locale])
                ->assertJsonPath('data.description', $payload['description'][$locale])
                ->assertJsonPath('data.seo.title', $payload['seo_title'][$locale])
                ->assertJsonPath('data.seo.description', $payload['seo_description'][$locale])
                ->assertJsonPath('data.country_code', 'IR')
                ->assertJsonPath('data.kyc_required', true)
                ->assertJsonPath('data.last_reviewed_at', '2026-10-07');

            foreach ($payload['facts'] as $index => $fact) {
                $response->assertJsonPath("data.facts.{$index}.label", $fact['label'][$locale])
                    ->assertJsonPath("data.facts.{$index}.value", $fact['value'][$locale]);
            }

            foreach ($payload['sources'] as $index => $source) {
                $response->assertJsonPath("data.sources.{$index}.label", $source['label'][$locale]);
            }
        }
    }

    public function test_rerunning_the_seed_preserves_editorial_changes_and_runtime_settings(): void
    {
        $provider = $this->provider();
        $runtime = $provider->fresh()->getAttributes();
        $this->seed(RamzinexProviderProfileSeeder::class);
        $profile = $provider->profile()->firstOrFail();
        $profile->update([
            'summary' => ['fa' => 'متن ویرایش‌شده'],
            'publication_status' => 'published',
            'published_at' => now(),
        ]);
        $edited = $profile->fresh()->getAttributes();

        $this->seed(RamzinexProviderProfileSeeder::class);

        $this->assertSame($edited, $profile->fresh()->getAttributes());
        $this->assertSame($runtime, $provider->fresh()->getAttributes());
        $this->assertDatabaseCount('market_provider_profiles', 1);
    }

    public function test_missing_provider_is_skipped_without_creating_runtime_configuration(): void
    {
        $this->seed(RamzinexProviderProfileSeeder::class);

        $this->assertDatabaseCount('market_provider_profiles', 0);
        $this->assertDatabaseCount('market_providers', 0);
    }

    private function provider(): MarketProvider
    {
        return MarketProvider::query()->create([
            'name' => 'Ramzinex',
            'slug' => 'ramzinex',
            'driver' => RamzinexDriver::class,
            'base_url' => 'https://publicapi.ramzinex.com',
            'status' => 'active',
        ]);
    }
}
