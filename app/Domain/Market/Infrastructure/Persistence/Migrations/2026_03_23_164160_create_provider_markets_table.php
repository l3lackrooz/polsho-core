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
        Schema::create('provider_markets', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('provider_id');
        $table->unsignedBigInteger('instrument_id');

        $table->string('remote_symbol'); // BTCUSDT, btc_usdt, btc-irr ...

        $table->string('status')->default('active');

        $table->json('metadata')->nullable();

        $table->timestamps();

        $table->foreign('provider_id')->references('id')->on('market_providers')->cascadeOnDelete();
        $table->foreign('instrument_id')->references('id')->on('instruments')->cascadeOnDelete();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_markets', function (Blueprint $table) {
            //
        });
    }
};
