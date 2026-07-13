<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $emptyObject = json_encode((object) []);

        DB::table('assets')
            ->where('symbol', 'MESGHAL')
            ->update(['metadata' => $emptyObject, 'updated_at' => now()]);

        DB::table('instruments')
            ->where('symbol', 'MESGHAL-IRR')
            ->update(['metadata' => $emptyObject, 'updated_at' => now()]);

        $instrumentId = DB::table('instruments')
            ->where('symbol', 'MESGHAL-IRR')
            ->value('id');

        if ($instrumentId !== null) {
            DB::table('provider_markets')
                ->where('instrument_id', $instrumentId)
                ->update(['metadata' => $emptyObject, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Metadata normalization is intentionally non-destructive.
    }
};
