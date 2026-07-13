<?php

namespace App\Domain\Asset\Infrastructure\Persistence\Seeders;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['symbol' => 'USDT', 'name' => 'Tether', 'precision' => 6],
            ['symbol' => 'IRT',  'name' => 'Iran Toman', 'precision' => 0],
            ['symbol' => 'IRR',  'name' => 'Iran Rial',  'precision' => 0],
            ['symbol' => 'BTC',  'name' => 'Bitcoin',     'precision' => 8],
        ];

        foreach ($items as $item) {
            Asset::firstOrCreate(
                ['symbol' => $item['symbol']],   // Unique condition
                [
                    'name'       => $item['name'],
                    'precision'  => $item['precision'],
                    'status'     => 'active',
                    'metadata'   => [],
                ]
            );
        }
    }
}
