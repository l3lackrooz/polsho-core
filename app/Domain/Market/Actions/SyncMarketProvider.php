<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\MarketProvider;
use DomainException;

class SyncMarketProvider
{
    /**
     * Queue a fresh provider fetch after an operator changes provider data.
     * This deliberately does not restart workers: jobs load the current
     * provider record from the database when they execute.
     */
    public function execute(MarketProvider $provider): void
    {
        if ($provider->status !== 'active') {
            throw new DomainException('Only active providers can be synced.');
        }

        if (! $provider->markets()->where('status', 'active')->exists()) {
            throw new DomainException('This provider has no active markets to sync.');
        }

        SyncProviderQuotesJob::dispatch($provider->id);
    }
}
