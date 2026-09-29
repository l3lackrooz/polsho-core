<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_milestone_rules', fn (Blueprint $table) => $table->string('price_source')->default('best_market')->change());
        DB::table('market_milestone_rules')->update(['price_source' => 'best_market', 'provider_market_id' => null,
            'enabled' => false, 'state' => null, 'configured_at' => now()->getTimestampMs(), 'revision' => DB::raw('revision + 1')]);
        Schema::table('market_milestone_events', function (Blueprint $table) {
            $table->json('matches')->nullable();
            // MySQL needs a separate index for the rule foreign key while replacing the unique key.
            $table->index('rule_id', 'milestone_events_rule_index');
            $table->dropUnique('milestone_event_unique');
            $table->unique(['rule_id', 'revision', 'quote_timestamp', 'price_source'], 'milestone_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('market_milestone_events', fn (Blueprint $table) => $table->dropColumn('matches'));
        // Keep the widened uniqueness key: reducing it could destroy valid event history.
    }
};
