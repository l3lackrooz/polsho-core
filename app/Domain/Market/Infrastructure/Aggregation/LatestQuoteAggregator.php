<?php

namespace App\Domain\Market\Infrastructure\Aggregation;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\ComparisonProviderQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;

class LatestQuoteAggregator
{
    // Providers are polled on a ten-second cadence. Leave room for network
    // latency and sequential jobs so one provider update does not evict the
    // other current exchange quotes from the aggregate event.
    private const DEFAULT_MAX_QUOTE_AGE_SECONDS = 30;

    private const MAX_SPREAD_RATIO = 0.10;

    private const MAX_DEVIATION = 0.08; // 8% from median

    public function __construct(
        private readonly LatestQuoteStore $quotes,
    ) {}

    public function aggregateInstrument(string $instrument): ?AggregatedQuoteDTO
    {
        $rows = $this->quotes->getAll($instrument);
        $providers = $this->loadProviders();
        $comparisonProviders = $this->comparisonProviders($instrument, $rows, $providers);
        $quotes = $this->normalizeQuotes($rows, $providers);

        if ($quotes === [] && $comparisonProviders === []) {
            return null;
        }

        $tradableQuotes = array_values(array_filter(
            $quotes,
            static fn (QuoteDTO $quote): bool => ! $quote->isReference,
        ));
        $bestPriceCandidates = $this->filterOutliers($tradableQuotes);
        $referenceFallback = $tradableQuotes === []
            ? $this->latestReferenceQuote($quotes)
            : null;

        return $this->buildBestPrices(
            $instrument,
            $quotes,
            $bestPriceCandidates,
            $referenceFallback,
            $comparisonProviders,
        );
    }

    private function loadProviders(): array
    {
        $providers = MarketProvider::where('status', 'active')->get();

        $maxPriority = $providers->max('priority') ?: 1;

        $map = [];

        foreach ($providers as $p) {
            $maxQuoteAgeSeconds = max(
                1,
                (int) ($p->config['max_quote_age_seconds'] ?? self::DEFAULT_MAX_QUOTE_AGE_SECONDS),
            );

            $weight = $p->config['weight']
                ?? ($p->priority / $maxPriority);

            $map[$p->slug] = [
                'name' => $p->name,
                'translations' => $p->translations,
                'homepage_url' => $p->homepage_url,
                'weight' => $weight,
                'max_spread_ratio' => $p->config['max_spread_ratio'] ?? self::MAX_SPREAD_RATIO,
                'max_deviation' => $p->config['max_deviation'] ?? self::MAX_DEVIATION,
                'is_reference' => (bool) ($p->config['is_reference'] ?? false),
                'max_quote_age_ms' => $maxQuoteAgeSeconds * 1_000,
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $providers
     * @return ComparisonProviderQuoteDTO[]
     */
    private function comparisonProviders(
        string $instrument,
        array $rows,
        array $providers,
    ): array {
        $markets = ProviderMarket::query()
            ->select([
                'provider_markets.id',
                'provider_markets.provider_id',
                'market_providers.slug as provider_slug',
            ])
            ->join('market_providers', 'market_providers.id', '=', 'provider_markets.provider_id')
            ->join('instruments', 'instruments.id', '=', 'provider_markets.instrument_id')
            ->where('provider_markets.status', 'active')
            ->where('market_providers.status', 'active')
            ->whereRaw('UPPER(instruments.symbol) = ?', [strtoupper($instrument)])
            ->orderBy('market_providers.priority')
            ->orderBy('market_providers.slug')
            ->get();

        $comparisonProviders = [];

        foreach ($markets as $market) {
            $provider = (string) $market->provider_slug;
            $providerConfig = $providers[$provider] ?? null;

            if ($providerConfig === null) {
                continue;
            }

            $quote = $rows[$provider] ?? null;
            $isCurrentMarket = is_array($quote)
                && (int) ($quote['provider_market_id'] ?? 0) === (int) $market->id;
            $isValidQuote = $isCurrentMarket
                && $this->isValidQuote($quote, $providerConfig['is_reference']);

            $comparisonProviders[] = new ComparisonProviderQuoteDTO(
                provider: $provider,
                providerMarketId: (int) $market->id,
                isReference: $providerConfig['is_reference'],
                bid: $isValidQuote ? (float) $quote['bid'] : null,
                ask: $isValidQuote ? (float) $quote['ask'] : null,
                last: $isValidQuote && isset($quote['last']) ? (float) $quote['last'] : null,
                volume: $isValidQuote && isset($quote['volume']) ? (float) $quote['volume'] : null,
                timestamp: $isValidQuote ? (int) $quote['timestamp'] : null,
                providerName: $providerConfig['name'],
                providerTranslations: $providerConfig['translations'],
                providerHomepageUrl: $providerConfig['homepage_url'],
            );
        }

        return $comparisonProviders;
    }

    private function normalizeQuotes(array $rows, array $providers): array
    {
        $result = [];
        $nowMs = now()->getTimestampMs();

        foreach ($rows as $provider => $data) {

            if (! isset($providers[$provider])) {
                continue;
            }

            $isReference = $providers[$provider]['is_reference'];

            if (! $this->isValidQuote($data, $isReference)) {
                continue;
            }

            if (! $this->isFreshTimestamp(
                $data['timestamp'] ?? null,
                $nowMs,
                $providers[$provider]['max_quote_age_ms'],
            )) {
                continue;
            }

            $result[] = new QuoteDTO(
                instrument: $data['instrument'],
                provider: $provider,
                bid: (float) $data['bid'],
                ask: (float) $data['ask'],
                last: $data['last'] ?? null,
                volume: $data['volume'] ?? null,
                timestamp: (int) $data['timestamp'],
                providerMarketId: $data['provider_market_id'] ?? null,
                isReference: $isReference,
                providerName: $providers[$provider]['name'],
                providerTranslations: $providers[$provider]['translations'],
                providerHomepageUrl: $providers[$provider]['homepage_url'],
            );
        }

        return $result;
    }

    private function isValidQuote(array $quote, bool $isReference): bool
    {
        if (! isset($quote['bid'], $quote['ask'])) {
            return false;
        }

        $bid = (float) $quote['bid'];
        $ask = (float) $quote['ask'];

        if ($bid <= 0 || $ask <= 0) {
            return false;
        }

        if ($ask < $bid) {
            return false;
        }

        return $isReference || $ask > $bid;
    }

    private function isFreshTimestamp(mixed $timestamp, int $nowMs, int $maxAgeMs): bool
    {
        if (! is_int($timestamp) && ! is_float($timestamp) && ! is_string($timestamp)) {
            return false;
        }

        $timestampMs = (int) $timestamp;

        return $timestampMs > 0
            && abs($nowMs - $timestampMs) <= $maxAgeMs;
    }

    /**
     * حذف outlierها با median deviation
     */
    private function filterOutliers(array $quotes): array
    {
        if ($quotes === []) {
            return [];
        }

        $mids = [];

        foreach ($quotes as $q) {
            $mids[] = ($q->bid + $q->ask) / 2;
        }

        sort($mids);

        $median = $mids[(int) floor(count($mids) / 2)];

        $filtered = [];

        foreach ($quotes as $q) {

            $mid = ($q->bid + $q->ask) / 2;

            $deviation = abs($mid - $median) / $median;

            if ($deviation > self::MAX_DEVIATION) {
                continue;
            }

            $spread = ($q->ask - $q->bid) / $mid;

            if ($spread > self::MAX_SPREAD_RATIO) {
                continue;
            }

            $filtered[] = $q;
        }

        return $filtered;
    }

    /** @param QuoteDTO[] $quotes */
    private function latestReferenceQuote(array $quotes): ?QuoteDTO
    {
        $latest = null;

        foreach ($quotes as $quote) {
            if (! $quote->isReference) {
                continue;
            }

            if ($latest === null || $quote->timestamp > $latest->timestamp) {
                $latest = $quote;
            }
        }

        return $latest;
    }

    /**
     * @param  QuoteDTO[]  $quotes
     * @param  ComparisonProviderQuoteDTO[]  $comparisonProviders
     */
    private function buildBestPrices(
        string $instrument,
        array $providers,
        array $bestPriceCandidates,
        ?QuoteDTO $referenceFallback,
        array $comparisonProviders,
    ): AggregatedQuoteDTO {
        $bestBid = $referenceFallback;
        $bestAsk = $referenceFallback;
        $latestTimestamp = 0;

        foreach ($providers as $quote) {
            $latestTimestamp = max($latestTimestamp, $quote->timestamp);
        }

        foreach ($comparisonProviders as $quote) {
            $latestTimestamp = max($latestTimestamp, $quote->timestamp ?? 0);
        }

        foreach ($bestPriceCandidates as $quote) {

            if ($bestBid === null || $quote->bid > $bestBid->bid) {
                $bestBid = $quote;
            }

            if ($bestAsk === null || $quote->ask < $bestAsk->ask) {
                $bestAsk = $quote;
            }

        }

        return new AggregatedQuoteDTO(
            instrument: $instrument,
            bestBid: $bestBid,
            bestAsk: $bestAsk,
            providers: $providers,
            timestamp: $latestTimestamp > 0 ? $latestTimestamp : now()->getTimestampMs(),
            comparisonProviders: $comparisonProviders,
        );
    }
}
