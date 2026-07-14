<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('instruments')->update([
            'symbol' => DB::raw('UPPER(symbol)'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Canonical instrument symbols are uppercase by design.
    }
};
