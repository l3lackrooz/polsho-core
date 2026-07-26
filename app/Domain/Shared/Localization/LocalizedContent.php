<?php

namespace App\Domain\Shared\Localization;

use Illuminate\Support\Str;

final class LocalizedContent
{
    public static function locale(?string $requested, ?string $acceptLanguage = null): string
    {
        $candidate = self::normalize($requested);
        if ($candidate !== null) {
            return $candidate;
        }

        foreach (explode(',', (string) $acceptLanguage) as $part) {
            $candidate = self::normalize(explode(';', $part)[0]);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return config('content_locales.default', 'fa');
    }

    /** @param array<string, mixed>|null $translations */
    public static function text(?array $translations, string $locale): ?string
    {
        if ($translations === null) {
            return null;
        }

        $language = Str::before(strtolower($locale), '-');
        $candidates = array_unique([
            strtolower($locale),
            $language,
            config('content_locales.fallback', 'en'),
            config('content_locales.default', 'fa'),
        ]);

        foreach ($candidates as $candidate) {
            $value = $translations[$candidate] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        foreach ($translations as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return array<string, string>|null */
    public static function map(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $localized = [];
        foreach ($value as $locale => $text) {
            $locale = self::normalize((string) $locale);
            $text = trim((string) $text);
            if ($locale !== null && $text !== '') {
                $localized[$locale] = $text;
            }
        }

        return $localized ?: null;
    }

    private static function normalize(?string $value): ?string
    {
        $locale = strtolower(trim((string) $value));
        $language = Str::before($locale, '-');

        return in_array($language, config('content_locales.supported', []), true)
            ? $language
            : null;
    }
}
