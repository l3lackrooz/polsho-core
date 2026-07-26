<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_provider_profile_cache_versions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('nonce');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_provider_profile_cache_versions');
    }
};
