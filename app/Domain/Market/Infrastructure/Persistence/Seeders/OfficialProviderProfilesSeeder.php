<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Database\Seeder;

class OfficialProviderProfilesSeeder extends Seeder
{
    public const SLUGS = ['ok-ex', 'wallex', 'bitpin', 'nobitex', 'ompfinex', 'tabdeal', 'tgju'];

    public function run(): void
    {
        foreach (static::SLUGS as $slug) {
            $provider = MarketProvider::query()->where('slug', $slug)->first();

            if ($provider === null) {
                $this->command?->warn("{$slug} provider was not found; skipping its profile seed.");

                continue;
            }

            $attributes = json_decode(
                file_get_contents(database_path("data/provider-profiles/{$slug}.json")),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            // Preserve Backoffice edits and runtime settings; only create missing drafts.
            $provider->profile()->firstOrCreate([], $attributes);
        }
    }
}
