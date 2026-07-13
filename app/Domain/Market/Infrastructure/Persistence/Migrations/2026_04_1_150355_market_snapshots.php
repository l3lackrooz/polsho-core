<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('market_snapshots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('provider_market_id');

            $table->decimal('bid', 20, 10)->nullable();
            $table->decimal('ask', 20, 10)->nullable();
            $table->decimal('last_price', 20, 10)->nullable();

            $table->decimal('volume_24h', 30, 10)->nullable();

            $table->decimal('high_24h', 20, 10)->nullable();
            $table->decimal('low_24h', 20, 10)->nullable();

            $table->timestamp('captured_at')->index();

            $table->timestamps();

            $table->foreign('provider_market_id')
                ->references('id')
                ->on('provider_markets')
                ->cascadeOnDelete();

            $table->index(['provider_market_id', 'captured_at']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_providers');
    }
};
