<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketSnapshot extends Model
{
    protected $table = 'market_snapshots';

    protected $fillable = [
        'provider_market_id',
        'dedupe_hash',
        'bid',
        'ask',
        'last_price',
        'volume_24h',
        'high_24h',
        'low_24h',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function providerMarket(): BelongsTo
    {
        return $this->belongsTo(ProviderMarket::class, 'provider_market_id');
    }
}
