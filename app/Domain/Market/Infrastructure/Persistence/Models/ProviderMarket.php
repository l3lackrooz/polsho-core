<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use App\Domain\Asset\Models\Instrument;
use App\Domain\Market\Infrastructure\Subscriptions\MarketSubscriptionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderMarket extends Model
{
    protected $table = 'provider_markets';

    protected $fillable = [
        'provider_id',
        'instrument_id',
        'remote_symbol',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $providerMarket): void {
            self::forgetSubscriptionMappings($providerMarket->provider_id);

            if ($providerMarket->wasChanged('provider_id')) {
                self::forgetSubscriptionMappings((int) $providerMarket->getOriginal('provider_id'));
            }
        });

        static::deleted(function (self $providerMarket): void {
            self::forgetSubscriptionMappings($providerMarket->provider_id);
        });
    }

    private static function forgetSubscriptionMappings(int $providerId): bool
    {
        $provider = MarketProvider::query()->find($providerId);
        if ($provider === null) {
            return false;
        }

        MarketSubscriptionFactory::forgetProviderMappings($provider->slug ?: $provider->name);

        return true;
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(MarketProvider::class, 'provider_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'instrument_id');
    }
}
