<?php

namespace App\Domain\Market\Infrastructure\Providers\Wallex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Support\Collection;

class WallexDriver implements MarketDataProviderInterface, SupportsPriceSnapshot
{
    public function __construct(
        private readonly WallexClient $client,
        private readonly WallexMapper $mapper,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function name(): string
    {
        return 'wallex';
    }

    public function healthCheck(): bool
    {
        try {
            $this->client->fetchMarkets();

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

        $rows = $this->client->fetchMarkets();

        return $this->mapper->mapSnapshot($rows, $subscriptions, $this->name());
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
