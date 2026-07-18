<?php

namespace App\Domain\Market\Actions;

use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
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

        $providers = MarketProvider::query()
            ->whereIn('slug', $this->providerSlugs($quotes))
            ->get(['slug', 'name', 'translations', 'homepage_url'])
            ->keyBy('slug');

        $quotes = array_map(
            fn (array $quote): array => $this->attachProviderMetadata($quote, $providers->all()),
            $quotes,
        );

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

    /** @param array<int, array> $quotes */
    private function providerSlugs(array $quotes): array
    {
        $slugs = [];

        foreach ($quotes as $quote) {
            foreach ([
                $quote['best_bid'] ?? null,
                $quote['best_ask'] ?? null,
                ...($quote['providers'] ?? []),
                ...($quote['comparison_providers'] ?? []),
            ] as $providerQuote) {
                if (is_array($providerQuote) && isset($providerQuote['provider'])) {
                    $slugs[] = (string) $providerQuote['provider'];
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Cache payloads created before a provider rename/localization do not
     * contain display metadata. Attach the current provider record at read
     * time so the public API never needs to wait for another price tick.
     *
     * @param  array<string, MarketProvider>  $providers
     */
    private function attachProviderMetadata(array $quote, array $providers): array
    {
        $attach = static function (?array $providerQuote) use ($providers): ?array {
            if ($providerQuote === null) {
                return null;
            }

            $provider = $providers[$providerQuote['provider'] ?? null] ?? null;
            if ($provider === null) {
                return $providerQuote;
            }

            $providerQuote['provider_name'] = $provider->name;
            $providerQuote['provider_translations'] = $provider->translations;
            $providerQuote['provider_homepage_url'] = $provider->homepage_url;

            return $providerQuote;
        };

        $quote['best_bid'] = $attach($quote['best_bid'] ?? null);
        $quote['best_ask'] = $attach($quote['best_ask'] ?? null);
        $quote['providers'] = array_map($attach, $quote['providers'] ?? []);
        $quote['comparison_providers'] = array_map(
            $attach,
            $quote['comparison_providers'] ?? [],
        );

        return $quote;
    }
}
