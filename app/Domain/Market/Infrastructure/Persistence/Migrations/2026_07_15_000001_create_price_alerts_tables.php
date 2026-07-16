<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->foreignId('provider_market_id')->nullable()->constrained('provider_markets')->nullOnDelete();
            $table->enum('scope', ['best_market', 'specific_exchange']);
            $table->enum('condition', ['reaches', 'goes_above', 'goes_below']);
            $table->decimal('target_price', 30, 10);
            $table->enum('status', ['active', 'paused', 'triggered', 'expired'])->default('active');
            $table->enum('repeat', ['once', 'recurring'])->default('once');
            $table->boolean('notify_push')->default(true);
            $table->boolean('notify_in_app')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['instrument_id', 'status']);
            $table->index(['provider_market_id', 'status']);
        });

        Schema::create('price_alert_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_alert_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['created', 'paused', 'resumed', 'triggered', 'edited']);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['price_alert_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alert_events');
        Schema::dropIfExists('price_alerts');
    }
};
