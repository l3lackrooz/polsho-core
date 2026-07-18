<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lives in the shared migrations directory because it touches two domains
// (Market providers + Assets) for the one branding feature.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_providers', function (Blueprint $table): void {
            // Content-addressed path on the public disk, e.g.
            // branding/nobitex.a3f8c1d2.png — see BrandingStorage.
            $table->string('logo_path', 2048)->nullable()->after('description');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->string('logo_path', 2048)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('market_providers', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
