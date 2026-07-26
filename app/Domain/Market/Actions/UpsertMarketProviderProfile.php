<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProviderProfile;
use App\Domain\Shared\Localization\LocalizedContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpsertMarketProviderProfile
{
    public function execute(MarketProvider $provider, array $attributes): MarketProviderProfile
    {
        return DB::transaction(function () use ($provider, $attributes): MarketProviderProfile {
            $profile = $provider->profile()->firstOrNew();
            $profile->fill($this->normalize($attributes));

            if ($profile->publication_status === 'published' && $profile->published_at === null) {
                $profile->published_at = now();
            }

            $profile->save();

            return $profile->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function normalize(array $attributes): array
    {
        $payload = Arr::only($attributes, [
            'type',
            'publication_status',
            'summary',
            'description',
            'seo_title',
            'seo_description',
            'legal_name',
            'country_code',
            'founded_year',
            'kyc_required',
            'fee_url',
            'support_url',
            'terms_url',
            'android_app_url',
            'ios_app_url',
            'facts',
            'sources',
            'last_reviewed_at',
        ]);

        foreach (['summary', 'description', 'seo_title', 'seo_description'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->localizedText($payload[$field]);
            }
        }

        if (array_key_exists('facts', $payload)) {
            $payload['facts'] = $this->localizedFacts($payload['facts']);
        }

        if (array_key_exists('sources', $payload)) {
            $payload['sources'] = $this->localizedSources($payload['sources']);
        }

        foreach (['legal_name'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]) ?: null;
            }
        }

        if (array_key_exists('country_code', $payload) && is_string($payload['country_code'])) {
            $payload['country_code'] = strtoupper(trim($payload['country_code'])) ?: null;
        }

        foreach (['fee_url', 'support_url', 'terms_url', 'android_app_url', 'ios_app_url'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = rtrim(trim($payload[$field]), '/') ?: null;
            }
        }

        return $payload;
    }

    /** @return array<string, string>|null */
    private function localizedText(mixed $value): ?array
    {
        return LocalizedContent::map($value);
    }

    /** @return array<int, array<string, mixed>>|null */
    private function localizedFacts(mixed $facts): ?array
    {
        if (! is_array($facts)) {
            return null;
        }

        return array_values(array_map(function (array $fact): array {
            $normalized = [
                'label' => $this->localizedText($fact['label'] ?? null),
                'value' => $this->localizedText($fact['value'] ?? null),
            ];

            if (isset($fact['key'])) {
                $normalized['key'] = trim((string) $fact['key']);
            }

            return $normalized;
        }, $facts));
    }

    /** @return array<int, array<string, mixed>>|null */
    private function localizedSources(mixed $sources): ?array
    {
        if (! is_array($sources)) {
            return null;
        }

        return array_values(array_map(function (array $source): array {
            $normalized = [
                'label' => $this->localizedText($source['label'] ?? null),
                'url' => rtrim(trim((string) ($source['url'] ?? '')), '/'),
            ];

            if (isset($source['published_at'])) {
                $normalized['published_at'] = $source['published_at'];
            }

            return $normalized;
        }, $sources));
    }
}
