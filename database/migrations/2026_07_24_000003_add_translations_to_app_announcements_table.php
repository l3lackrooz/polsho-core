<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_announcements', function (Blueprint $table): void {
            $table->json('title_translations')->nullable()->after('title');
            $table->json('message_translations')->nullable()->after('message');
            $table->json('action_label_translations')->nullable()->after('action_label');
        });
    }

    public function down(): void
    {
        Schema::table('app_announcements', function (Blueprint $table): void {
            $table->dropColumn([
                'title_translations',
                'message_translations',
                'action_label_translations',
            ]);
        });
    }
};
