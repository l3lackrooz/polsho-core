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
        Schema::create('instruments', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('base_asset_id');
        $table->unsignedBigInteger('quote_asset_id');

        $table->string('symbol')->unique(); // btc-usdt

        $table->string('status')->default('active');

        $table->json('metadata')->nullable();

        $table->timestamps();

        // relations
        $table->foreign('base_asset_id')->references('id')->on('assets')->cascadeOnDelete();
        $table->foreign('quote_asset_id')->references('id')->on('assets')->cascadeOnDelete();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            //
        });
    }
};
