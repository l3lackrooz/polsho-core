<?php

use App\Domain\Shared\Enums\CurrencyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
        $table->id();

        $table->string('symbol')->unique();   // BTC
        $table->string('name');               // Bitcoin
        $table->unsignedTinyInteger('precision')->default(8); // decimals

        $table->string('status')->default('active');  // active / inactive
        $table->string('type')->default(CurrencyType::CRYPTO->value);


        $table->json('metadata')->nullable(); // extra config

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            //
        });
    }
};
