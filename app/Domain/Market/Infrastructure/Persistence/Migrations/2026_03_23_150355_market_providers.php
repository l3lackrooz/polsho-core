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
        Schema::create('market_providers', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('driver')->unique();
            $table->string('slug')->unique();

            $table->string('base_url');
            $table->text('description')->nullable();

            $table->string('status')->default('active');

            $table->boolean('is_default')->default(false);

            $table->unsignedSmallInteger('priority')->default(0);

            $table->json('config')->nullable();

            $table->timestamps();

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
