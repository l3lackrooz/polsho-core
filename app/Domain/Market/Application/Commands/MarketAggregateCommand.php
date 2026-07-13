<?php

namespace App\Domain\Market\Application\Commands;

use App\Domain\Market\Infrastructure\Aggregation\PriceAggregator;
use App\Domain\Market\Infrastructure\Broadcasting\MarketBroadcaster;
use Illuminate\Console\Command;

class MarketAggregateCommand extends Command
{
    protected $signature = 'market:aggregate {instrument* : Canonical or raw symbols like BTC-USDT BTCUSDT ETH/USDT} {--broadcast : Publish the aggregated payload over Reverb}';

    protected $description = 'Aggregate best bid/ask across every registered snapshot provider';

    public function __construct(
        private readonly PriceAggregator $aggregator,
        private readonly MarketBroadcaster $broadcaster,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $aggregated = $this->aggregator->aggregate($this->argument('instrument'));

        if ($aggregated === []) {
            $this->warn('No quotes returned by the registered providers.');
            return self::FAILURE;
        }

        foreach ($aggregated as $quote) {
            $this->line(json_encode($quote->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($this->option('broadcast')) {
                $this->broadcaster->publishAggregated($quote);
            }
        }

        return self::SUCCESS;
    }
}
