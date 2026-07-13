<?php

namespace App\Domain\Market\Infrastructure\Cache;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use Illuminate\Support\Facades\Cache;

class LatestQuoteStore
{
    public function __construct(
        private readonly int $ttlSeconds = 30,
    ) {}

    public function put(QuoteDTO $quote): void
    {
        $expiresAt = now()->addSeconds($this->ttlSeconds);

        Cache::put($this->providerKey($quote->provider, $quote->instrument), $quote->toArray(), $expiresAt);

        $quotes = Cache::get($this->instrumentKey($quote->instrument), []);
        if (!is_array($quotes)) {
            $quotes = [];
        }

        $quotes[$quote->provider] = $quote->toArray();

        Cache::put($this->instrumentKey($quote->instrument), $quotes, $expiresAt);
    }

    /**
     * @return array<int, QuoteDTO>
     */
    public function getFreshInstrumentQuotes(string $instrument): array
    {
        $rows = Cache::get($this->instrumentKey($instrument), []);
        if (!is_array($rows)) {
            return [];
        }

        $fresh = [];
        $cutoff = now()->subSeconds($this->ttlSeconds)->getTimestampMs();

        foreach ($rows as $provider => $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $quote = QuoteDTO::fromArray($payload);
            if ($quote->timestamp < $cutoff) {
                continue;
            }

            $fresh[$provider] = $quote->toArray();
        }

        Cache::put($this->instrumentKey($instrument), $fresh, now()->addSeconds($this->ttlSeconds));

        return array_map(
            static fn (array $payload): QuoteDTO => QuoteDTO::fromArray($payload),
            array_values($fresh),
        );
    }

    public function putAggregate(AggregatedQuoteDTO $quote): void
    {
        Cache::put(
            $this->aggregateKey($quote->instrument),
            $quote->toArray(),
            now()->addSeconds($this->ttlSeconds),
        );
    }

    private function providerKey(string $provider, string $instrument): string
    {
        return sprintf('market.latest.provider.%s.%s', strtolower($provider), strtoupper($instrument));
    }

    private function instrumentKey(string $instrument): string
    {
        return sprintf('market.latest.instrument.%s', strtoupper($instrument));
    }

    private function aggregateKey(string $instrument): string
    {
        return sprintf('market.latest.aggregate.%s', strtoupper($instrument));
    }
}
