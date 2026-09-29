<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_milestone_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->unique()->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('provider_market_id')->constrained('provider_markets')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->decimal('step', 24, 8);
            $table->string('direction')->default('both');
            $table->unsignedInteger('cooldown_seconds')->default(300);
            $table->unsignedInteger('confirmation_quotes')->default(2);
            $table->unsignedInteger('revision')->default(1);
            $table->json('state')->nullable();
            $table->unsignedBigInteger('configured_at')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('market_milestone_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('market_milestone_rules')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('symbol');
            $table->string('quote_unit');
            $table->string('provider');
            $table->string('direction');
            $table->decimal('level', 24, 8);
            $table->decimal('price', 24, 8);
            $table->unsignedBigInteger('quote_timestamp');
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('audience_max_user_id')->default(0);
            $table->unsignedBigInteger('fanout_cursor')->default(0);
            $table->timestamp('fanout_completed_at')->nullable();
            $table->timestamps();
            $table->unique(['rule_id', 'revision', 'quote_timestamp'], 'milestone_event_unique');
            $table->index(['fanout_completed_at', 'expires_at']);
        });
        Schema::create('market_milestone_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('market_milestone_events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('push_device_id')->nullable()->constrained('push_devices')->nullOnDelete();
            $table->string('provider');
            $table->string('platform');
            $table->text('address');
            $table->char('target_hash', 64);
            $table->string('locale')->default('en');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'provider', 'target_hash'], 'milestone_delivery_unique');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_milestone_deliveries');
        Schema::dropIfExists('market_milestone_events');
        Schema::dropIfExists('market_milestone_rules');
    }
};
