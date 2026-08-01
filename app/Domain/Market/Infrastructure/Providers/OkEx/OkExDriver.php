<?php

namespace App\Domain\Market\Infrastructure\Providers\OkEx;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OkExDriver implements MarketDataProviderInterface, SupportsPriceSnapshot
{
    public function __construct(
        private readonly OkExClient $client,
        private readonly OkExMapper $mapper,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function name(): string
    {
        return 'ok-ex';
    }

    public function healthCheck(): bool
    {
        try {
            $this->client->fetchTickers();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function fetchPrices(Collection $instruments): array
    {
        $subscriptions = $this->normalizeSubscriptions($instruments);

        if ($subscriptions === []) {
            return [];
        }

        $orderBooks = [];
        foreach (array_keys($subscriptions) as $symbol) {
            try {
                $orderBooks[$symbol] = $this->client->fetchOrderBook($symbol);
            } catch (\Throwable $exception) {
                Log::warning(sprintf('OK-EX order book fetch failed for [%s]: %s', $symbol, $exception->getMessage()));
            }
        }

        return $this->mapper->mapSnapshot(
            tickers: $this->client->fetchTickers(),
            orderBooks: $orderBooks,
            subscriptions: $subscriptions,
            provider: $this->name(),
        );
    }

    /**
     * @param  Collection<int, mixed>  $instruments
     * @return array<string, MarketSubscriptionDTO>
     */
    private function normalizeSubscriptions(Collection $instruments): array
    {
        $subscriptions = [];

        foreach ($instruments as $instrument) {
            $subscription = $this->subscriptions->forProvider($instrument, $this->name());

            if ($subscription instanceof MarketSubscriptionDTO) {
                $subscriptions[$subscription->remoteSymbol] = $subscription;
            }
        }

        return $subscriptions;
    }
}
