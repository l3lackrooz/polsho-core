<?php

namespace App\Domain\Shared\Services;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;

/**
 * Builds the public branding manifest: every exchange logo and asset icon the
 * mobile app can display, keyed for lookup, plus a version hash over the
 * whole document.
 *
 * The version doubles as the HTTP ETag and is exposed through pub/app-status
 * so the app can detect changes without an extra request. The two queries
 * behind it are tiny (slug + path of rows that have a logo), so no cache
 * layer is needed — ETag/304 already keeps the bandwidth at zero.
 */
class BrandingManifest
{
    public function __construct(private readonly BrandingStorage $storage) {}

    /** @return array{version: string, exchanges: array<string, array{logo: string}>, assets: array<string, array{icon: string}>} */
    public function build(): array
    {
        $exchanges = MarketProvider::query()
            ->whereNotNull('logo_path')
            ->orderBy('slug')
            ->get(['slug', 'logo_path'])
            ->mapWithKeys(fn (MarketProvider $provider): array => [
                strtolower($provider->slug) => ['logo' => $this->storage->url($provider->logo_path)],
            ])
            ->all();

        $assets = Asset::query()
            ->whereNotNull('logo_path')
            ->orderBy('symbol')
            ->get(['symbol', 'logo_path'])
            ->mapWithKeys(fn (Asset $asset): array => [
                strtoupper($asset->symbol) => ['icon' => $this->storage->url($asset->logo_path)],
            ])
            ->all();

        return [
            'version' => $this->hash($exchanges, $assets),
            'exchanges' => $exchanges,
            'assets' => $assets,
        ];
    }

    public function version(): string
    {
        return $this->build()['version'];
    }

    /**
     * @param array<string, mixed> $exchanges
     * @param array<string, mixed> $assets
     */
    private function hash(array $exchanges, array $assets): string
    {
        return substr(sha1(json_encode([$exchanges, $assets])), 0, 12);
    }
}
