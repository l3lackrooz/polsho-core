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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');

            // Estefade az Enum dar sat-h database
            $table->string('type')->default(CurrencyType::FIAT->value);

            // UnsignedTinyInteger baraye precision khoube (0 ta 255)
            $table->unsignedTinyInteger('precision')->default(2);

            // Baraye boolean-ha dar Laravel 12 mitooni az in syntax estefade koni
            $table->boolean('is_active')->default(true)->index(); // Index baraye query-haye sari-tar
            $table->boolean('is_base')->default(false);
            $table->boolean('is_convertible')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
