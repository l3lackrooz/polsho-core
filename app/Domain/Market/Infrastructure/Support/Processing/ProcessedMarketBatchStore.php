<?php

namespace App\Domain\Market\Infrastructure\Support\Processing;

use Illuminate\Support\Facades\Cache;

class ProcessedMarketBatchStore
{
    public function remember(string $batchKey, callable $callback, int $ttlSeconds = 3600): bool
    {
        $cacheKey = sprintf('market.processed-batch.%s', sha1($batchKey));

        return Cache::lock($cacheKey, $ttlSeconds)->get(function () use ($cacheKey, $callback, $ttlSeconds): bool {
            if (Cache::has($cacheKey)) {
                return false;
            }

            $callback();
            Cache::put($cacheKey, true, now()->addSeconds($ttlSeconds));

            return true;
        }) ?? false;
    }
}
