<?php

use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $irrId = DB::table('assets')->where('symbol', 'IRR')->value('id');
        $providerId = DB::table('market_providers')->where('slug', 'tgju')->value('id');

        if ($irrId === null || $providerId === null) {
            return;
        }

        DB::table('assets')->updateOrInsert(
            ['symbol' => 'MESGHAL'],
            [
                'name' => 'Gold (Mithqal)',
                'precision' => 0,
                'type' => 'metal',
                'status' => 'active',
                'metadata' => json_encode((object) []),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $mesghalId = DB::table('assets')->where('symbol', 'MESGHAL')->value('id');
        if ($mesghalId === null) {
            return;
        }

        DB::table('instruments')->updateOrInsert(
            ['symbol' => 'MESGHAL-IRR'],
            [
                'base_asset_id' => $mesghalId,
                'quote_asset_id' => $irrId,
                'status' => 'active',
                'metadata' => json_encode((object) []),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $instrumentId = DB::table('instruments')->where('symbol', 'MESGHAL-IRR')->value('id');
        if ($instrumentId === null) {
            return;
        }

        DB::table('provider_markets')->updateOrInsert(
            [
                'provider_id' => $providerId,
                'instrument_id' => $instrumentId,
            ],
            [
                'remote_symbol' => 'mesghal',
                'status' => 'active',
                'metadata' => json_encode((object) []),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        MarketSubscriptionFactory::forgetProviderMappings('tgju');
    }

    public function down(): void
    {
        $instrumentId = DB::table('instruments')->where('symbol', 'MESGHAL-IRR')->value('id');

        if ($instrumentId !== null) {
            DB::table('provider_markets')->where('instrument_id', $instrumentId)->delete();
            DB::table('instruments')->where('id', $instrumentId)->delete();
        }

        DB::table('assets')->where('symbol', 'MESGHAL')->delete();
        MarketSubscriptionFactory::forgetProviderMappings('tgju');
    }
};
