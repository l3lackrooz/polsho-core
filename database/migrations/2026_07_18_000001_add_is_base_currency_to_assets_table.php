<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->boolean('is_base_currency')->default(false)->after('type');
        });

        // Preserve Polsho's existing Toman-first display until an administrator
        // enables further display currencies from the Assets screen.
        DB::table('assets')
            ->where('symbol', 'IRT')
            ->update(['is_base_currency' => true]);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('is_base_currency');
        });
    }
};
