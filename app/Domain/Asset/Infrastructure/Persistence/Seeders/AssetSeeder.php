<?php

namespace App\Domain\Asset\Infrastructure\Persistence\Seeders;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['symbol' => 'USDT', 'name' => 'Tether', 'precision' => 6, 'translations' => ['fa' => 'تتر', 'de' => 'Tether']],
            ['symbol' => 'IRT', 'name' => 'Iran Toman', 'precision' => 0, 'is_base_currency' => true, 'translations' => ['fa' => 'تومان ایران', 'de' => 'Iranischer Toman']],
            ['symbol' => 'IRR', 'name' => 'Iran Rial', 'precision' => 0, 'translations' => ['fa' => 'ریال ایران', 'de' => 'Iranischer Rial']],
            ['symbol' => 'BTC', 'name' => 'Bitcoin', 'precision' => 8, 'translations' => ['fa' => 'بیت کوین', 'de' => 'Bitcoin']],
        ];

        foreach ($items as $item) {
            Asset::firstOrCreate(
                ['symbol' => $item['symbol']],   // Unique condition
                [
                    'name' => $item['name'],
                    'precision' => $item['precision'],
                    'status' => 'active',
                    'is_base_currency' => $item['is_base_currency'] ?? false,
                    'translations' => $item['translations'],
                    'metadata' => [],
                ]
            );
        }
    }
}
