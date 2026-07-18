<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestions', function (Blueprint $table): void {
            $table->id();
            // Nullable so a guest submission survives the user being deleted.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['add_instrument', 'instrument_on_exchange', 'add_exchange']);
            // Free text: the requested instrument or exchange does not exist in
            // the catalog yet, so nothing here is a foreign key.
            $table->string('subject', 120);
            $table->enum('market_kind', ['crypto', 'fiat', 'gold', 'other'])->nullable();
            $table->string('exchange', 120)->nullable();
            $table->string('website', 2048)->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['under_review', 'planned', 'added', 'declined'])->default('under_review');
            $table->text('admin_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
