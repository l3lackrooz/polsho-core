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

        $emptyMetadata = json_encode((object) []);

        DB::table('assets')->updateOrInsert(
            ['symbol' => 'GERAM18'],
            [
                'name' => '18K Gold (Gram)',
                'precision' => 0,
                'type' => 'metal',
                'status' => 'active',
                'metadata' => $emptyMetadata,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $geram18Id = DB::table('assets')->where('symbol', 'GERAM18')->value('id');
        if ($geram18Id === null) {
            return;
        }

        DB::table('instruments')->updateOrInsert(
            ['symbol' => 'GERAM18-IRR'],
            [
                'base_asset_id' => $geram18Id,
                'quote_asset_id' => $irrId,
                'status' => 'active',
                'metadata' => $emptyMetadata,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $instrumentId = DB::table('instruments')->where('symbol', 'GERAM18-IRR')->value('id');
        if ($instrumentId === null) {
            return;
        }

        DB::table('provider_markets')->updateOrInsert(
            [
                'provider_id' => $providerId,
                'instrument_id' => $instrumentId,
            ],
            [
                'remote_symbol' => 'geram18',
                'status' => 'active',
                'metadata' => $emptyMetadata,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        MarketSubscriptionFactory::forgetProviderMappings('tgju');
    }

    public function down(): void
    {
        $instrumentId = DB::table('instruments')->where('symbol', 'GERAM18-IRR')->value('id');

        if ($instrumentId !== null) {
            DB::table('provider_markets')->where('instrument_id', $instrumentId)->delete();
            DB::table('instruments')->where('id', $instrumentId)->delete();
        }

        DB::table('assets')->where('symbol', 'GERAM18')->delete();
        MarketSubscriptionFactory::forgetProviderMappings('tgju');
    }
};
