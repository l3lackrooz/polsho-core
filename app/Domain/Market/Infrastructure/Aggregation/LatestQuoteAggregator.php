<?php

namespace App\Domain\Market\Infrastructure\Aggregation;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;

class LatestQuoteAggregator
{
    private const DEFAULT_MAX_QUOTE_AGE_SECONDS = 5;
    private const MAX_SPREAD_RATIO = 0.10;
    private const MAX_DEVIATION = 0.08; // 8% from median

    public function __construct(
        private readonly LatestQuoteStore $quotes,
    ) {}

    public function aggregateInstrument(string $instrument): ?AggregatedQuoteDTO
    {
        $rows = $this->quotes->getAll($instrument);

        if ($rows === []) {
            return null;
        }

        $providers = $this->loadProviders();
        $quotes = $this->normalizeQuotes($rows, $providers);

        if ($quotes === []) {
            return null;
        }

        $tradableQuotes = array_values(array_filter(
            $quotes,
            static fn (QuoteDTO $quote): bool => !$quote->isReference,
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
                'weight' => $weight,
                'max_spread_ratio' => $p->config['max_spread_ratio'] ?? self::MAX_SPREAD_RATIO,
                'max_deviation' => $p->config['max_deviation'] ?? self::MAX_DEVIATION,
                'is_reference' => (bool) ($p->config['is_reference'] ?? false),
                'max_quote_age_ms' => $maxQuoteAgeSeconds * 1_000,
            ];
        }

        return $map;
    }

    private function normalizeQuotes(array $rows, array $providers): array
    {
        $result = [];
        $nowMs = now()->getTimestampMs();

        foreach ($rows as $provider => $data) {

            if (!isset($providers[$provider])) {
                continue;
            }

            $isReference = $providers[$provider]['is_reference'];

            if (!$this->isValidQuote($data, $isReference)) {
                continue;
            }

            if (!$this->isFreshTimestamp(
                $data['timestamp'] ?? null,
                $nowMs,
                $providers[$provider]['max_quote_age_ms'],
            )) {
                continue;
            }

            $result[] = new QuoteDTO(
                instrument: $data['instrument'],
                provider: $provider,
                bid: (float)$data['bid'],
                ask: (float)$data['ask'],
                last: $data['last'] ?? null,
                volume: $data['volume'] ?? null,
                timestamp: (int)$data['timestamp'],
                providerMarketId: $data['provider_market_id'] ?? null,
                isReference: $isReference,
            );
        }

        return $result;
    }

    private function isValidQuote(array $quote, bool $isReference): bool
    {
        if (!isset($quote['bid'], $quote['ask'])) {
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
        if (!is_int($timestamp) && !is_float($timestamp) && !is_string($timestamp)) {
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
            if (!$quote->isReference) {
                continue;
            }

            if ($latest === null || $quote->timestamp > $latest->timestamp) {
                $latest = $quote;
            }
        }

        return $latest;
    }

    /**
     * @param QuoteDTO[] $quotes
     */
    private function buildBestPrices(
        string $instrument,
        array $providers,
        array $bestPriceCandidates,
        ?QuoteDTO $referenceFallback,
    ): AggregatedQuoteDTO
    {
        $bestBid = $referenceFallback;
        $bestAsk = $referenceFallback;
        $latestTimestamp = 0;

        foreach ($providers as $quote) {
            $latestTimestamp = max($latestTimestamp, $quote->timestamp);
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
            timestamp: $latestTimestamp,
        );
    }
}
