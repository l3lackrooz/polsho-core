<?php

namespace App\Domain\Market\Infrastructure\Providers\Nobitex;

use App\Domain\Market\Application\DTO\MarketSubscriptionDTO;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceSnapshot;
use App\Domain\Market\Contracts\Capabilities\SupportsPriceStream;
use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use App\Domain\Market\Infrastructure\Support\WebSockets\TextWebSocketClient;
use Illuminate\Support\Collection;

class NobitexDriver implements MarketDataProviderInterface, SupportsPriceSnapshot, SupportsPriceStream
{
    public function __construct(
        private readonly NobitexClient $client,
        private readonly NobitexMapper $mapper,
        private readonly TextWebSocketClient $webSocketClient,
        private readonly string $streamUrl,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function name(): string
    {
        return 'nobitex';
    }

    public function healthCheck(): bool
    {
        try {
            $this->client->fetchTickers(['BTCUSDT']);

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

        $rows = $this->client->fetchTickers(array_keys($subscriptions));

        return $this->mapper->mapSnapshot($rows, $subscriptions, $this->name());
    }

    public function subscribePrices(Collection $instruments, callable $handler): void
    {
        $subscriptions = $this->normalizeSubscriptions($instruments);
        if ($subscriptions === []) {
            return;
        }

        $this->webSocketClient->connect($this->streamUrl);
        $this->webSocketClient->sendText(json_encode([
            'op' => 'subscribe',
            'channel' => 'ticker',
            'symbols' => array_keys($subscriptions),
        ], JSON_THROW_ON_ERROR));

        $this->webSocketClient->listen(function (string $message) use ($subscriptions, $handler): void {
            $payload = json_decode($message, true);
            if (!is_array($payload)) {
                return;
            }

            $symbol = (string) ($payload['symbol'] ?? '');
            if ($symbol === '' || !isset($subscriptions[$symbol])) {
                return;
            }

            $handler($this->mapper->mapStream($payload, $subscriptions[$symbol], $this->name()));
        });
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
