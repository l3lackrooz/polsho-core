<?php

namespace App\Domain\Market\Application\Jobs;

use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use App\Domain\Market\Infrastructure\Providers\ProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncProviderQuotesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly int $providerId,
    ) {
        $this->onQueue(config('queue.queues.market'));
    }

    public function handle(ProviderFactory $providers): void
    {
        $provider = MarketProvider::query()
            ->with([
                'markets' => fn ($query) => $query->where('status', 'active')->with('instrument'),
            ])
            ->find($this->providerId);
        if ($provider === null) {
            return;
        }

        if ($provider->markets->isEmpty()) {
            Log::warning(sprintf('Skipping provider [%s]; no active markets found.', $provider->slug ?: $provider->name));
            return;
        }

        $driver = $providers->make($provider);
        $quotes = $driver->fetchPrices($provider->markets);

        if ($quotes === []) {
            Log::info(sprintf('Provider [%s] returned no quotes.', $driver->name()));
            return;
        }

        ProcessProviderQuotesJob::dispatch(
            array_map(
                static fn ($quote): array => $quote->toArray(),
                $quotes,
            ),
        );
    }
}
