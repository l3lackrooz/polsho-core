<?php

use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $providerId = DB::table('market_providers')
            ->where('slug', 'tgju')
            ->value('id');

        if ($providerId === null) {
            return;
        }

        $mappings = [
            'USD-IRR' => 'price_dollar_rl',
            'EUR-IRR' => 'price_eur',
            'AED-IRR' => 'price_aed',
            'TRY-IRR' => 'price_try',
            'MESGHAL-IRR' => 'mesghal',
            'GERAM18-IRR' => 'geram18',
        ];

        $instrumentIds = DB::table('instruments')
            ->whereIn('symbol', array_keys($mappings))
            ->pluck('id', 'symbol');

        foreach ($mappings as $instrumentSymbol => $remoteSymbol) {
            $instrumentId = $instrumentIds[$instrumentSymbol] ?? null;

            if ($instrumentId === null) {
                continue;
            }

            DB::table('provider_markets')->updateOrInsert(
                [
                    'provider_id' => $providerId,
                    'instrument_id' => $instrumentId,
                ],
                [
                    'remote_symbol' => $remoteSymbol,
                    'status' => 'active',
                    'metadata' => json_encode((object) []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        MarketSubscriptionFactory::forgetProviderMappings('tgju');
    }

    public function down(): void
    {
        // Existing provider markets are preserved on rollback.
    }
};
