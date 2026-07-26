<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_provider_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')
                ->unique()
                ->constrained('market_providers')
                ->cascadeOnDelete();

            // A provider can be a tradable exchange or a public reference source.
            $table->string('type')->default('exchange');
            $table->string('publication_status')->default('draft');

            // Localized editorial/SEO copy, keyed by locale (for example fa/en).
            $table->json('summary')->nullable();
            $table->json('description')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();

            // Verifiable, provider-owned facts. Do not store runtime driver data here.
            $table->string('legal_name')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->boolean('kyc_required')->nullable();
            $table->string('fee_url', 2048)->nullable();
            $table->string('support_url', 2048)->nullable();
            $table->string('terms_url', 2048)->nullable();
            $table->string('android_app_url', 2048)->nullable();
            $table->string('ios_app_url', 2048)->nullable();
            $table->json('facts')->nullable();
            $table->json('sources')->nullable();

            $table->date('last_reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['publication_status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_provider_profiles');
    }
};
