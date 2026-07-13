<?php

namespace App\Domain\Market;

use App\Domain\Market\Infrastructure\Aggregation\PriceAggregator;
use App\Domain\Market\Infrastructure\Aggregation\LatestQuoteAggregator;
use App\Domain\Market\Infrastructure\Aggregation\ProviderManager;
use App\Domain\Market\Infrastructure\Broadcasting\MarketBroadcaster;
use App\Domain\Market\Infrastructure\Stores\LatestQuoteStore;
use App\Domain\Market\Infrastructure\Persistence\MarketSnapshotWriter;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use App\Domain\Market\Infrastructure\Support\Processing\ProcessedMarketBatchStore;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Throwable;
use Illuminate\Support\ServiceProvider;

class MarketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('market.php'), 'market');

        $this->app->singleton(ProviderManager::class, function () {
            $factory = $this->app->make(ProviderFactory::class);

            try {
                $providers = MarketProvider::query()
                    ->where('status', 'active')
                    ->orderByDesc('is_default')
                    ->orderBy('priority')
                    ->get()
                    ->map(fn (MarketProvider $provider) => $factory->make($provider))
                    ->all();
            } catch (Throwable) {
                // Allow composer/package discovery and container bootstrapping to succeed
                // before the database service is reachable.
                $providers = [];
            }

            return new ProviderManager($providers);
        });

        $this->app->singleton(MarketSubscriptionFactory::class);
        $this->app->singleton(ProviderFactory::class);
        $this->app->singleton(MarketSnapshotWriter::class);
        $this->app->singleton(LatestQuoteStore::class);
        $this->app->singleton(LatestQuoteAggregator::class);
        $this->app->singleton(ProcessedMarketBatchStore::class);
        $this->app->singleton(PriceAggregator::class);
        $this->app->singleton(MarketBroadcaster::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Domain/Market/Infrastructure/Persistence/Migrations'));
    }
}
