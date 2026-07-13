<?php

namespace App\Domain\Market\Infrastructure\Providers;

use App\Domain\Market\Contracts\MarketDataProviderInterface;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Providers\Bitpin\BitpinClient;
use App\Domain\Market\Infrastructure\Providers\Bitpin\BitpinMapper;
use App\Domain\Market\Infrastructure\Providers\Nobitex\NobitexClient;
use App\Domain\Market\Infrastructure\Providers\Nobitex\NobitexDriver;
use App\Domain\Market\Infrastructure\Providers\Nobitex\NobitexMapper;
use App\Domain\Market\Infrastructure\Providers\Ompfinex\OmpfinexClient;
use App\Domain\Market\Infrastructure\Providers\Ompfinex\OmpfinexMapper;
use App\Domain\Market\Infrastructure\Providers\Ramzinex\RamzinexClient;
use App\Domain\Market\Infrastructure\Providers\Ramzinex\RamzinexMapper;
use App\Domain\Market\Infrastructure\Providers\SampleExchange\SampleExchangeClient;
use App\Domain\Market\Infrastructure\Providers\SampleExchange\SampleExchangeDriver;
use App\Domain\Market\Infrastructure\Providers\SampleExchange\SampleExchangeMapper;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealClient;
use App\Domain\Market\Infrastructure\Providers\Tabdeal\TabdealMapper;
use App\Domain\Market\Infrastructure\Providers\Tgju\TgjuClient;
use App\Domain\Market\Infrastructure\Providers\Tgju\TgjuMapper;
use App\Domain\Market\Infrastructure\Providers\Wallex\WallexClient;
use App\Domain\Market\Infrastructure\Providers\Wallex\WallexMapper;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use App\Domain\Market\Infrastructure\Support\WebSockets\TextWebSocketClient;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ProviderFactory
{
    public function __construct(
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {}

    public function make(MarketProvider $provider): MarketDataProviderInterface
    {
        $providerKey = strtolower((string) ($provider->slug ?: $provider->name));
        $config = is_array($provider->config) ? $provider->config : [];
        $timeout = (int) data_get($config, 'rest.timeout', 10);
        $streamUrl = (string) data_get($config, 'websocket.url', '');
        Log::channel('dev')->info('step 2',[$providerKey]);

        return match ($providerKey) {

            'nobitex' => new $provider->driver(
                client: new NobitexClient(
                    baseUrl: $provider->base_url,
                    timeout: $timeout,
                ),
                mapper: new NobitexMapper(),
                webSocketClient: new TextWebSocketClient(),
                streamUrl: $streamUrl,
                subscriptions: $this->subscriptions,
            ),
            'ramzinex' => new $provider->driver(
                client: new RamzinexClient(
                    baseUrl: $provider->base_url,
                    timeout: $timeout,
                ),
                mapper: new RamzinexMapper(),
                subscriptions: $this->subscriptions,
            ),
            'tabdeal' => new $provider->driver(
                client: new TabdealClient(
                    baseUrl: $provider->base_url,
                    timeout: $timeout,
                ),
                mapper: new TabdealMapper(),
                subscriptions: $this->subscriptions,
            ),
            'ompfinex' => new $provider->driver(
                client: new OmpfinexClient(
                    baseUrl: $provider->base_url,
                    timeout: $timeout,
                ),
                mapper: new OmpfinexMapper(),
                subscriptions: $this->subscriptions,
            ),
            'bitpin' => new $provider->driver(
                client: new BitpinClient(
                    baseUrl: $provider->base_url,
                    timeout: $timeout,
                ),
                mapper: new BitpinMapper(),
                subscriptions: $this->subscriptions,
            ),
            'wallex' => new $provider->driver(
                client: new WallexClient(
                    baseUrl: $provider->base_url,
                    timeout: $timeout,
                ),
                mapper: new WallexMapper(),
                subscriptions: $this->subscriptions,
            ),
            'tgju' => new $provider->driver(
                client: new TgjuClient(
                    baseUrl: $provider->base_url,
                    rev: (string) data_get($config, 'rest.rev', ''),
                    timeout: $timeout,
                ),
                mapper: new TgjuMapper(),
                subscriptions: $this->subscriptions,
            ),
            'sample_exchange' => new SampleExchangeDriver(
                client: new SampleExchangeClient(
                    baseUrl: $provider->base_url,
                    timeout: $timeout,
                ),
                mapper: new SampleExchangeMapper(),
                webSocketClient: new TextWebSocketClient(),
                streamUrl: $streamUrl,
                subscriptions: $this->subscriptions,
            ),
            default => throw new InvalidArgumentException("Unknown provider driver [{$provider->driver}]"),
        };
    }
}
