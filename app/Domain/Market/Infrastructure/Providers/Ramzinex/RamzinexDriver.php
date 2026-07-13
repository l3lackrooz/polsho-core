<?php

namespace App\Domain\Market\Infrastructure\Providers\Ramzinex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RamzinexDriver implements MarketDataProviderInterface, SupportsPriceSnapshot
{
    public function __construct(
        private readonly RamzinexClient $client,
        private readonly RamzinexMapper $mapper,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function name(): string
    {
        return 'ramzinex';
    }

    public function healthCheck(): bool
    {
        try {
            $this->client->fetchPairs();
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
        $rows = $this->client->fetchPairs();

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

        $remoteSymbol = $instrument->remote_symbol;
        $subscription = $this->subscriptions->forProvider(
            $instrument,
            $this->name()
        );

        if ($subscription instanceof MarketSubscriptionDTO) {
            $subscriptions[$remoteSymbol] = $subscription;
        }
    }

    return $subscriptions;
}

private function toRamzinexSymbol(string $instrument): string
{
    return strtolower(str_replace('-', '', $instrument));
}

}
