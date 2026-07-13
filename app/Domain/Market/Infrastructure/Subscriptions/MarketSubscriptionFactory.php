<?php

namespace App\Domain\Market\Infrastructure\Subscriptions;

use App\Domain\Asset\Infrastructure\Persistence\Models\Asset;
use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class MarketSubscriptionFactory
{
    public static function forgetProviderMappings(string $provider): void
    {
        Cache::forget(self::providerCacheKey($provider));
    }

    /**
     * @param iterable<int, mixed> $instruments
     * @return Collection<int, MarketSubscriptionDTO>
     */
    public function forProviderSymbols(iterable $instruments, string $provider): Collection
    {
        return collect($instruments)
            ->map(fn (mixed $instrument): ?MarketSubscriptionDTO => $this->forProvider($instrument, $provider))
            ->filter()
            ->values();
    }

    public function forProvider(mixed $value, string $provider): ?MarketSubscriptionDTO
    {
        if ($value instanceof MarketSubscriptionDTO) {
            return $this->mapCanonicalToProvider($value, $provider);
        }

        if (is_string($value) && trim($value) !== '') {
            return $this->mapCanonicalToProvider($this->canonicalFromString($value), $provider);
        }

        if (is_array($value)) {
            return $this->fromPayload(
                provider: $provider,
                remoteSymbol: $value['remote_symbol'] ?? $value['remoteSymbol'] ?? null,
                instrument: $value['instrument'] ?? null,
                base: $value['base'] ?? $value['base_asset'] ?? null,
                quote: $value['quote'] ?? $value['quote_asset'] ?? $value['qoute'] ?? null,
                metadata: is_array($value['metadata'] ?? null) ? $value['metadata'] : [],
            );
        }

        if (is_object($value)) {
            $instrument = $value->instrument ?? null;

            return $this->fromPayload(
                provider: $provider,
                remoteSymbol: $value->remote_symbol ?? $value->remoteSymbol ?? null,
                instrument: is_object($instrument) ? ($instrument->symbol ?? null) : $instrument,
                base: $value->base ?? $value->base_asset ?? (is_object($instrument) ? ($instrument->base ?? $instrument->base_asset ?? null) : null),
                quote: $value->quote ?? $value->quote_asset ?? $value->qoute ?? (is_object($instrument) ? ($instrument->quote ?? $instrument->quote_asset ?? null) : null),
                metadata: (array) ($value->metadata ?? []),
            );
        }

        return null;
    }

    private function fromPayload(
        string $provider,
        mixed $remoteSymbol,
        mixed $instrument = null,
        mixed $base = null,
        mixed $quote = null,
        array $metadata = [],
    ): ?MarketSubscriptionDTO {
        $normalizedRemoteSymbol = $this->normalizeRemoteSymbol($remoteSymbol);
        $normalizedInstrument = $this->normalizeInstrumentSymbol($instrument);
        $normalizedBase = $this->normalizeAssetSymbol($base);
        $normalizedQuote = $this->normalizeAssetSymbol($quote);

        if ($normalizedRemoteSymbol !== null) {
            $mapped = $this->findByRemoteSymbol($provider, $normalizedRemoteSymbol, $metadata);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        if ($normalizedInstrument !== null) {
            [$basePart, $quotePart] = $this->splitCanonicalInstrument($normalizedInstrument);

            return $this->mapCanonicalToProvider(
                new MarketSubscriptionDTO(
                    instrument: $normalizedInstrument,
                    remoteSymbol: $normalizedRemoteSymbol ?? $normalizedInstrument,
                    base: $normalizedBase ?? $basePart,
                    quote: $normalizedQuote ?? $quotePart,
                    providerMarketId: null,
                    metadata: $metadata,
                ),
                $provider,
            );
        }

        if ($normalizedBase !== null && $normalizedQuote !== null) {
            return $this->mapCanonicalToProvider(
                new MarketSubscriptionDTO(
                    instrument: sprintf('%s-%s', $normalizedBase, $normalizedQuote),
                    remoteSymbol: $normalizedRemoteSymbol ?? sprintf('%s-%s', $normalizedBase, $normalizedQuote),
                    base: $normalizedBase,
                    quote: $normalizedQuote,
                    providerMarketId: null,
                    metadata: $metadata,
                ),
                $provider,
            );
        }

        if ($normalizedRemoteSymbol !== null) {
            return $this->mapCanonicalToProvider($this->canonicalFromString($normalizedRemoteSymbol), $provider);
        }

        return null;
    }

    private function canonicalFromString(string $symbol): MarketSubscriptionDTO
    {
        $normalizedSymbol = $this->normalizeRemoteSymbol($symbol);
        [$base, $quote] = $this->parseCanonicalParts($normalizedSymbol);

        return new MarketSubscriptionDTO(
            instrument: sprintf('%s-%s', $base, $quote),
            remoteSymbol: $normalizedSymbol,
            base: $base,
            quote: $quote,
            providerMarketId: null,
        );
    }

    private function mapCanonicalToProvider(MarketSubscriptionDTO $subscription, string $provider): MarketSubscriptionDTO
    {
        $mapping = $this->findByInstrument($provider, $subscription->instrument);
        if ($mapping === null) {
            throw new InvalidArgumentException(sprintf(
                'Provider [%s] does not have a market mapping for instrument [%s].',
                $provider,
                $subscription->instrument,
            ));
        }

        return new MarketSubscriptionDTO(
            instrument: $mapping['instrument'],
            remoteSymbol: $mapping['remote_symbol'],
            base: $mapping['base'],
            quote: $mapping['quote'],
            providerMarketId: $mapping['provider_market_id'] ?? null,
            metadata: [...($mapping['metadata'] ?? []), ...$subscription->metadata],
        );
    }

    private function findByInstrument(string $provider, string $instrument): ?array
    {
        return $this->providerMappings($provider)['by_instrument'][$this->normalizeInstrumentSymbol($instrument)] ?? null;
    }

    private function findByRemoteSymbol(string $provider, string $remoteSymbol, array $metadata = []): ?MarketSubscriptionDTO
    {
        $mapping = $this->providerMappings($provider)['by_remote_symbol'][$remoteSymbol] ?? null;
        if ($mapping === null) {
            return null;
        }

        return new MarketSubscriptionDTO(
            instrument: $mapping['instrument'],
            remoteSymbol: $mapping['remote_symbol'],
            base: $mapping['base'],
            quote: $mapping['quote'],
            providerMarketId: $mapping['provider_market_id'] ?? null,
            metadata: [...($mapping['metadata'] ?? []), ...$metadata],
        );
    }

    /**
     * @return array{
     *     by_instrument: array<string, array{instrument: string, remote_symbol: string, base: string, quote: string, provider_market_id: int, metadata: array<string, mixed>}>,
     *     by_remote_symbol: array<string, array{instrument: string, remote_symbol: string, base: string, quote: string, provider_market_id: int, metadata: array<string, mixed>}>
     * }
     */
    private function providerMappings(string $provider): array
    {
        $normalizedProvider = strtolower(trim($provider));

        return Cache::remember(
            self::providerCacheKey($normalizedProvider),
            now()->addHour(),
            function () use ($normalizedProvider): array {
                $mappings = [
                    'by_instrument' => [],
                    'by_remote_symbol' => [],
                ];

                $markets = ProviderMarket::query()
                    ->select([
                        'provider_markets.remote_symbol',
                        'provider_markets.metadata',
                        'provider_markets.id as provider_market_id',
                        'instruments.symbol as instrument_symbol',
                        'base_assets.symbol as base_symbol',
                        'quote_assets.symbol as quote_symbol',
                    ])
                    ->join('market_providers', 'market_providers.id', '=', 'provider_markets.provider_id')
                    ->join('instruments', 'instruments.id', '=', 'provider_markets.instrument_id')
                    ->join('assets as base_assets', 'base_assets.id', '=', 'instruments.base_asset_id')
                    ->join('assets as quote_assets', 'quote_assets.id', '=', 'instruments.quote_asset_id')
                    ->where(function ($query) use ($normalizedProvider): void {
                        $query
                            ->whereRaw('LOWER(market_providers.name) = ?', [$normalizedProvider])
                            ->orWhereRaw('LOWER(market_providers.slug) = ?', [$normalizedProvider]);
                    })
                    ->where('provider_markets.status', 'active')
                    ->get();

                foreach ($markets as $market) {
                    $entry = [
                        'instrument' => $this->normalizeInstrumentSymbol((string) $market->instrument_symbol),
                        'remote_symbol' => $this->normalizeRemoteSymbol((string) $market->remote_symbol),
                        'base' => $this->normalizeAssetSymbol((string) $market->base_symbol),
                        'quote' => $this->normalizeAssetSymbol((string) $market->quote_symbol),
                        'provider_market_id' => (int) $market->provider_market_id,
                        'metadata' => is_array($market->metadata) ? $market->metadata : [],
                    ];

                    $mappings['by_instrument'][$entry['instrument']] = $entry;
                    $mappings['by_remote_symbol'][$entry['remote_symbol']] = $entry;
                }
                return $mappings;
            },
        );
    }

    private static function providerCacheKey(string $provider): string
    {
        return sprintf('market.subscription.v2.provider.%s', strtolower(trim($provider)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseCanonicalParts(string $symbol): array
    {
        $separated = preg_split('/[-_:\\/]/', $symbol);
        if (is_array($separated) && count($separated) === 2 && $separated[0] !== '' && $separated[1] !== '') {
            return [
                $this->normalizeAssetSymbol($separated[0]),
                $this->normalizeAssetSymbol($separated[1]),
            ];
        }

        return $this->parseConcatenatedInstrument($symbol);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseConcatenatedInstrument(string $symbol): array
    {
        $assetSymbols = $this->assetSymbols();
        $lookup = array_flip($assetSymbols);

        foreach ($assetSymbols as $quote) {
            if (!str_ends_with($symbol, $quote) || strlen($symbol) <= strlen($quote)) {
                continue;
            }

            $base = substr($symbol, 0, -strlen($quote));

            if ($base !== '' && isset($lookup[$base])) {
                return [$base, $quote];
            }
        }

        throw new InvalidArgumentException("Unable to normalize symbol [{$symbol}] to a known instrument.");
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitCanonicalInstrument(string $instrument): array
    {
        $parts = preg_split('/[-_:\\/]/', $instrument);
        if (!is_array($parts) || count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException("Unable to split canonical instrument [{$instrument}].");
        }

        return [
            $this->normalizeAssetSymbol($parts[0]),
            $this->normalizeAssetSymbol($parts[1]),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function assetSymbols(): array
    {
        return Cache::remember(
            'market.subscription.assets',
            now()->addHour(),
            fn (): array => Asset::query()
                ->where('status', 'active')
                ->pluck('symbol')
                ->map(fn (string $symbol): string => strtoupper(trim($symbol)))
                ->filter()
                ->unique()
                ->sortByDesc(fn (string $symbol): int => strlen($symbol))
                ->values()
                ->all(),
        );
    }

    private function normalizeRemoteSymbol(mixed $symbol): ?string
    {
        if (!is_string($symbol) || trim($symbol) === '') {
            return null;
        }

        return $symbol;
    }

    private function normalizeAssetSymbol(mixed $symbol): ?string
    {
        if (!is_string($symbol) || trim($symbol) === '') {
            return null;
        }

        return strtoupper(trim($symbol));
    }

    private function normalizeInstrumentSymbol(mixed $instrument): ?string
    {
        if (!is_string($instrument) || trim($instrument) === '') {
            return null;
        }

        [$base, $quote] = $this->parseCanonicalParts(strtoupper(trim($instrument)));

        return sprintf('%s-%s', $base, $quote);
    }
}
