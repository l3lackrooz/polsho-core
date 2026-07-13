<?php

namespace App\Domain\Market\Infrastructure\Stores;

use App\Domain\Market\Application\DTO\AggregatedQuoteDTO;
use Illuminate\Support\Facades\Redis;

class AggregateStore
{
    private const KEY = 'market:agg:%s';

    public function put(string $instrument, AggregatedQuoteDTO $payload): bool
    {
        $key = sprintf(self::KEY, $instrument);

        $encoded = json_encode($payload->toArray());

        $previous = Redis::get($key);

        if ($previous === $encoded) {
            return false;
        }

        Redis::set($key, $encoded);

        return true;
    }

    public function get(string $instrument): ?array
    {
        $data = Redis::get(sprintf(self::KEY, $instrument));

        return $data ? json_decode($data, true) : null;
    }
}
