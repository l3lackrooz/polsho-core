<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $provider = DB::table('market_providers')
            ->where('slug', 'tgju')
            ->first(['id', 'config']);

        if ($provider === null) {
            return;
        }

        $config = $this->decodeConfig($provider->config);
        $config['is_reference'] = true;
        $config['max_quote_age_seconds'] = 1_800;

        DB::table('market_providers')
            ->where('id', $provider->id)
            ->update([
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $provider = DB::table('market_providers')
            ->where('slug', 'tgju')
            ->first(['id', 'config']);

        if ($provider === null) {
            return;
        }

        $config = $this->decodeConfig($provider->config);
        $config['max_quote_age_seconds'] = 90;

        DB::table('market_providers')
            ->where('id', $provider->id)
            ->update([
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    private function decodeConfig(mixed $config): array
    {
        if (is_array($config)) {
            return $config;
        }

        if (!is_string($config) || $config === '') {
            return [];
        }

        $decoded = json_decode($config, true);

        return is_array($decoded) ? $decoded : [];
    }
};
