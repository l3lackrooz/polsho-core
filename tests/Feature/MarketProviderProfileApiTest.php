<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketProviderProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_only_returns_published_profiles_for_active_providers(): void
    {
        $published = $this->provider(['slug' => 'tala', 'translations' => ['fa' => 'طلا', 'en' => 'Tala']]);
        $published->profile()->create([
            'publication_status' => 'published',
            'summary' => ['fa' => 'خلاصه فارسی', 'en' => 'English summary'],
            'description' => ['fa' => 'توضیحات فارسی'],
            'published_at' => now(),
        ]);

        $draft = $this->provider(['slug' => 'draft-provider']);
        $draft->profile()->create(['publication_status' => 'draft']);

        $inactive = $this->provider(['slug' => 'inactive-provider', 'status' => 'inactive']);
        $inactive->profile()->create(['publication_status' => 'published', 'published_at' => now()]);

        $response = $this->getJson('/api/pub/providers?locale=fa');

        $response->assertOk()
            ->assertJsonPath('data.0.slug', 'tala')
            ->assertJsonPath('data.0.name', 'طلا')
            ->assertJsonPath('data.0.summary', 'خلاصه فارسی')
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['driver' => 'Tests\\TalaDriver'])
            ->assertJsonMissing(['base_url' => 'https://private-api.example.test']);
    }

    public function test_public_detail_hides_drafts_and_inactive_providers(): void
    {
        $draft = $this->provider(['slug' => 'draft-provider']);
        $draft->profile()->create(['publication_status' => 'draft']);

        $inactive = $this->provider(['slug' => 'inactive-provider', 'status' => 'inactive']);
        $inactive->profile()->create(['publication_status' => 'published', 'published_at' => now()]);

        $this->getJson('/api/pub/providers/draft-provider')->assertNotFound();
        $this->getJson('/api/pub/providers/inactive-provider')->assertNotFound();
    }

    public function test_admin_can_publish_a_profile_without_changing_runtime_provider_settings(): void
    {
        $provider = $this->provider();
        $admin = User::factory()->create(['is_admin' => true]);

        $payload = [
            'type' => 'exchange',
            'publication_status' => 'published',
            'summary' => ['fa' => 'خلاصه', 'en' => 'Summary'],
            'description' => ['fa' => 'توضیحات کامل', 'en' => 'Full description'],
            'seo_title' => ['fa' => 'پروفایل طلا'],
            'seo_description' => ['fa' => 'اطلاعات صرافی طلا'],
            'legal_name' => ' Tala Exchange ',
            'country_code' => 'ir',
            'founded_year' => 2020,
            'kyc_required' => true,
            'fee_url' => 'https://tala.example.test/fees/',
            'support_url' => 'https://tala.example.test/support',
            'facts' => [[
                'key' => 'settlement',
                'label' => ['fa' => 'تسویه', 'en' => 'Settlement', 'de' => 'Abwicklung'],
                'value' => ['fa' => 'فوری', 'en' => 'Instant', 'de' => 'Sofort'],
            ]],
            'sources' => [[
                'label' => ['fa' => 'کارمزد رسمی', 'en' => 'Official fees', 'de' => 'Offizielle Gebühren'],
                'url' => 'https://tala.example.test/fees',
                'published_at' => '2026-07-01',
            ]],
            'last_reviewed_at' => '2026-07-24',
        ];

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/market/providers/{$provider->id}/profile", $payload)
            ->assertOk()
            ->assertJsonPath('data.publication_status', 'published')
            ->assertJsonPath('data.country_code', 'IR')
            ->assertJsonPath('data.fee_url', 'https://tala.example.test/fees');

        $provider->refresh();
        $this->assertSame('Tests\\TalaDriver', $provider->driver);
        $this->assertSame('https://private-api.example.test', $provider->base_url);

        $this->getJson('/api/pub/providers/tala?locale=en')
            ->assertOk()
            ->assertJsonPath('data.name', 'Tala')
            ->assertJsonPath('data.summary', 'Summary')
            ->assertJsonPath('data.description', 'Full description')
            ->assertJsonPath('data.country_code', 'IR')
            ->assertJsonPath('data.links.fees', 'https://tala.example.test/fees')
            ->assertJsonPath('data.facts.0.label', 'Settlement')
            ->assertJsonPath('data.facts.0.value', 'Instant')
            ->assertJsonPath('data.sources.0.label', 'Official fees')
            ->assertJsonMissing(['driver' => 'Tests\\TalaDriver'])
            ->assertJsonMissing(['base_url' => 'https://private-api.example.test']);
    }

    public function test_public_detail_localizes_facts_and_sources_and_falls_back_to_english(): void
    {
        $provider = $this->provider(['translations' => ['fa' => 'طلا', 'en' => 'Tala']]);
        $provider->profile()->create([
            'publication_status' => 'published',
            'summary' => ['fa' => 'خلاصه', 'en' => 'English summary', 'de' => 'Deutsche Zusammenfassung'],
            'facts' => [[
                'key' => 'settlement',
                'label' => ['fa' => 'تسویه', 'en' => 'Settlement', 'de' => 'Abwicklung'],
                'value' => ['fa' => 'فوری', 'en' => 'Instant', 'de' => 'Sofort'],
            ]],
            'sources' => [[
                'label' => ['fa' => 'وب‌سایت رسمی', 'en' => 'Official website', 'de' => 'Offizielle Website'],
                'url' => 'https://tala.example.test',
            ]],
            'published_at' => now(),
        ]);

        $this->getJson('/api/pub/providers/tala?locale=de-DE')
            ->assertOk()
            ->assertJsonPath('data.summary', 'Deutsche Zusammenfassung')
            ->assertJsonPath('data.facts.0.label', 'Abwicklung')
            ->assertJsonPath('data.facts.0.value', 'Sofort')
            ->assertJsonPath('data.sources.0.label', 'Offizielle Website');

        $this->getJson('/api/pub/providers/tala?locale=tr')
            ->assertOk()
            ->assertJsonPath('data.summary', 'English summary')
            ->assertJsonPath('data.facts.0.label', 'Settlement')
            ->assertJsonPath('data.sources.0.label', 'Official website');
    }

    public function test_non_admin_cannot_manage_provider_profiles(): void
    {
        $provider = $this->provider();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/market/providers/{$provider->id}/profile", [
                'publication_status' => 'published',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_only_store_configured_profile_locales(): void
    {
        $provider = $this->provider();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/market/providers/{$provider->id}/profile", [
                'summary' => ['es' => 'Resumen'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['summary']);
    }

    public function test_admin_can_force_mobile_provider_profile_cache_invalidation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $before = $this->getJson('/api/pub/app-status?platform=ios&version=1.0.0&build=1')
            ->assertOk()
            ->json('data.provider_profiles_version');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/market/provider-profiles/cache/refresh')
            ->assertOk()
            ->assertJsonStructure(['data' => ['version']]);

        $after = $this->getJson('/api/pub/app-status?platform=ios&version=1.0.0&build=1')
            ->assertOk()
            ->json('data.provider_profiles_version');

        $this->assertNotSame($before, $after);
    }

    /** @param array<string, mixed> $overrides */
    private function provider(array $overrides = []): MarketProvider
    {
        $attributes = array_merge([
            'slug' => 'tala',
            'base_url' => 'https://private-api.example.test',
            'homepage_url' => 'https://tala.example.test',
            'status' => 'active',
        ], $overrides);
        $attributes['name'] ??= str_replace('-', ' ', ucwords($attributes['slug'], '-'));
        $attributes['driver'] ??= 'Tests\\'.str_replace('-', '', ucwords($attributes['slug'], '-')).'Driver';

        return MarketProvider::query()->create($attributes);
    }
}
