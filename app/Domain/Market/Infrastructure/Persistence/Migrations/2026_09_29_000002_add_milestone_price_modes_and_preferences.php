<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_milestone_rules', function (Blueprint $table) {
            $table->foreignId('provider_market_id')->nullable()->change();
            $table->string('price_source')->default('best_buy');
        });
        // Previously configured single-exchange rules must be reviewed under the new semantics.
        DB::table('market_milestone_rules')->update(['enabled' => false, 'provider_market_id' => null,
            'state' => null, 'configured_at' => now()->getTimestampMs(), 'revision' => DB::raw('revision + 1')]);
        Schema::table('market_milestone_events', function (Blueprint $table) {
            $table->string('price_source')->default('exchange_last');
            $table->unsignedBigInteger('provider_market_id')->nullable();
            $table->string('provider_name')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('market_milestones_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('market_milestones_enabled'));
        Schema::table('market_milestone_events', fn (Blueprint $table) => $table->dropColumn(['price_source', 'provider_market_id', 'provider_name']));
        Schema::table('market_milestone_rules', fn (Blueprint $table) => $table->dropColumn('price_source'));
        // Keep provider_market_id nullable on rollback to preserve aggregate-source rules.
    }
};
