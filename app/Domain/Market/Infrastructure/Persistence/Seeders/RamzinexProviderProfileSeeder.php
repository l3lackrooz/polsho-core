<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Database\Seeder;

class RamzinexProviderProfileSeeder extends Seeder
{
    public function run(): void
    {
        $provider = MarketProvider::query()->where('slug', 'ramzinex')->first();

        if ($provider === null) {
            $this->command?->warn('Ramzinex provider was not found; skipping its profile seed.');

            return;
        }

        $attributes = json_decode(
            file_get_contents(database_path('data/provider-profiles/ramzinex.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        // Match the existing editorial seed policy: create a draft once and
        // preserve any profile already maintained through Backoffice.
        $provider->profile()->firstOrCreate([], $attributes);
    }
}
