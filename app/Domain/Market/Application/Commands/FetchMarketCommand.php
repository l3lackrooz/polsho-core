<?php

namespace App\Domain\Market\Application\Commands;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use Illuminate\Console\Command;

class FetchMarketCommand extends Command
{
    public function __construct(
        private readonly ProviderFactory $providers,
    ) {
        parent::__construct();
    }

    protected $signature = 'market:fetch {provider?}';
    protected $description = 'Fetch market data from provider';

    public function handle()
    {
        $providerName = $this->argument('provider');
        $providerModel = MarketProvider::query()
            ->where('name', $providerName)
            ->orWhere('driver', $providerName)
            ->orWhere('slug', $providerName)
            ->firstOrFail();

        $provider = $this->providers->make($providerModel);
    
        $quotes = $provider->healthCheck();

    }
}
