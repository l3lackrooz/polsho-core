<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use App\Domain\Market\Infrastructure\Providers\Drivers\NobitexDriver;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('market_providers')->insert([
            [
                'name' => 'Nobitex',
                'slug' => 'nobitex',
                'driver' => NobitexDriver::class,
                'base_url' => 'https://api.nobitex.ir',
                'status' => 'active',
                'config' => json_encode([
                    'api_url' => 'https://api.nobitex.ir'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
