<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('installation_id')->unique();
            $table->enum('platform', ['android', 'ios']);
            $table->enum('provider', ['pushe', 'fcm']);
            $table->text('provider_token')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->boolean('enabled')->default(true);
            $table->string('app_version', 50)->nullable();
            $table->string('locale', 20)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'enabled', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
