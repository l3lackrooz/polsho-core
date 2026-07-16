<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
        });

        DB::table('application_settings')->insert([
            ['key' => 'alerts.unverified_active_limit', 'value' => '3'],
            ['key' => 'alerts.verified_active_limit', 'value' => '10'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('application_settings');
    }
};
