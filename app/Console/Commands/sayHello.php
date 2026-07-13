<?php

namespace App\Console\Commands;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Providers\MarketProviderFactory;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use App\Domain\Market\MarketServiceProvider;
use Illuminate\Console\Command;

class sayHello extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:say-hello';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $providers = app(ProviderFactory::class );
        $provider = MarketProvider::query()
            ->with([
                'markets' => fn ($query) => $query->where('status', 'active')->with('instrument'),
            ])
            ->find(3);

        $driver = $providers->make($provider);

        $result = $driver->fetchPrices($provider->markets);
    }
}
