<?php

namespace App\Domain\Market\Infrastructure\Providers\Tabdeal;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceStream;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use App\Domain\Market\Infrastructure\Support\WebSockets\TextWebSocketClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
            $this->client->fetchTicker('BTCIRT');
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
