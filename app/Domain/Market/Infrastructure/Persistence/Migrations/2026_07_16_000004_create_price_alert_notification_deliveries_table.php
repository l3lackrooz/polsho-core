<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_alert_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_alert_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->timestamp('in_app_sent_at')->nullable();
            $table->enum('push_status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->unsignedSmallInteger('push_attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('push_error')->nullable();
            $table->timestamp('push_sent_at')->nullable();
            $table->timestamps();

            $table->index(['push_status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alert_notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};
