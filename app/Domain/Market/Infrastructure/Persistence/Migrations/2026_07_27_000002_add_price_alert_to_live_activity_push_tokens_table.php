<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_activity_push_tokens', function (Blueprint $table): void {
            $table->foreignId('price_alert_id')->nullable()->after('push_device_id')->constrained('price_alerts')->nullOnDelete();
            $table->index(['price_alert_id', 'kind', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('live_activity_push_tokens', function (Blueprint $table): void {
            $table->dropIndex(['price_alert_id', 'kind', 'enabled']);
            $table->dropConstrainedForeignId('price_alert_id');
        });
    }
};
