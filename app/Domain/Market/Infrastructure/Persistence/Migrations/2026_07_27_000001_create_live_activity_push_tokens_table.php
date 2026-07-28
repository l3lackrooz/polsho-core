<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_activity_push_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('push_device_id')->constrained('push_devices')->cascadeOnDelete();
            $table->enum('kind', ['push_to_start', 'activity_update']);
            $table->string('activity_id', 255)->nullable();
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_seen_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['push_device_id', 'kind', 'enabled']);
            $table->index(['push_device_id', 'kind', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_activity_push_tokens');
    }
};
