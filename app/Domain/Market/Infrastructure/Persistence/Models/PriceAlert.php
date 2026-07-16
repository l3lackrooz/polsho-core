<?php

namespace App\Domain\Market\Infrastructure\Persistence\Models;

use App\Domain\Asset\Models\Instrument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceAlert extends Model
{
    protected $fillable = ['user_id', 'instrument_id', 'provider_market_id', 'scope', 'condition', 'target_price', 'status', 'repeat', 'notify_push', 'notify_in_app', 'last_triggered_at', 'expires_at', 'metadata'];

    protected $casts = ['target_price' => 'decimal:10', 'notify_push' => 'boolean', 'notify_in_app' => 'boolean', 'last_triggered_at' => 'datetime', 'expires_at' => 'datetime', 'metadata' => 'array'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function providerMarket(): BelongsTo
    {
        return $this->belongsTo(ProviderMarket::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PriceAlertEvent::class)->orderBy('occurred_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
