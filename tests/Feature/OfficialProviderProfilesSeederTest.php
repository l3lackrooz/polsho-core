<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Seeders\OfficialProviderProfilesSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfficialProviderProfilesSeederTest extends TestCase
{
    use RefreshDatabase;

    public static function providers(): array
    {
        return array_map(fn ($slug) => [$slug], OfficialProviderProfilesSeeder::SLUGS);
    }

    #[DataProvider('providers')]
    public function test_complete_profiles_validate_and_render_in_all_locales(string $slug): void
    {
        $provider = MarketProvider::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'driver' => 'test',
            'base_url' => 'https://example.com',
            'status' => 'active',
        ]);
        $runtime = $provider->fresh()->getAttributes();
        $this->seed(OfficialProviderProfilesSeeder::class);
        $this->getJson('/api/pub/providers/'.$slug)->assertNotFound();

        $payload = json_decode(file_get_contents(database_path("data/provider-profiles/{$slug}.json")), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('draft', $payload['publication_status']);
        $payload['publication_status'] = 'published';
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')
            ->putJson("/api/market/providers/{$provider->id}/profile", $payload)
            ->assertOk();

        foreach (config('content_locales.supported') as $locale) {
            $response = $this->getJson("/api/pub/providers/{$slug}?locale={$locale}")
                ->assertOk()
                ->assertJsonPath('data.summary', $payload['summary'][$locale])
                ->assertJsonPath('data.description', $payload['description'][$locale])
                ->assertJsonPath('data.seo.title', $payload['seo_title'][$locale])
                ->assertJsonPath('data.seo.description', $payload['seo_description'][$locale])
                ->assertJsonPath('data.type', $slug === 'tgju' ? 'reference_source' : 'exchange')
                ->assertJsonPath('data.kyc_required', $slug === 'tgju' ? null : true);

            foreach ($payload['facts'] as $index => $fact) {
                $this->assertLessThanOrEqual(10, mb_strlen($fact['label'][$locale]));
                $this->assertLessThanOrEqual(15, mb_strlen($fact['value'][$locale]));
                $response->assertJsonPath("data.facts.{$index}.label", $fact['label'][$locale])
                    ->assertJsonPath("data.facts.{$index}.value", $fact['value'][$locale]);
            }
            foreach ($payload['sources'] as $index => $source) {
                $response->assertJsonPath("data.sources.{$index}.label", $source['label'][$locale])
                    ->assertJsonPath("data.sources.{$index}.url", rtrim($source['url'], '/'));
            }
        }

        $profile = $provider->profile()->firstOrFail();
        $profile->update(['summary' => ['fa' => 'متن ویرایش‌شده']]);
        $editorial = $profile->fresh()->getAttributes();
        $this->seed(OfficialProviderProfilesSeeder::class);
        $this->assertSame($editorial, $profile->fresh()->getAttributes());
        $this->assertSame($runtime, $provider->fresh()->getAttributes());
        $this->assertDatabaseCount('market_provider_profiles', 1);
    }

    public function test_missing_providers_are_not_created(): void
    {
        $this->seed(OfficialProviderProfilesSeeder::class);
        $this->assertDatabaseCount('market_provider_profiles', 0);
        $this->assertDatabaseCount('market_providers', 0);
    }
}
