<?php

namespace App\Domain\Market\Application\Presenters;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProviderProfile;
use App\Domain\Shared\Localization\LocalizedContent;

class PublicMarketProviderProfilePresenter
{
    /** @return array<string, mixed> */
    public static function card(MarketProvider $provider, string $locale): array
    {
        $profile = self::profile($provider);

        return [
            'slug' => $provider->slug,
            'name' => LocalizedContent::text($provider->translations, $locale) ?? $provider->name,
            'name_translations' => $provider->translations,
            'logo_url' => $provider->logo_url,
            'homepage_url' => $provider->homepage_url,
            'type' => $profile->type,
            'summary' => LocalizedContent::text($profile->summary, $locale),
            'active_markets_count' => (int) ($provider->active_markets_count ?? 0),
            'last_reviewed_at' => $profile->last_reviewed_at?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(MarketProvider $provider, string $locale): array
    {
        $profile = self::profile($provider);

        return [
            ...self::card($provider, $locale),
            'description' => LocalizedContent::text($profile->description, $locale),
            'seo' => [
                'title' => LocalizedContent::text($profile->seo_title, $locale),
                'description' => LocalizedContent::text($profile->seo_description, $locale),
            ],
            'legal_name' => $profile->legal_name,
            'country_code' => $profile->country_code,
            'founded_year' => $profile->founded_year,
            'kyc_required' => $profile->kyc_required,
            'links' => [
                'fees' => $profile->fee_url,
                'support' => $profile->support_url,
                'terms' => $profile->terms_url,
                'android_app' => $profile->android_app_url,
                'ios_app' => $profile->ios_app_url,
            ],
            'facts' => self::facts($profile->facts, $locale),
            'sources' => self::sources($profile->sources, $locale),
            'markets' => $provider->markets
                ->where('status', 'active')
                ->map(fn ($market): array => [
                    'id' => $market->id,
                    'instrument' => $market->instrument?->symbol,
                ])
                ->values(),
            'published_at' => $profile->published_at?->toIso8601String(),
        ];
    }

    private static function profile(MarketProvider $provider): MarketProviderProfile
    {
        /** @var MarketProviderProfile $profile */
        $profile = $provider->profile;

        return $profile;
    }

    /** @return array<int, array<string, string|null>> */
    private static function facts(?array $facts, string $locale): array
    {
        return collect($facts ?? [])
            ->filter(fn (mixed $fact): bool => is_array($fact))
            ->map(function (array $fact) use ($locale): array {
                $item = [
                    'label' => self::localizedValue($fact['label'] ?? null, $locale),
                    'value' => self::localizedValue($fact['value'] ?? null, $locale),
                ];

                if (isset($fact['key'])) {
                    $item['key'] = (string) $fact['key'];
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, string|null>> */
    private static function sources(?array $sources, string $locale): array
    {
        return collect($sources ?? [])
            ->filter(fn (mixed $source): bool => is_array($source))
            ->map(fn (array $source): array => [
                'label' => self::localizedValue($source['label'] ?? null, $locale),
                'url' => isset($source['url']) ? (string) $source['url'] : null,
                'published_at' => isset($source['published_at']) ? (string) $source['published_at'] : null,
            ])
            ->values()
            ->all();
    }

    private static function localizedValue(mixed $value, string $locale): ?string
    {
        if (is_string($value)) {
            return trim($value) !== '' ? $value : null;
        }

        return is_array($value) ? LocalizedContent::text($value, $locale) : null;
    }
}
