<?php

namespace App\Domain\Market\Infrastructure\Aggregation;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use App\Domain\Market\Application\DTO\QuoteDTO;
use Illuminate\Support\Collection;

class PriceAggregator
{
    public function __construct(
        private ProviderManager $providers,
    ) {}

    /**
     * @param Collection<int, mixed>|array<int, mixed> $instruments
     * @return array<string, AggregatedQuoteDTO>
     */
    public function aggregate(Collection|array $instruments): array
    {
        $subscriptions = $instruments instanceof Collection ? $instruments : collect($instruments);
        $quotes = [];

        foreach ($this->providers->snapshotProviders() as $provider) {
            try {
                foreach ($provider->fetchPrices($subscriptions) as $quote) {
                    $quotes[$quote->instrument][] = $quote;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->buildBestPrices($quotes);
    }

    /**
     * @param array<string, array<int, QuoteDTO>> $quotes
     * @return array<string, AggregatedQuoteDTO>
     */
    private function buildBestPrices(array $quotes): array
    {
        $best = [];

        foreach ($quotes as $instrument => $instrumentQuotes) {
            $bestBid = null;
            $bestAsk = null;
            $latestTimestamp = 0;

            foreach ($instrumentQuotes as $quote) {
                if ($bestBid === null || $quote->bid > $bestBid->bid) {
                    $bestBid = $quote;
                }

                if ($bestAsk === null || $quote->ask < $bestAsk->ask) {
                    $bestAsk = $quote;
                }

                $latestTimestamp = max($latestTimestamp, $quote->timestamp);
            }

            $best[$instrument] = new AggregatedQuoteDTO(
                instrument: $instrument,
                bestBid: $bestBid,
                bestAsk: $bestAsk,
                providers: $instrumentQuotes,
                timestamp: $latestTimestamp,
            );
        }

        return $best;
    }
}
