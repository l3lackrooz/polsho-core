<?php

namespace Database\Seeders;

use App\Domain\Asset\Infrastructure\Persistence\Seeders\AssetSeeder;
use App\Domain\Market\Infrastructure\Persistence\Seeders\InstrumentSeeder;
use App\Domain\Market\Infrastructure\Persistence\Seeders\NewProvidersSeeder;
use App\Domain\Market\Infrastructure\Persistence\Seeders\ProviderSeeder;
use App\Domain\Market\Infrastructure\Persistence\Seeders\TgjuProviderSeeder;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            AssetSeeder::class,
            ProviderSeeder::class,
            InstrumentSeeder::class,
            NewProvidersSeeder::class,
            TgjuProviderSeeder::class,
        ]);
    }
}
