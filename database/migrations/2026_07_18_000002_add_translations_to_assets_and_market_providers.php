<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->json('translations')->nullable()->after('name');
        });

        Schema::table('market_providers', function (Blueprint $table): void {
            $table->json('translations')->nullable()->after('name');
        });

        foreach ([
            'USDT' => ['fa' => 'تتر', 'de' => 'Tether'],
            'IRT' => ['fa' => 'تومان ایران', 'de' => 'Iranischer Toman'],
            'IRR' => ['fa' => 'ریال ایران', 'de' => 'Iranischer Rial'],
            'BTC' => ['fa' => 'بیت کوین', 'de' => 'Bitcoin'],
            'USD' => ['fa' => 'دلار آمریکا', 'de' => 'US-Dollar'],
            'EUR' => ['fa' => 'یورو', 'de' => 'Euro'],
            'AED' => ['fa' => 'درهم امارات', 'de' => 'VAE-Dirham'],
            'TRY' => ['fa' => 'لیر ترکیه', 'de' => 'Türkische Lira'],
            'MESGHAL' => ['fa' => 'مثقال طلا', 'de' => 'Gold (Mithqal)'],
            'GERAM18' => ['fa' => 'طلای ۱۸ عیار (گرم)', 'de' => '18-Karat-Gold (Gramm)'],
        ] as $symbol => $translations) {
            DB::table('assets')
                ->where('symbol', $symbol)
                ->whereNull('translations')
                ->update(['translations' => json_encode($translations)]);
        }

        DB::table('market_providers')
            ->where('slug', 'tgju')
            ->whereNull('translations')
            ->update([
                'translations' => json_encode([
                    'fa' => 'شبکه اطلاع رسانی طلا، سکه و ارز',
                    'de' => 'TGJU-Referenzkurse',
                ]),
            ]);
    }

    public function down(): void
    {
        Schema::table('market_providers', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
    }
};
