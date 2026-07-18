<?php

namespace App\Domain\Market\Infrastructure\Providers\Tala;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Support\Collection;

class TalaDriver implements MarketDataProviderInterface, SupportsPriceSnapshot
{
    public function __construct(
        private readonly TalaClient $client,
        private readonly TalaMapper $mapper,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function name(): string
    {
        return 'tala';
    }

    public function healthCheck(): bool
    {
        try {
            return $this->client->fetchPrices() !== [];
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

        return $this->mapper->mapSnapshot(
            $this->client->fetchPrices(),
            $subscriptions,
            $this->name(),
        );
    }

    /** @return array<string, MarketSubscriptionDTO> */
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
