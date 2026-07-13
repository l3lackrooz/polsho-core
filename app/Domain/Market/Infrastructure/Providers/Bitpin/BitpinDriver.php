<?php

namespace App\Domain\Market\Infrastructure\Providers\Bitpin;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BitpinDriver implements MarketDataProviderInterface, SupportsPriceSnapshot
{
    public function __construct(
        private readonly BitpinClient $client,
        private readonly BitpinMapper $mapper,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function name(): string
    {
        return 'bitpin';
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

        $tickers = $this->client->fetchTickers();

        // Bitpin tickers have no bid/ask, so pull top-of-book per subscribed symbol.
        $orderBooks = [];
        foreach (array_keys($subscriptions) as $symbol) {
            try {
                $orderBooks[$symbol] = $this->client->fetchOrderBook($symbol);
            } catch (\Throwable $e) {
                Log::warning(sprintf('Bitpin orderbook fetch failed for [%s]: %s', $symbol, $e->getMessage()));
            }
        }

        return $this->mapper->mapSnapshot($tickers, $orderBooks, $subscriptions, $this->name());
    }

    /**
     * @param Collection<int, mixed> $instruments
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
