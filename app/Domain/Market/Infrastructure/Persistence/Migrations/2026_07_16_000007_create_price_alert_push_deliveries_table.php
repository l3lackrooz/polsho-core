<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alert_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_delivery_id')
                ->constrained('price_alert_notification_deliveries')
                ->cascadeOnDelete();
            $table->foreignId('push_device_id')
                ->nullable()
                ->constrained('push_devices')
                ->nullOnDelete();
            $table->enum('platform', ['android', 'ios']);
            $table->enum('provider', ['pushe', 'fcm']);
            $table->text('provider_target');
            $table->char('target_hash', 64);
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['notification_delivery_id', 'provider', 'target_hash'],
                'alert_push_target_unique',
            );
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alert_push_deliveries');
    }
};
