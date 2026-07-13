<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_snapshots', function (Blueprint $table): void {
            $table->string('dedupe_hash', 64)->nullable()->after('provider_market_id');
            $table->unique('dedupe_hash');
        });
    }

    public function down(): void
    {
        Schema::table('market_snapshots', function (Blueprint $table): void {
            $table->dropUnique(['dedupe_hash']);
            $table->dropColumn('dedupe_hash');
        });
    }
};
