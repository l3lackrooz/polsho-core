<?php

namespace App\Domain\Market\Infrastructure\Stores;

use App\Domain\Market\Application\DTO\QuoteDTO;
use Illuminate\Support\Facades\Redis;

class LatestQuoteStore
{
    private string $keyPrefix = "market:quotes:";

    private function instrumentKey(string $instrument): string
    {
        return $this->keyPrefix . $instrument;
    }

    /**
     * Atomic write — هر provider فقط فیلد خودش را در hash آپدیت می‌کند
     */
    public function put(QuoteDTO $quote): void
    {
        $key = $this->instrumentKey($quote->instrument);

        Redis::hset(
            $key,
            $quote->provider,
            json_encode([
                'instrument'         => $quote->instrument,
                'provider'           => $quote->provider,
                'bid'                => $quote->bid,
                'ask'                => $quote->ask,
                'last'               => $quote->last,
                'volume'             => $quote->volume,
                'mid'                => $quote->mid(),
                'spread'             => $quote->spread(),
                'timestamp'          => $quote->timestamp,
                'provider_market_id' => $quote->providerMarketId,
                'received_at'        => now()->toIso8601String(),
            ])
        );
    }

    /**
     * خواندن تمام providerها برای یک instrument
     */
    public function getAll(string $instrument): array
    {
        $key = $this->instrumentKey($instrument);

        $rows = Redis::hgetall($key);

        $result = [];

        foreach ($rows as $provider => $json) {
            $result[$provider] = json_decode($json, true);
        }

        return $result;
    }

    /**
     * حذف provider از یک instrument
     */
    public function removeProvider(string $instrument, string $provider): void
    {
        Redis::hdel($this->instrumentKey($instrument), $provider);
    }

    /**
     * پاک‌کردن کامل instrument
     */
    public function clearInstrument(string $instrument): void
    {
        Redis::del($this->instrumentKey($instrument));
    }
}
