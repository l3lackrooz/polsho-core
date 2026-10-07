<?php

namespace App\Domain\Market\Infrastructure\Providers\Tabdeal;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Support\Collection;

class TabdealDriver implements MarketDataProviderInterface, SupportsPriceSnapshot
{
    public function __construct(
        private readonly TabdealClient $client,
        private readonly TabdealMapper $mapper,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function name(): string
    {
        return 'tabdeal';
    }

    public function healthCheck(): bool
    {
        try {
            $rows = $this->client->fetchTicker('BTCIRT');

            return is_numeric($rows['BTC']['IRT']['price'] ?? null)
                && (float) $rows['BTC']['IRT']['price'] > 0;
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

        $symbols = array_keys($subscriptions);

        $rows = $this->client->fetchTickers($symbols);

        return $this->mapper->mapSnapshot($rows, $subscriptions, $this->name());
    }

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
