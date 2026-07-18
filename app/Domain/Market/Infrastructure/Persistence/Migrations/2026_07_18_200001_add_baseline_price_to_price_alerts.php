<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_alerts', function (Blueprint $table): void {
            // Market price when the alert was created; anchors progress
            // displays (baseline -> target). Null for alerts created before
            // this column, or when no quote was available at creation.
            $table->decimal('baseline_price', 30, 10)->nullable()->after('target_price');
        });
    }

    public function down(): void
    {
        Schema::table('price_alerts', function (Blueprint $table): void {
            $table->dropColumn('baseline_price');
        });
    }
};
