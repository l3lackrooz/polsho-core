<?php

namespace App\Domain\Market\Infrastructure\Persistence\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class InstrumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $btc = DB::table('assets')->where('symbol', 'BTC')->first();
        $usdt = DB::table('assets')->where('symbol', 'USDT')->first();
        $irt = DB::table('assets')->where('symbol', 'IRT')->first();

        DB::table('instruments')->insert([
            [
                'base_asset_id' => $btc->id,
                'quote_asset_id' => $irt->id,
                'symbol' => 'btc-irt',
                'status' => 'active',
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'base_asset_id' => $btc->id,
                'quote_asset_id' => $usdt->id,
                'symbol' => 'btc-usdt',
                'status' => 'active',
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'base_asset_id' => $usdt->id,
                'quote_asset_id' => $irt->id,
                'symbol' => 'usdt-irt',
                'status' => 'active',
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
