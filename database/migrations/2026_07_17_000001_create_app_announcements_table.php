<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 16)->nullable();
            $table->string('presentation', 16)->default('banner');
            $table->string('type', 16)->default('info');
            $table->string('title');
            $table->text('message');
            $table->string('action_label', 80)->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'platform', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_announcements');
    }
};
