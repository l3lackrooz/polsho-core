<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_providers', function (Blueprint $table): void {
            // Deliberate opt-in; quote ingestion and the ordinary price list stay independent.
            $table->boolean('comparison_enabled')->default(false);
            $table->boolean('demo_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('market_providers', function (Blueprint $table): void {
            $table->dropColumn(['comparison_enabled', 'demo_enabled']);
        });
    }
};
