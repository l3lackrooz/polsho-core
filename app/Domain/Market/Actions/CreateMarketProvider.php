<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateMarketProvider
{
    public function execute(array $attributes): MarketProvider
    {
        $payload = $this->normalize($attributes);

        return DB::transaction(
            fn (): MarketProvider => MarketProvider::query()->create($payload)
        );
    }

    private function normalize(array $attributes): array
    {
        $name = trim((string) Arr::get($attributes, 'name', ''));
        $driver = trim((string) Arr::get($attributes, 'class_name', ''));

        $config = Arr::get($attributes, 'config', []);
        $homepageUrl = Arr::get($attributes, 'homepage_url');

        if ($name === '') {
            throw new InvalidArgumentException('Provider name is required.');
        }

        if ($driver === '') {
            throw new InvalidArgumentException('Provider driver is required.');
        }

        if (! is_array($config)) {
            throw new InvalidArgumentException('Provider config must be an array.');
        }

        if (! isset($config['base_url']) || empty($config['base_url'])) {
            throw new InvalidArgumentException('Provider base_url is required in config.');
        }

        if ($homepageUrl !== null && $homepageUrl !== '' && ! filter_var($homepageUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Provider homepage_url must be a valid URL.');
        }

        return [
            'name' => $name,
            'translations' => $this->normalizeTranslations(Arr::get($attributes, 'translations')),
            'driver' => $driver,
            'slug' => trim((string) Arr::get($attributes, 'slug', '')) ?: Str::slug($name),
            'base_url' => $config['base_url'],
            'homepage_url' => is_string($homepageUrl) && trim($homepageUrl) !== ''
                ? rtrim(trim($homepageUrl), '/')
                : null,
            'description' => Arr::get($attributes, 'description'),
            'status' => Arr::get($attributes, 'status', 'active'),
            'is_default' => (bool) Arr::get($attributes, 'is_default', false),
            'priority' => (int) Arr::get($attributes, 'priority', 0),
            'config' => $this->normalizeConfig($config),
        ];
    }

    private function normalizeConfig(mixed $config): ?array
    {
        if ($config === null || $config === '') {
            return null;
        }

        if (! is_array($config)) {
            throw new InvalidArgumentException('Provider config must be an array.');
        }

        return $config;
    }

    private function normalizeTranslations(mixed $translations): ?array
    {
        if ($translations === null || $translations === '') {
            return null;
        }

        if (! is_array($translations)) {
            throw new InvalidArgumentException('Provider translations must be an array.');
        }

        $normalized = [];
        foreach ($translations as $locale => $name) {
            $locale = strtolower(trim((string) $locale));
            $name = trim((string) $name);
            if ($locale !== '' && $name !== '') {
                $normalized[$locale] = $name;
            }
        }

        return $normalized;
    }
}
