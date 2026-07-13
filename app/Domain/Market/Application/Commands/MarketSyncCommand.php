<?php

namespace App\Domain\Market\Application\Commands;

use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use Illuminate\Console\Command;

class MarketSyncCommand extends Command
{
    protected $signature = 'market:sync {provider? : Optional provider name or slug} {--now : Run the sync inline instead of queueing}';

    protected $description = 'Dispatch market quote sync jobs for one provider or every active provider';

    public function handle(): int
    {
        $provider = $this->argument('provider');
        $runInline = (bool) $this->option('now');

        $providers = MarketProvider::query()
            ->where('status', 'active')
            ->when(
                is_string($provider) && $provider !== '',
                fn ($query) => $query->where(function ($builder) use ($provider): void {
                    $builder
                        ->where('name', $provider)
                        ->orWhere('slug', $provider);
                }),
            )
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->get(['id', 'name', 'slug']);

        if ($providers->isEmpty()) {
            $this->warn('No active providers matched the sync request.');
            return self::FAILURE;
        }

        foreach ($providers as $providerModel) {
            $job = new SyncProviderQuotesJob($providerModel->id);

            if ($runInline) {
                dispatch_sync($job);
                $this->line(sprintf('Synced %s inline.', $providerModel->slug ?: $providerModel->name));
                continue; 
            }

            dispatch($job);
            $this->line(sprintf('Dispatched sync for %s.', $providerModel->slug ?: $providerModel->name));
        }

        return Command::SUCCESS;
    }
}
