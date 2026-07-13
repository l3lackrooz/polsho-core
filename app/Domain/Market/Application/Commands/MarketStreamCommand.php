<?php

namespace App\Domain\Market\Application\Commands;

use App\Domain\Market\Infrastructure\Aggregation\ProviderManager;
use App\Domain\Market\Infrastructure\Broadcasting\MarketBroadcaster;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Console\Command;

class MarketStreamCommand extends Command
{
    protected $signature = 'market:stream {provider : Provider name, for example sample_exchange} {instrument* : Remote symbols like BTCUSDT ETHUSDT}';

    protected $description = 'Listen to an exchange websocket and publish every normalized quote over Reverb';

    public function __construct(
        private readonly ProviderManager $providers,
        private readonly MarketBroadcaster $broadcaster,
        private readonly MarketSubscriptionFactory $subscriptions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $providerName = (string) $this->argument('provider');
        $provider = $this->providers->stream($providerName);
        $subscriptions = $this->subscriptions->forProviderSymbols(
            $this->argument('instrument'),
            $providerName,
        );

        $this->info(sprintf(
            'Streaming %s for %s',
            $provider->name(),
            $subscriptions->pluck('remoteSymbol')->implode(', '),
        ));

        $provider->subscribePrices($subscriptions, function ($quote): void {
            $this->line(json_encode($quote->toArray(), JSON_UNESCAPED_SLASHES));
            $this->broadcaster->publishQuote($quote);
        });

        return self::SUCCESS;
    }
}
