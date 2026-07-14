<?php

namespace App\Domain\Market\Actions;

use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Stores\AggregateStore;
use Illuminate\Support\Facades\DB;

class ListPublicQuotes
{
    public function __construct(private AggregateStore $store) {}

    /**
     * @param  string[]  $requestedSymbols
     * @return array{quotes: array<int, array>, missing: string[]}
     */
    public function execute(array $requestedSymbols): array
    {
        $activeSymbols = Instrument::query()
            ->where('status', 'active')
            ->whereIn(DB::raw('UPPER(symbol)'), $requestedSymbols)
            ->pluck('symbol')
            ->map(static fn (string $symbol): string => strtoupper($symbol))
            ->all();

        $quotes = [];
        $availableSymbols = [];

        foreach ($activeSymbols as $symbol) {
            $quote = $this->store->get($symbol);

            if ($quote === null) {
                continue;
            }

            $quotes[] = $this->normalizeQuote($quote);
            $availableSymbols[] = $symbol;
        }

        return [
            'quotes' => $quotes,
            'missing' => array_values(array_diff($requestedSymbols, $availableSymbols)),
        ];
    }

    private function normalizeQuote(array $quote): array
    {
        if (array_key_exists('best_bid', $quote)) {
            return $quote;
        }

        return [
            'instrument' => $quote['instrument'],
            'best_bid' => $this->normalizeProviderQuote($quote['bestBid'] ?? null),
            'best_ask' => $this->normalizeProviderQuote($quote['bestAsk'] ?? null),
            'providers' => array_map(
                fn (array $providerQuote): array => $this->normalizeProviderQuote($providerQuote),
                $quote['providers'] ?? [],
            ),
            'timestamp' => $quote['timestamp'],
        ];
    }

    private function normalizeProviderQuote(?array $quote): ?array
    {
        if ($quote === null || array_key_exists('provider_market_id', $quote)) {
            return $quote;
        }

        $quote['provider_market_id'] = $quote['providerMarketId'] ?? null;
        unset($quote['providerMarketId']);

        return $quote;
    }
}
