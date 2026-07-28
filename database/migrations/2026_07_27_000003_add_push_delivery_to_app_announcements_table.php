<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_announcements', function (Blueprint $table): void {
            $table->boolean('publish_push')->default(false)->after('is_active');
            $table->enum('push_status', ['pending', 'sending', 'sent', 'failed'])->nullable()->after('publish_push');
            $table->timestamp('push_sent_at')->nullable()->after('push_status');
        });
    }

    public function down(): void
    {
        Schema::table('app_announcements', function (Blueprint $table): void {
            $table->dropColumn(['publish_push', 'push_status', 'push_sent_at']);
        });
    }
};
