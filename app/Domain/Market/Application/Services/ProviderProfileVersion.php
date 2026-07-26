<?php

namespace App\Domain\Market\Application\Services;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProviderProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Small public-content version used by mobile clients to invalidate their
 * persisted provider-profile cache. It is derived from published content plus
 * a stored nonce that Backoffice may explicitly refresh.
 */
class ProviderProfileVersion
{
    public function current(): string
    {
        $rows = MarketProviderProfile::query()
            ->where('publication_status', 'published')
            ->whereHas('provider', fn ($query) => $query->where('status', 'active'))
            ->orderBy('provider_id')
            ->get(['provider_id', 'updated_at', 'published_at'])
            ->map(fn (MarketProviderProfile $profile): array => [
                $profile->provider_id,
                $profile->updated_at?->toIso8601String(),
                $profile->published_at?->toIso8601String(),
            ])
            ->all();

        return substr(sha1(json_encode([$this->nonce(), $rows])), 0, 12);
    }

    public function refresh(): string
    {
        DB::table('market_provider_profile_cache_versions')->updateOrInsert(
            ['id' => 1],
            ['nonce' => (string) Str::uuid(), 'updated_at' => now()],
        );

        return $this->current();
    }

    private function nonce(): string
    {
        $nonce = DB::table('market_provider_profile_cache_versions')->where('id', 1)->value('nonce');

        if (is_string($nonce) && $nonce !== '') {
            return $nonce;
        }

        $nonce = (string) Str::uuid();
        DB::table('market_provider_profile_cache_versions')->insertOrIgnore([
            'id' => 1,
            'nonce' => $nonce,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (string) DB::table('market_provider_profile_cache_versions')->where('id', 1)->value('nonce');
    }
}
