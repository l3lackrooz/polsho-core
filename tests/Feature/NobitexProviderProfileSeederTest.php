<?php

namespace Tests\Feature;

use App\Domain\Market\Infrastructure\Persistence\Seeders\NobitexProviderProfileSeeder;
use App\Domain\Market\Infrastructure\Persistence\Seeders\ProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NobitexProviderProfileSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_nobitex_profile_as_a_draft_without_overwriting_existing_content(): void
    {
        $this->seed(ProviderSeeder::class);
        $this->seed(NobitexProviderProfileSeeder::class);
        $this->seed(NobitexProviderProfileSeeder::class);

        $providerId = DB::table('market_providers')->where('slug', 'nobitex')->value('id');

        $this->assertDatabaseHas('market_provider_profiles', [
            'provider_id' => $providerId,
            'type' => 'exchange',
            'publication_status' => 'draft',
        ]);
        $this->assertSame(1, DB::table('market_provider_profiles')->where('provider_id', $providerId)->count());
    }
}
