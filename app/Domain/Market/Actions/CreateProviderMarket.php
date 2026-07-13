<?php

namespace App\Domain\Market\Actions;

use App\Domain\Market\Application\Jobs\SyncProviderQuotesJob;
use App\Domain\Market\Infrastructure\Persistence\Models\ProviderMarket;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateProviderMarket
{
    public function execute(array $attributes): ProviderMarket
    {
        $payload = $this->normalize($attributes);

        $providerMarket = DB::transaction(
            fn (): ProviderMarket => ProviderMarket::query()->create($payload)
        );

        if ($providerMarket->status === 'active') {
            SyncProviderQuotesJob::dispatch($providerMarket->provider_id);
        }

        return $providerMarket;
    }

    private function normalize(array $attributes): array
    {
        $providerId = (int) Arr::get($attributes, 'provider_id');
        $instrumentId = (int) Arr::get($attributes, 'instrument_id');
        $remoteSymbol = trim((string) Arr::get($attributes, 'remote_symbol', ''));

        if ($providerId <= 0) {
            throw new InvalidArgumentException('Provider is required.');
        }

        if ($instrumentId <= 0) {
            throw new InvalidArgumentException('Instrument is required.');
        }

        if ($remoteSymbol === '') {
            throw new InvalidArgumentException('Remote symbol is required.');
        }

        return [
            'provider_id' => $providerId,
            'instrument_id' => $instrumentId,
            'remote_symbol' => $remoteSymbol,
            'status' => Arr::get($attributes, 'status', 'active'),
            'metadata' => $this->normalizeMetadata(Arr::get($attributes, 'metadata')),
        ];
    }

    private function normalizeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null || $metadata === '') {
            return null;
        }

        if (!is_array($metadata)) {
            throw new InvalidArgumentException('Provider market metadata must be an array.');
        }
        return $metadata;
    }
}
