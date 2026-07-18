<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_version_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 16)->unique();
            $table->string('latest_version', 32);
            $table->unsignedInteger('latest_build');
            $table->string('minimum_version', 32);
            $table->unsignedInteger('minimum_build');
            $table->string('store_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_version_policies');
    }
};
